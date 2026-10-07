<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CommunicationAccess as Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CommunicationController extends Controller
{
    private function isAdmin(Request $r): bool
    {
        return (bool) $r->session()->get('judibas_admin');
    }

    private function authorize(Request $r): void
    {
        Access::authorize($r);
    }

    private function subject(Request $r): ?int
    {
        return Access::subject($r);
    }

    private function scope($query, ?int $user): void
    {
        Access::scope($query, $user);
    }

    private function conversation(Request $r, int $id)
    {
        return Access::conversation($r, $id);
    }

    private function mutable(Request $r): void
    {
        Access::mutable($r);
    }

    private function audit(Request $r, string $action, ?int $conversation = null): void
    {
        DB::table('communication_audits')->insert(['admin_email' => config('judibas.admin_email'), 'viewed_user_id' => $r->filled('view_user') ? (int) $r->input('view_user') : null, 'conversation_id' => $conversation, 'action' => $action, 'created_at' => now()]);
    }

    public function page(Request $r)
    {
        if (! $this->isAdmin($r) && ! Auth::check()) {
            return redirect('/login');
        }
        $this->authorize($r);

        return app(PortalController::class)->index($r, 'communication');
    }

    public function data(Request $r)
    {
        $user = $this->subject($r);
        $q = DB::table('communication_conversations as c')->leftJoin('communication_companies as co', 'co.id', '=', 'c.company_id')->select('c.*', 'co.name as company_name');
        $this->scope($q, $user);
        $conversations = $q->orderByDesc('c.updated_at')->get();
        $conversationIds = $conversations->pluck('id');
        $membersByChat = DB::table('communication_members as cm')->join('users as u', 'u.id', '=', 'cm.user_id')->whereIn('cm.conversation_id', $conversationIds)->get(['cm.conversation_id', 'u.id', 'u.name'])->groupBy('conversation_id');
        $latestIds = DB::table('communication_messages')->whereIn('conversation_id', $conversationIds)->selectRaw('max(id) as id')->groupBy('conversation_id');
        $latest = DB::table('communication_messages')->whereIn('id', $latestIds)->get()->keyBy('conversation_id');
        $unreadCounts = collect();
        if ($user !== null) {
            $unreadCounts = DB::table('communication_messages as m')->leftJoin('communication_members as reads', function ($join) use ($user) {
                $join->on('reads.conversation_id', '=', 'm.conversation_id')->where('reads.user_id', $user);
            })
                ->whereIn('m.conversation_id', $conversationIds)->whereNull('m.deleted_at')
                ->where(function ($q) use ($user) {
                    $q->where('m.sender_id', '!=', $user)->orWhereNull('m.sender_id');
                })
                ->where(function ($q) {
                    $q->whereNull('reads.read_at')->orWhereColumn('m.created_at', '>', 'reads.read_at');
                })
                ->groupBy('m.conversation_id')->selectRaw('m.conversation_id,count(*) as total')->pluck('total', 'conversation_id');
        }
        foreach ($conversations as $c) {
            $members = $membersByChat->get($c->id, collect())->map(fn ($m) => ['id' => $m->id, 'name' => $m->name]);
            $c->members = $members;
            if ($c->kind === 'direct') {
                $c->name = $members->where('id', '!=', $user)->pluck('name')->implode(', ') ?: 'Personal chat';
            }
            if ($c->kind === 'community') {
                $c->name = $c->company_name;
            }
            $last = $latest->get($c->id);
            $c->preview = $last ? ($last->deleted_at ? 'Message deleted' : ($last->body ?: 'Attachment')) : 'No messages yet';
            $c->last_message_at = $last?->created_at;
            $c->unread = (int) $unreadCounts->get($c->id, 0);
        }
        $people = User::where('is_active', true)->whereIn('id', DB::table('user_product_access')->where('product_slug', 'communication')->select('user_id'));
        if ($user !== null) {
            $people->whereIn('id', Access::eligible($user));
        }
        $companies = DB::table('communication_companies')->orderBy('name');
        if ($user !== null) {
            $companies->where('id', Access::company($user));
        }

        return response()->json(['conversations' => $conversations, 'companies' => $companies->get(), 'teams' => [], 'people' => $people->orderBy('name')->get(['id', 'name', 'email', 'company_id', 'profile_photo_path']),
            'review_people' => $this->isAdmin($r) ? User::orderBy('name')->get(['id', 'name', 'email']) : [],
            'stories' => CommunicationStoryController::listing($r),
            'admin' => $this->isAdmin($r), 'manager' => Access::manager($r), 'company_id' => Access::company($user), 'user_id' => Auth::id(), 'profile' => ! $this->isAdmin($r) && Auth::check() ? Auth::user()->only(['id', 'name', 'email', 'avatar_url']) : null,
            'viewed_user' => $r->filled('view_user') ? User::findOrFail($user)->only(['id', 'name', 'email']) : null]);
    }

    public function review(Request $r)
    {
        $this->authorize($r);
        abort_unless($this->isAdmin($r), 403);
        $r->validate(['view_user' => 'required|integer|exists:users,id']);
        $this->audit($r, 'employee_review');

        return $this->data($r);
    }

    public function messages(Request $r, int $id)
    {
        $this->conversation($r, $id);
        $v = $r->validate(['before' => 'nullable|integer|min:1', 'after' => 'nullable|integer|min:1', 'open' => 'nullable|boolean', 'search' => 'nullable|string|max:200', 'around' => 'nullable|integer|min:1']);
        $q = DB::table('communication_messages')->where('conversation_id', $id);
        if (! empty($v['before'])) {
            $q->where('id', '<', $v['before']);
        }
        if (! empty($v['search'])) {
            $q->where('body', 'ilike', '%'.addcslashes($v['search'], '%_\\').'%');
        }
        $firstUnread = null;
        $subject = $this->subject($r);
        if ($r->boolean('open') && $subject !== null) {
            $readAt = DB::table('communication_members')->where('conversation_id', $id)->where('user_id', $subject)->value('read_at');
            $unread = (clone $q)->whereNull('deleted_at')->where(fn ($q) => $q->where('sender_id', '!=', $subject)->orWhereNull('sender_id'));
            if ($readAt) {
                $unread->where('created_at', '>', $readAt);
            }
            $firstUnread = $unread->orderBy('id')->value('id');
        }
        if ($firstUnread) {
            $messages = (clone $q)->where('id', '<', $firstUnread)->orderByDesc('id')->limit(10)->get()->reverse()->values()
                ->concat((clone $q)->where('id', '>=', $firstUnread)->orderBy('id')->limit(50)->get());
        } elseif (! empty($v['after'])) {
            $messages = (clone $q)->where('id', '>', $v['after'])->orderBy('id')->limit(50)->get();
        } elseif (! empty($v['around'])) {
            abort_unless(DB::table('communication_messages')->where('conversation_id', $id)->where('id', $v['around'])->exists(), 404);
            $messages = (clone $q)->where('id', '<=', $v['around'])->orderByDesc('id')->limit(25)->get()->reverse()->values()
                ->concat((clone $q)->where('id', '>', $v['around'])->orderBy('id')->limit(25)->get());
        } else {
            $messages = $q->orderByDesc('id')->limit(50)->get()->reverse()->values();
        }
        $reactions = DB::table('communication_reactions as cr')->join('users as u', 'u.id', '=', 'cr.user_id')->whereIn('cr.message_id', $messages->pluck('id'))->get(['cr.message_id', 'cr.user_id', 'cr.emoji', 'u.name'])->groupBy('message_id');
        foreach ($messages as $m) {
            $m->reactions = $m->deleted_at ? [] : $reactions->get($m->id, collect())->values();
            if ($m->deleted_at) {
                $m->body = null;
                $m->attachment_name = null;
            }
            $m->attachment_url = ! $m->deleted_at && $m->attachment_path ? '/communication/api/attachments/'.$m->id : null;
            unset($m->attachment_path);
        }
        if (! $this->isAdmin($r)) {
            DB::table('communication_members')->updateOrInsert(['conversation_id' => $id, 'user_id' => Auth::id()], ['read_at' => now()]);
        }
        if ($this->isAdmin($r)) {
            $auditKey = 'communication_review.'.$id.'.'.($r->input('view_user') ?: 'all');
            $last = $r->session()->get($auditKey, 0);
            if ($r->boolean('audit') || time() - $last >= 60) {
                $this->audit($r, 'conversation_read', $id);
                $r->session()->put($auditKey, time());
            }
        }

        $pinned = DB::table('communication_messages')->where('conversation_id', $id)->whereNull('deleted_at')->whereNotNull('pinned_at')->orderByDesc('pinned_at')->limit(3)->get(['id', 'body', 'sender_name', 'attachment_name', 'pinned_at']);

        return response()->json(['messages' => $messages, 'pinned' => $pinned, 'first_unread_id' => $firstUnread, 'has_more' => $messages->isNotEmpty() && (clone $q)->where('id', '<', $messages->first()->id)->exists(), 'has_newer' => $messages->isNotEmpty() && (clone $q)->where('id', '>', $messages->last()->id)->exists(), 'next_after' => $messages->last()?->id]);
    }

    public function shared(Request $r, int $id)
    {
        $c = $this->conversation($r, $id);
        $v = $r->validate(['kind' => ['required', Rule::in(['media', 'docs', 'links'])], 'before' => 'nullable|integer|min:1']);
        $q = DB::table('communication_messages')->where('conversation_id', $id)->whereNull('deleted_at');
        if ($v['kind'] === 'links') {
            $q->where('body', 'ilike', '%http%');
        } else {
            $q->whereNotNull('attachment_path');
            $operator = $v['kind'] === 'media' ? '~*' : '!~*';
            $q->whereRaw('attachment_name '.$operator.' ?', ['\\.(jpe?g|png|webp)$']);
        }
        if (! empty($v['before'])) {
            $q->where('id', '<', $v['before']);
        }
        $rows = $q->orderByDesc('id')->limit(31)->get();
        $hasMore = $rows->count() > 30;
        $rows = $rows->take(30);
        $items = $rows->map(function ($m) use ($v) {
            preg_match_all('~https?://[^\s<>"\x{0000}-\x{001F}]+~u', $m->body ?? '', $matches);
            $links = array_map(fn ($url) => rtrim($url, '.,;!?)]}'), $matches[0]);

            return ['id' => $m->id, 'sender_name' => $m->sender_name, 'created_at' => $m->created_at,
                'attachment_name' => $m->attachment_name,
                'attachment_url' => $m->attachment_path ? '/communication/api/attachments/'.$m->id : null,
                'links' => $v['kind'] === 'links' ? array_values(array_unique(array_filter($links, fn ($url) => filter_var($url, FILTER_VALIDATE_URL)))) : []];
        })->values();
        $members = DB::table('communication_members as cm')->join('users as u', 'u.id', '=', 'cm.user_id')
            ->leftJoin('communication_companies as co', 'co.id', '=', 'u.company_id')
            ->where('cm.conversation_id', $id)->orderBy('u.name')->get(['u.id', 'u.name', 'u.email', 'co.name as company_name']);
        if ($this->isAdmin($r)) {
            $this->audit($r, 'shared_content_view', $id);
        }

        return response()->json(['items' => $items, 'members' => $members, 'has_more' => $hasMore, 'before' => $rows->last()?->id, 'company_name' => $c->company_id ? DB::table('communication_companies')->where('id', $c->company_id)->value('name') : null]);
    }

    public function createConversation(Request $r)
    {
        $this->mutable($r);
        $v = $r->validate(['kind' => ['required', Rule::in(['direct', 'group'])], 'name' => 'nullable|string|max:100', 'company_id' => 'nullable|integer|exists:communication_companies,id', 'member_ids' => 'present|array|max:100', 'member_ids.*' => 'integer|distinct|exists:users,id']);
        $admin = $this->isAdmin($r);
        $ids = array_unique(array_merge($v['member_ids'], $admin ? [] : [Auth::id()]));
        abort_unless($v['kind'] !== 'direct' || (! $admin && count($ids) === 2), 422, 'Choose one contact for a personal chat.');
        abort_unless($v['kind'] !== 'group' || (count($ids) >= ($admin ? 1 : 2) && ! empty($v['name'])), 422, 'Select members and a group name.');
        $company = $v['kind'] === 'group' ? ($v['company_id'] ?? Access::company()) : null;
        if ($v['kind'] === 'group') {
            abort_unless($company, 422, 'Every group must belong to a company.');
            if (! $admin) {
                abort_unless((int) $company === Access::company(), 403);
            }
        }
        $eligible = $admin ? null : Access::eligible((int) Auth::id());
        foreach ($ids as $uid) {
            abort_unless(User::where('id', $uid)->where('is_active', true)->exists() && DB::table('user_product_access')->where('user_id', $uid)->where('product_slug', 'communication')->exists(), 422, 'Every participant needs active Communication access.');
            if (! $admin) {
                abort_unless(in_array((int) $uid, $eligible, true), 403, 'Contacts must share a community or group with you.');
            }
        }
        sort($ids);
        $key = $v['kind'] === 'direct' ? implode(':', $ids) : null;
        $id = DB::transaction(function () use ($v, $ids, $key, $admin, $company) {
            if ($key && ($existing = DB::table('communication_conversations')->where('direct_key', $key)->value('id'))) {
                return $existing;
            }
            $id = DB::table('communication_conversations')->insertGetId(['kind' => $v['kind'], 'name' => $v['name'] ?? null, 'company_id' => $company, 'direct_key' => $key, 'created_by' => $admin ? null : Auth::id(), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($ids as $uid) {
                DB::table('communication_members')->insert(['conversation_id' => $id, 'user_id' => $uid]);
            }

            return $id;
        });

        return response()->json(['id' => $id]);
    }

    public function send(Request $r, int $id)
    {
        $this->mutable($r);
        $c = $this->conversation($r, $id);
        abort_unless($c->kind !== 'community' || $this->isAdmin($r), 403, 'Only Super Admin can publish community announcements.');
        $v = $r->validate(['body' => 'nullable|string|max:10000', 'reply_to' => 'nullable|integer', 'attachment' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,webp,pdf,txt,csv,doc,docx,xls,xlsx,zip']);
        abort_unless(trim($v['body'] ?? '') !== '' || $r->hasFile('attachment'), 422, 'Enter a message or attach a file.');
        if (! empty($v['reply_to'])) {
            abort_unless(DB::table('communication_messages')->where('id', $v['reply_to'])->where('conversation_id', $id)->whereNull('deleted_at')->exists(), 422);
        }
        $file = $r->file('attachment');
        $path = $file ? $file->store('communication', 'local') : null;
        try {
            $message = DB::transaction(function () use ($r, $v, $id, $path, $file) {
                $mid = DB::table('communication_messages')->insertGetId(['conversation_id' => $id, 'sender_id' => $this->isAdmin($r) ? null : Auth::id(), 'sender_name' => $this->isAdmin($r) ? 'Super Admin' : Auth::user()->name, 'body' => $v['body'] ?? null, 'reply_to' => $v['reply_to'] ?? null, 'attachment_path' => $path, 'attachment_name' => $file ? $file->getClientOriginalName() : null, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('communication_conversations')->where('id', $id)->update(['updated_at' => now()]);

                return $mid;
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            } throw $e;
        }

        $saved = DB::table('communication_messages')->where('id', $message)->first();
        $saved->attachment_url = $saved->attachment_path ? '/communication/api/attachments/'.$saved->id : null;
        unset($saved->attachment_path);
        $saved->reactions = [];

        return response()->json(['id' => $message, 'message' => $saved], 201);
    }

    public function updateMessage(Request $r, int $id)
    {
        $this->mutable($r);
        $m = DB::table('communication_messages')->where('id', $id)->first();
        abort_unless($m, 404);
        $c = $this->conversation($r, $m->conversation_id);
        abort_unless($c->kind !== 'community' || $this->isAdmin($r), 403);
        abort_unless(! $m->deleted_at && ($this->isAdmin($r) ? $m->sender_id === null && $m->sender_name === 'Super Admin' : $m->sender_id === Auth::id()), 403);
        $v = $r->validate(['body' => 'required|string|max:10000']);
        abort_if(trim($v['body']) === '', 422);
        DB::table('communication_messages')->where('id', $id)->update(['body' => $v['body'], 'edited_at' => now(), 'updated_at' => now()]);

        return response()->json(['message' => 'Message updated.']);
    }

    public function deleteMessage(Request $r, int $id)
    {
        $this->mutable($r);
        $m = DB::table('communication_messages')->where('id', $id)->first();
        abort_unless($m, 404);
        $this->conversation($r, $m->conversation_id);
        abort_unless($this->isAdmin($r), 403, 'Only Super Admin can delete messages.');
        DB::table('communication_messages')->where('id', $id)->update(['body' => null, 'deleted_at' => now(), 'updated_at' => now()]);
        if ($m->attachment_path) {
            Storage::disk('local')->delete($m->attachment_path);
        }

        return response()->json(['message' => 'Message deleted.']);
    }

    public function attachment(Request $r, int $id)
    {
        $m = DB::table('communication_messages')->where('id', $id)->whereNull('deleted_at')->first();
        abort_unless($m && $m->attachment_path && Storage::disk('local')->exists($m->attachment_path), 404);
        $this->conversation($r, $m->conversation_id);
        if ($this->isAdmin($r)) {
            $this->audit($r, 'attachment_download', $m->conversation_id);
        }

        if ($r->boolean('inline') && in_array(Storage::disk('local')->mimeType($m->attachment_path), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return response()->file(Storage::disk('local')->path($m->attachment_path), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
        }

        return Storage::disk('local')->download($m->attachment_path, $m->attachment_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function react(Request $r, int $id)
    {
        $this->mutable($r);
        abort_unless(Auth::check() && ! $this->isAdmin($r), 403);
        $m = DB::table('communication_messages')->where('id', $id)->whereNull('deleted_at')->first();
        abort_unless($m, 404);
        $this->conversation($r, $m->conversation_id);
        $v = $r->validate(['emoji' => ['required', Rule::in(['👍', '❤️', '😂', '😮', '🙏'])]]);
        $q = DB::table('communication_reactions')->where('message_id', $id)->where('user_id', Auth::id());
        if ($q->value('emoji') === $v['emoji']) {
            $q->delete();
        } else {
            DB::table('communication_reactions')->updateOrInsert(['message_id' => $id, 'user_id' => Auth::id()], ['emoji' => $v['emoji']]);
        }

        return response()->json(['message' => 'Reaction updated.']);
    }

    public function audits(Request $r)
    {
        $this->authorize($r);
        abort_unless($this->isAdmin($r), 403);

        return response()->json(DB::table('communication_audits as a')->leftJoin('users as u', 'u.id', '=', 'a.viewed_user_id')->orderByDesc('a.id')->limit(100)->get(['a.*', 'u.name as employee_name']));
    }
}
