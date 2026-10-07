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
        abort_if(Access::super($r), 403, 'Calling is available in employee accounts.');

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
        DB::table('communication_calls')->where('status', 'ringing')->where('created_at', '<', now()->subSeconds(60))->update(['status' => 'missed', 'ended_at' => now(), 'updated_at' => now()]);
        DB::table('communication_calls')->whereIn('status', ['connecting', 'active'])->where(fn ($q) => $q->where('caller_seen_at', '<', now()->subSeconds(90))->orWhere('callee_seen_at', '<', now()->subSeconds(90)))->update(['status' => 'failed', 'ended_at' => now(), 'updated_at' => now()]);
    }

    private function dto($c): array
    {
        $names = User::whereIn('id', [$c->caller_id, $c->callee_id])->pluck('name', 'id');

        return ['id' => $c->id, 'conversation_id' => (int) $c->conversation_id, 'caller_id' => (int) $c->caller_id, 'callee_id' => (int) $c->callee_id, 'caller_name' => $names[$c->caller_id] ?? 'Employee', 'callee_name' => $names[$c->callee_id] ?? 'Employee', 'type' => $c->type, 'status' => $c->status, 'created_at' => $c->created_at, 'answered_at' => $c->answered_at, 'ended_at' => $c->ended_at];
    }

    private function notify($c, string $kind, array $data = []): void
    {
        foreach ([$c->caller_id, $c->callee_id] as $uid) {
            event(new CommunicationCallSignal((int) $uid, ['kind' => $kind, 'call' => $this->dto($c)] + $data));
        }
    }

    public function index(Request $r)
    {
        $uid = $this->actor($r);
        self::expire();
        $r->validate(['page' => 'nullable|integer|min:1']);
        $q = DB::table('communication_calls')->where(fn ($q) => $q->where('caller_id', $uid)->orWhere('callee_id', $uid));
        $page = $q->orderByDesc('created_at')->paginate(30);

        return response()->json(['calls' => collect($page->items())->map(fn ($c) => $this->dto($c)), 'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null])->header('Cache-Control', 'private, no-store');
    }

    public function state(Request $r)
    {
        $uid = $this->actor($r);
        self::expire();
        $c = DB::table('communication_calls')->whereIn('status', self::LIVE)->where(fn ($q) => $q->where('caller_id', $uid)->orWhere('callee_id', $uid))->first();

        return response()->json(['call' => $c ? $this->dto($c) : null, 'offer' => $c && $c->callee_id == $uid && $c->status === 'ringing' ? Cache::get('communication.call.offer.'.$c->id) : null])->header('Cache-Control', 'private, no-store');
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
        abort_unless(count($ids) === 2 && in_array($uid, $ids), 403);
        $peer = current(array_diff($ids, [$uid]));
        abort_unless(User::where('id', $peer)->where('is_active', true)->exists() && in_array($peer, Access::eligible($uid)), 403);
        self::expire();
        $id = DB::transaction(function () use ($uid, $peer, $v) {
            User::whereIn('id', [$uid, $peer])->orderBy('id')->lockForUpdate()->get();
            $busy = DB::table('communication_calls')->whereIn('status', self::LIVE)->where(fn ($q) => $q->whereIn('caller_id', [$uid, $peer])->orWhereIn('callee_id', [$uid, $peer]))->exists();
            abort_if($busy, 409, 'You or this person are already in a call.');
            $id = (string) Str::uuid();
            DB::table('communication_calls')->insert(['id' => $id, 'conversation_id' => $v['conversation_id'], 'caller_id' => $uid, 'callee_id' => $peer, 'type' => $v['type'], 'status' => 'ringing', 'caller_seen_at' => now(), 'callee_seen_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

            return $id;
        });
        Cache::put('communication.call.offer.'.$id, $v['offer'], 90);
        $c = DB::table('communication_calls')->where('id', $id)->first();
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
        $uid = (int) $r->user()->id;
        $v = $r->validate(['kind' => 'required|in:answer,candidate,connected,heartbeat', 'answer' => 'nullable|array:type,sdp', 'answer.type' => 'required_if:kind,answer|in:answer', 'answer.sdp' => 'required_if:kind,answer|string|max:60000', 'candidate' => 'nullable|array:candidate,sdpMid,sdpMLineIndex,usernameFragment', 'candidate.candidate' => 'required_if:kind,candidate|string|max:4000', 'candidate.sdpMid' => 'nullable|string|max:100', 'candidate.sdpMLineIndex' => 'nullable|integer|min:0|max:100', 'candidate.usernameFragment' => 'nullable|string|max:256']);
        $kind = $v['kind'];
        abort_unless(in_array($c->status, self::LIVE), 409, 'This call has ended.');
        if ($kind === 'answer') {
            abort_unless($c->callee_id == $uid, 403);
            $changed = DB::table('communication_calls')->where('id', $id)->where('status', 'ringing')->where('created_at', '>', now()->subSeconds(60))->update(['status' => 'connecting', 'caller_seen_at' => now(), 'callee_seen_at' => now(), 'updated_at' => now()]);
            abort_unless($changed, 409, 'Call already answered or expired.');
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
                abort_unless($c->callee_id == $r->user()->id && $c->status === 'ringing', 403);
            }
            if (in_array($status, ['cancelled', 'missed'])) {
                abort_unless($c->caller_id == $r->user()->id && $c->status === 'ringing', 403);
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
