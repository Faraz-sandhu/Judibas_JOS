<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CommunicationAccess as Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CommunicationStoryController extends Controller
{
    public static function listing(Request $r)
    {
        return self::visible($r)->orderByDesc('id')->get()->map(function ($s) {
            $s->attachment_url = $s->attachment_path ? '/communication/api/stories/'.$s->id.'/attachment' : null;
            unset($s->attachment_path);

            return $s;
        });
    }

    private static function visible(Request $r)
    {
        $uid = Access::subject($r);
        $query = DB::table('communication_stories')->where('expires_at', '>', now());
        if ($uid !== null) {
            $company = Access::company($uid);
            $query->where(function ($q) use ($company) {
                $q->whereNull('company_id');
                if ($company !== null) $q->orWhere('company_id', $company);
            });
        }
        return $query;
    }
    public function store(Request $r)
    {
        Access::mutable($r);
        abort_unless(Access::manager($r), 403); abort_unless(Access::super($r) || Access::company() !== null, 403, 'A company is required to publish stories.');
        $v = $r->validate(['title' => 'required|string|max:100', 'body' => 'nullable|string|max:3000', 'attachment' => 'nullable|file|max:20480|mimes:jpg,jpeg,png,webp,mp4,webm,mp3,wav,m4a,pdf,txt,csv,doc,docx,xls,xlsx,zip']);
        abort_unless(trim($v['body'] ?? '') !== '' || $r->hasFile('attachment'), 422, 'Add story text or an attachment.');
        $file = $r->file('attachment');
        $path = $file ? $file->store('communication-stories', 'local') : null;
        try {
            $id = DB::table('communication_stories')->insertGetId(['author_id' => Access::super($r) ? null : $r->user()->id, 'company_id' => Access::super($r) ? null : Access::company(), 'title' => $v['title'], 'body' => $v['body'] ?? '', 'attachment_path' => $path, 'attachment_name' => $file?->getClientOriginalName(), 'attachment_mime' => $file?->getMimeType(), 'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()]);
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }throw $e;
        }

        return response()->json(['id' => $id], 201);
    }

    public function attachment(Request $r, int $id)
    {
        Access::subject($r);
        $s = self::visible($r)->where('id', $id)->first();
        abort_unless($s && $s->attachment_path && Storage::disk('local')->exists($s->attachment_path), 404);
        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        if (! $r->boolean('download') && preg_match('~^(image|video|audio)/~', $s->attachment_mime ?? '')) {
            return response()->file(Storage::disk('local')->path($s->attachment_path), $headers);
        }

        return Storage::disk('local')->download($s->attachment_path, $s->attachment_name, $headers);
    }

    public function viewed(Request $r, int $id)
    {
        Access::mutable($r);
        abort_unless(! Access::super($r), 403);
        abort_unless(self::visible($r)->where('id', $id)->exists(), 404);
        DB::table('communication_story_views')->insertOrIgnore(['story_id' => $id, 'user_id' => $r->user()->id, 'first_viewed_at' => now(), 'last_viewed_at' => now()]);
        DB::table('communication_story_views')->where('story_id', $id)->where('user_id', $r->user()->id)->update(['last_viewed_at' => now()]);

        return response()->json(['message' => 'Story viewed.']);
    }

    public function viewers(Request $r, int $id)
    {
        Access::subject($r);
        $story = self::visible($r)->where('id', $id)->first(); abort_unless($story, 404); abort_unless(Access::super($r) || (Access::manager($r) && (int) $story->author_id === (int) $r->user()->id), 403);
        abort_unless(self::visible($r)->where('id', $id)->exists(), 404);
        $r->validate(['page' => 'nullable|integer|min:1']);
        $page = User::join('communication_story_views as v', 'v.user_id', '=', 'users.id')->where('v.story_id', $id)
            ->orderByDesc('v.first_viewed_at')->orderByDesc('v.id')->paginate(30, ['users.id', 'users.name', 'users.email', 'users.profile_photo_path', 'v.first_viewed_at', 'v.last_viewed_at']);

        return response()->json(['viewers' => $page->items(), 'total' => $page->total(), 'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null]);
    }

    public function destroy(Request $r, int $id)
    {
        Access::mutable($r);
        abort_unless(Access::super($r), 403);
        $s = DB::table('communication_stories')->where('id', $id)->first();
        abort_unless($s, 404);
        if ($s->attachment_path) {
            Storage::disk('local')->delete($s->attachment_path);
        }
        DB::table('communication_stories')->where('id', $id)->delete();

        return response()->json(['message' => 'Story removed.']);
    }
}
