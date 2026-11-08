<?php

namespace App\Http\Controllers;

use App\Events\CommunicationCallSignal;
use App\Models\User;
use App\Services\CommunicationAccess as Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CommunicationCallController extends Controller
{
    const LIVE = ['ringing', 'connecting', 'active'];

    private function actor(Request $r): int
    {
        abort_unless(config('communication_calls.enabled'), 404);
        Access::mutable($r);
        if (Access::super($r)) {
            abort_unless(Access::personal($r), 403, 'Switch to My chats to make or answer calls.');
            return 0;
        }

        return (int) $r->user()->id;
    }

    private function call(Request $r, string $id)
    {
        $uid = $this->actor($r);
        $c = DB::table('communication_calls')->where('id', $id)->first();
        abort_unless($c && in_array($uid, [(int) $c->caller_id, (int) $c->callee_id]), 403);
        Access::conversation($r, $c->conversation_id);

        return $c;
    }

    public static function expire(): void
    {
        $expired = DB::table('communication_calls')->where(fn ($q) => $q->where(fn ($q) => $q->where('status', 'ringing')->where('created_at', '<', now()->subSeconds(60)))->orWhere(fn ($q) => $q->whereIn('status', ['connecting', 'active'])->where(fn ($q) => $q->where('caller_seen_at', '<', now()->subSeconds(90))->orWhere('callee_seen_at', '<', now()->subSeconds(90)))))->limit(200)->get();
        foreach ($expired as $c) {
            $changed = DB::table('communication_calls')->where('id', $c->id)->where('status', $c->status)->where('updated_at', $c->updated_at)->where('caller_seen_at', $c->caller_seen_at)->where('callee_seen_at', $c->callee_seen_at)->update(['status' => $c->status === 'ringing' ? 'missed' : 'failed', 'ended_at' => now(), 'updated_at' => now()]);
            if ($changed) {
                Cache::forget('communication.call.offer.'.$c->id);
                if (config('communication_calls.enabled')) {
                    try {
                        (new self)->notify(DB::table('communication_calls')->where('id', $c->id)->first(), 'finished');
                    } catch (\Throwable $e) {
                    }
                }
            }
        }
    }

    private function dto($c, ?array $names = null): array
    {
        $names ??= User::whereIn('id', [$c->caller_id, $c->callee_id])->pluck('name', 'id')->all();

        return ['id' => $c->id, 'conversation_id' => (int) $c->conversation_id, 'caller_id' => (int) $c->caller_id, 'callee_id' => (int) $c->callee_id, 'caller_name' => $c->caller_id === null ? 'Super Admin' : ($names[$c->caller_id] ?? 'Employee'), 'callee_name' => $c->callee_id === null ? 'Super Admin' : ($names[$c->callee_id] ?? 'Employee'), 'waiting' => $c->status === 'ringing' && DB::table('communication_calls')->where('id', '!=', $c->id)->whereIn('status', ['connecting', 'active'])->where(fn ($q) => $q->where('caller_id', $c->callee_id)->orWhere('callee_id', $c->callee_id))->exists(), 'type' => $c->type, 'status' => $c->status, 'created_at' => $c->created_at, 'answered_at' => $c->answered_at, 'ended_at' => $c->ended_at, 'unread' => ! $c->callee_read_at && in_array($c->status, ['missed', 'cancelled', 'busy'])];
    }

    private function notify($c, string $kind, array $data = []): void
    {
        if (in_array($kind, ['incoming', 'connected', 'finished'])) {
            try { event(new \App\Events\CommunicationChanged([], 'calls', (int) $c->conversation_id)); } catch (\Throwable $e) {}
        }
        foreach ([$c->caller_id, $c->callee_id] as $uid) {
            event(new CommunicationCallSignal((int) $uid, ['kind' => $kind, 'call' => $this->dto($c)] + $data));
        }
    }

    public function index(Request $r)
    {
        abort_unless(config('communication_calls.enabled'), 404);
        $uid = Access::personal($r) ? 0 : Access::subject($r);
        $review = Access::super($r) && !Access::personal($r);
        self::expire();
        $r->validate(['page' => 'nullable|integer|min:1', 'conversation_id' => 'nullable|integer']);
        if ($r->filled('conversation_id')) {
            Access::conversation($r, $r->integer('conversation_id'));
        }
        $q = DB::table('communication_calls')->when($uid !== null, fn ($q) => $q->where(fn ($q) => $q->where('caller_id', $uid ?: null)->orWhere('callee_id', $uid ?: null)));
        if ($review) {
            DB::table('communication_audits')->insert(['admin_email' => config('judibas.admin_email'), 'viewed_user_id' => $uid, 'conversation_id' => $r->filled('conversation_id') ? $r->integer('conversation_id') : null, 'action' => 'call_history_review', 'created_at' => now()]);
        }
        $q->when($r->filled('conversation_id'), fn ($q) => $q->where('conversation_id', $r->integer('conversation_id')));
        $unread = $review ? 0 : DB::table('communication_calls')->where('callee_id', $uid ?: null)->whereNull('callee_read_at')->whereIn('status', ['missed', 'cancelled', 'busy'])->count();
        $page = $q->orderByDesc('created_at')->orderByDesc('id')->paginate(30);

        $names = User::whereIn('id', collect($page->items())->flatMap(fn ($c) => [$c->caller_id, $c->callee_id])->unique())->pluck('name', 'id')->all();

        return response()->json(['calls' => collect($page->items())->map(fn ($c) => $this->dto($c, $names)), 'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null, 'missed_unread' => $unread])->header('Cache-Control', 'private, no-store');
    }

    public function read(Request $r)
    {
        $uid = $this->actor($r);
        $v = $r->validate(['ids' => 'required|array|max:30', 'ids.*' => 'required|uuid']);
        $rows = DB::table('communication_calls')->whereIn('id', $v['ids'])->where('callee_id', $uid ?: null)->whereNull('callee_read_at')->whereIn('status', ['missed', 'cancelled', 'busy'])->get();
        DB::table('communication_calls')->whereIn('id', $rows->pluck('id'))->update(['callee_read_at' => now()]);
        foreach ($rows as $row) {
            try {
                event(new CommunicationCallSignal($uid, ['kind' => 'history_read', 'call' => $this->dto(DB::table('communication_calls')->where('id', $row->id)->first())]));
            } catch (\Throwable $e) {
            }
        }

        return response()->json(['ok' => true]);
    }

    public function state(Request $r)
    {
        $uid = $this->actor($r);
        self::expire();
        $c = DB::table('communication_calls')->whereIn('status', self::LIVE)->where(fn ($q) => $q->where('caller_id', $uid ?: null)->orWhere('callee_id', $uid ?: null))->orderByRaw("CASE WHEN status IN ('active', 'connecting') THEN 0 ELSE 1 END")->first();

        $waiting = $c ? DB::table('communication_calls')->where('id', '!=', $c->id)->where('callee_id', $uid ?: null)->where('status', 'ringing')->first() : null;
        return response()->json(['call' => $c ? $this->dto($c) : null, 'offer' => $c && $c->callee_id == $uid && $c->status === 'ringing' ? Cache::get('communication.call.offer.'.$c->id) : null, 'waiting' => $waiting ? ['call' => $this->dto($waiting), 'offer' => Cache::get('communication.call.offer.'.$waiting->id)] : null])->header('Cache-Control', 'private, no-store');
    }

    public function ice(Request $r)
    {
        $uid = $this->actor($r);
        $key = config('communication_calls.turn_key_id');
        $token = config('communication_calls.turn_api_token');
        abort_if((bool) $key !== (bool) $token, 503, 'Configure both the Cloudflare TURN key ID and API token.');
        if (! $key || ! $token) {
            return response()->json(['iceServers' => [['urls' => ['stun:stun.cloudflare.com:3478']]], 'relay' => false])->header('Cache-Control', 'private, no-store');
        }
        try {
            $servers = Cache::remember('communication.turn.'.hash('sha256', $key.$token).'.'.$uid, 300, fn () => Http::withToken($token)->connectTimeout(3)->timeout(10)->post('https://rtc.live.cloudflare.com/v1/turn/keys/'.rawurlencode($key).'/credentials/generate-ice-servers', ['ttl' => 7200])->throw()->json('iceServers'));
            abort_unless(is_array($servers) && count($servers), 503, 'TURN configuration is unavailable.');
        } catch (\Throwable $e) {
            abort(503, 'Call relay unavailable. Check the Cloudflare TURN settings.');
        }

        return response()->json(['iceServers' => $servers, 'relay' => true])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $r)
    {
        $uid = $this->actor($r);
        $v = $r->validate(['conversation_id' => 'required|integer', 'type' => 'required|in:audio', 'offer' => 'required|array:type,sdp', 'offer.type' => 'required|in:offer', 'offer.sdp' => 'required|string|max:60000']);
        $conversation = Access::conversation($r, $v['conversation_id']);
        abort_unless($conversation->kind === 'direct', 422, 'Start calls from a personal chat.');
        $ids = DB::table('communication_members')->where('conversation_id', $conversation->id)->pluck('user_id')->map(fn ($x) => (int) $x)->all();
        if ($conversation->super_participant) $ids[] = 0;
        abort_unless(count($ids) === 2 && in_array($uid, $ids), 403);
        $peer = current(array_diff($ids, [$uid]));
        if ($peer !== 0) {
            abort_unless(User::where('id', $peer)->where('is_active', true)->exists() && DB::table('user_product_access')->where('user_id', $peer)->where('product_slug', 'communication')->exists() && ($uid === 0 || in_array($peer, Access::eligible($uid))), 403);
        } else abort_unless($conversation->super_participant, 403);
        self::expire();
        $id = DB::transaction(function () use ($uid, $peer, $v) {
            if (($uid === 0 || $peer === 0) && DB::getDriverName() === 'pgsql') DB::select('SELECT pg_advisory_xact_lock(73126, 0)');
            User::whereIn('id', [$uid, $peer])->orderBy('id')->lockForUpdate()->get();
            $callerBusy = DB::table('communication_calls')->whereIn('status', self::LIVE)->where(fn ($q) => $q->where('caller_id', $uid ?: null)->orWhere('callee_id', $uid ?: null))->exists();
            abort_if($callerBusy, 409, 'You are already in a call.');
            $busy = DB::table('communication_calls')->where('status', 'ringing')->where(fn ($q) => $q->where('caller_id', $peer ?: null)->orWhere('callee_id', $peer ?: null))->exists();
            $id = (string) Str::uuid();
            DB::table('communication_calls')->insert(['id' => $id, 'conversation_id' => $v['conversation_id'], 'caller_id' => $uid ?: null, 'callee_id' => $peer ?: null, 'type' => $v['type'], 'status' => $busy ? 'busy' : 'ringing', 'ended_at' => $busy ? now() : null, 'caller_seen_at' => now(), 'callee_seen_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

            return $id;
        });
        $c = DB::table('communication_calls')->where('id', $id)->first();
        if ($c->status === 'busy') {
            try {
                $this->notify($c, 'finished');
            } catch (\Throwable $e) {
            }

            return response()->json(['call' => $this->dto($c)], 201);
        }
        Cache::put('communication.call.offer.'.$id, $v['offer'], 90);
        try {
            $this->notify($c, 'incoming', ['offer' => $v['offer']]);
        } catch (\Throwable $e) {
            DB::table('communication_calls')->where('id', $id)->update(['status' => 'failed', 'ended_at' => now()]);
            Cache::forget('communication.call.offer.'.$id);
            abort(503, 'Calling connection unavailable. Please try again.');
        }

        return response()->json(['call' => $this->dto($c)], 201);
    }

    public function signal(Request $r, string $id)
    {
        $c = $this->call($r, $id);
        $uid = $this->actor($r);
        $v = $r->validate(['kind' => 'required|in:answer,candidate,connected,heartbeat', 'answer' => 'nullable|array:type,sdp', 'answer.type' => 'required_if:kind,answer|in:answer', 'answer.sdp' => 'required_if:kind,answer|string|max:60000', 'candidate' => 'nullable|array:candidate,sdpMid,sdpMLineIndex,usernameFragment', 'candidate.candidate' => 'required_if:kind,candidate|string|max:4000', 'candidate.sdpMid' => 'nullable|string|max:100', 'candidate.sdpMLineIndex' => 'nullable|integer|min:0|max:100', 'candidate.usernameFragment' => 'nullable|string|max:256']);
        $kind = $v['kind'];
        abort_unless(in_array($c->status, self::LIVE), 409, 'This call has ended.');
        if ($kind === 'answer') {
            abort_unless($c->callee_id == $uid, 403);
            $ended = DB::transaction(function () use ($c, $id, $uid) {
                if (($c->caller_id === null || $c->callee_id === null) && DB::getDriverName() === 'pgsql') DB::select('SELECT pg_advisory_xact_lock(73126, 0)');
                User::whereIn('id', [$c->caller_id, $uid])->orderBy('id')->lockForUpdate()->get();
                $changed = DB::table('communication_calls')->where('id', $id)->where('status', 'ringing')->where('created_at', '>', now()->subSeconds(60))->update(['status' => 'connecting', 'caller_seen_at' => now(), 'callee_seen_at' => now(), 'updated_at' => now()]);
                abort_unless($changed, 409, 'Call already answered or expired.');
                $others = DB::table('communication_calls')->where('id', '!=', $id)->whereIn('status', self::LIVE)->where(fn ($q) => $q->where('caller_id', $uid ?: null)->orWhere('callee_id', $uid ?: null))->get();
                foreach ($others as $other) {
                    DB::table('communication_calls')->where('id', $other->id)->whereIn('status', self::LIVE)->update(['status' => $other->status === 'ringing' ? 'missed' : 'ended', 'ended_at' => now(), 'updated_at' => now()]);
                    Cache::forget('communication.call.offer.'.$other->id);
                }
                return $others;
            });
            foreach ($ended as $other) {
                try { $this->notify(DB::table('communication_calls')->where('id', $other->id)->first(), 'finished'); } catch (\Throwable $e) {}
            }
            Cache::forget('communication.call.offer.'.$id);
        } elseif ($kind === 'connected') {
            DB::table('communication_calls')->where('id', $id)->where('status', 'connecting')->update(['status' => 'active', 'answered_at' => now(), 'updated_at' => now()]);
        }
        DB::table('communication_calls')->where('id', $id)->update([$c->caller_id == $uid ? 'caller_seen_at' : 'callee_seen_at' => now()]);
        $c = DB::table('communication_calls')->where('id', $id)->first();
        if ($kind === 'candidate' || $kind === 'answer') {
            event(new CommunicationCallSignal($c->caller_id == $uid ? (int) $c->callee_id : (int) $c->caller_id, ['kind' => $kind, 'call' => $this->dto($c), $kind => $v[$kind]]));
        }
        if ($kind === 'connected') {
            $this->notify($c, 'connected');
        }

        return response()->json(['call' => $this->dto($c)]);
    }

    public function finish(Request $r, string $id)
    {
        $c = $this->call($r, $id);
        $v = $r->validate(['status' => 'required|in:ended,rejected,cancelled,missed,failed']);
        $status = $v['status'];
        if (in_array($c->status, self::LIVE)) {
            if ($status === 'rejected') {
                abort_unless((int)$c->callee_id === $this->actor($r) && $c->status === 'ringing', 403);
            }
            if (in_array($status, ['cancelled', 'missed'])) {
                abort_unless((int)$c->caller_id === $this->actor($r) && $c->status === 'ringing', 403);
            }
            if ($status === 'missed') {
                abort_unless(strtotime($c->created_at) <= now()->subSeconds(55)->timestamp, 422);
            }
            DB::table('communication_calls')->where('id', $id)->whereIn('status', self::LIVE)->update(['status' => $status, 'ended_at' => now(), 'updated_at' => now()]);
            Cache::forget('communication.call.offer.'.$id);
            $c = DB::table('communication_calls')->where('id', $id)->first();
            try {
                $this->notify($c, 'finished');
            } catch (\Throwable $e) {
            }
        }

        return response()->json(['call' => $this->dto($c)]);
    }
}
