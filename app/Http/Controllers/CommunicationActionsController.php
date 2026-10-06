<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CommunicationAccess as Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommunicationActionsController extends Controller
{
    public function profile(Request $r)
    {
        Access::mutable($r);
        abort_unless(! Access::super($r) && Auth::check(), 403);
        $v = $r->validate(['name' => 'required|string|max:100', 'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048', 'remove_photo' => 'nullable|boolean', 'current_password' => 'nullable|string|max:200', 'password' => 'nullable|string|min:12|max:200|confirmed']);
        if (isset($v['password']) && ! Hash::check($v['current_password'] ?? '', Auth::user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'Your current password is incorrect.']);
        }
        if (trim($v['name']) === '') {
            throw ValidationException::withMessages(['name' => 'Enter your name.']);
        }
        $u = Auth::user();
        $old = $u->profile_photo_path;
        $path = $r->hasFile('photo') ? $r->file('photo')->store('communication-profiles', 'local') : null;
        try {
            $u->name = trim($v['name']);
            if ($path) {
                $u->profile_photo_path = $path;
            } elseif ($r->boolean('remove_photo')) {
                $u->profile_photo_path = null;
            }
            if (isset($v['password'])) {
                $u->password = $v['password'];
                $u->setRememberToken(Str::random(60));
            }
            $u->save();
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }throw $e;
        }
        if ($old && $old !== $u->profile_photo_path) {
            Storage::disk('local')->delete($old);
        }
        if (isset($v['password'])) {
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $u->id)->where('id', '!=', $r->session()->getId())->delete();
            }
            $r->session()->regenerate();
        }

        return response()->json(['message' => 'Profile updated.', 'csrf' => csrf_token(), 'profile' => $u->only(['id', 'name', 'email', 'avatar_url'])]);
    }

    public function avatar(Request $r, int $id)
    {
        $subject = Access::subject($r);
        abort_unless($subject === null || $subject === $id || in_array($id, Access::eligible($subject), true), 403);
        $u = User::findOrFail($id);
        abort_unless($u->profile_photo_path && Storage::disk('local')->exists($u->profile_photo_path), 404);

        return response()->file(Storage::disk('local')->path($u->profile_photo_path), ['Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function message(Request $r, int $id)
    {
        Access::mutable($r);
        $m = DB::table('communication_messages')->where('id', $id)->whereNull('deleted_at')->first();
        abort_unless($m, 404);
        Access::conversation($r, $m->conversation_id);

        return $m;
    }

    public function pin(Request $r, int $id)
    {
        $m = $this->message($r, $id);
        $c = Access::conversation($r, $m->conversation_id);
        abort_unless($c->kind !== 'community' || Access::super($r), 403);
        $v = $r->validate(['pinned' => 'required|boolean']);
        DB::transaction(function () use ($m, $v) {
            DB::table('communication_conversations')->where('id', $m->conversation_id)->lockForUpdate()->first();
            abort_if($v['pinned'] && ! $m->pinned_at && DB::table('communication_messages')->where('conversation_id', $m->conversation_id)->whereNull('deleted_at')->whereNotNull('pinned_at')->count() >= 3, 422, 'Unpin a message first. A chat can have up to three pinned messages.');
            DB::table('communication_messages')->where('id', $m->id)->update(['pinned_at' => $v['pinned'] ? now() : null, 'pinned_by' => $v['pinned'] ? Auth::id() : null, 'updated_at' => now()]);
        });

        return response()->json(['message' => $v['pinned'] ? 'Message pinned.' : 'Message unpinned.']);
    }

    public function forward(Request $r, int $id)
    {
        $m = $this->message($r, $id);
        $v = $r->validate(['conversation_id' => 'required|integer']);
        $c = Access::conversation($r, $v['conversation_id']);
        abort_unless($c->kind !== 'community' || Access::super($r), 403, 'Only Super Admin can post to communities.');
        $path = null;
        if ($m->attachment_path) {
            abort_unless(Storage::disk('local')->exists($m->attachment_path), 404, 'The attachment is no longer available.');
            $path = 'communication/'.Str::uuid().'.'.pathinfo($m->attachment_path, PATHINFO_EXTENSION);
            abort_unless(Storage::disk('local')->copy($m->attachment_path, $path), 500, 'Unable to copy this attachment.');
        }
        try {
            $mid = DB::transaction(function () use ($r, $m, $v, $path) {
                $id = DB::table('communication_messages')->insertGetId(['conversation_id' => $v['conversation_id'], 'sender_id' => Access::super($r) ? null : Auth::id(), 'sender_name' => Access::super($r) ? 'Super Admin' : Auth::user()->name, 'body' => $m->body, 'attachment_path' => $path, 'attachment_name' => $m->attachment_name, 'forwarded' => true, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('communication_conversations')->where('id', $v['conversation_id'])->update(['updated_at' => now()]);

                return $id;
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }throw $e;
        }

        return response()->json(['id' => $mid, 'conversation_id' => $v['conversation_id'], 'message' => 'Message forwarded.'], 201);
    }
}
