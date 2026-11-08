<?php

namespace App\Http\Controllers;

use App\Events\CommunicationEncryptedChanged;
use App\Models\User;
use App\Services\CommunicationAccess as Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommunicationEncryptedController extends Controller
{
    private function principal(Request $r): string
    {
        Access::authorize($r);
        abort_if($r->filled('view_user') || $r->input('mode') === 'review', 403, 'Encrypted conversations cannot be reviewed.');

        return Access::super($r) ? 'super' : 'user:'.$r->user()->id;
    }

    private function device(Request $r): object
    {
        $principal = $this->principal($r);
        $device = DB::table('communication_e2ee_devices')->where('principal', $principal)->first();
        abort_unless($device, 409, 'Set up your encrypted-chat device first.');

        return $device;
    }

    private function thread(Request $r, string $id): object
    {
        $device = $this->device($r);
        $thread = DB::table('communication_e2ee_threads')->where('id', $id)->where(fn ($q) => $q->where('owner_device_id', $device->id)->orWhere('peer_device_id', $device->id))->first();
        abort_unless($thread, 404);
        abort_unless(User::where('id', $thread->peer_user_id)->where('is_active', true)->whereIn('id', DB::table('user_product_access')->where('product_slug', 'communication')->select('user_id'))->exists(), 403, 'The participant no longer has Communication access.');

        abort_unless($thread->peer_device_id, 409, 'The invited participant must register their device before messages or calls can be sent.');

        return $thread;
    }

    private function result($payload, int $status = 200)
    {
        return response()->json($payload, $status)->header('Cache-Control', 'private, no-store');
    }

    public function index(Request $r)
    {
        $principal = $this->principal($r);
        $device = DB::table('communication_e2ee_devices')->where('principal', $principal)->first();
        $threads = DB::table('communication_e2ee_threads as t')->join('users as u', 'u.id', '=', 't.peer_user_id')->where(function ($q) use ($r, $device) {
            if (Access::super($r)) {
                $device ? $q->where('t.owner_device_id', $device->id) : $q->whereRaw('1=0');
            } else {
                $q->where('t.peer_user_id', $r->user()->id);
            }
        })->orderByDesc('t.updated_at')->get(['t.*', 'u.name as peer_name']);
        $deviceIds = $threads->flatMap(fn ($thread) => [$thread->owner_device_id, $thread->peer_device_id])->filter()->unique()->values()->all();
        $devices = DB::table('communication_e2ee_devices')->whereIn('id', $deviceIds)->get()->keyBy('id');
        foreach ($threads as $thread) {
            $thread->devices = collect([$devices->get($thread->owner_device_id), $devices->get($thread->peer_device_id)])->filter()->values();
        }
        $people = Access::super($r) ? DB::table('users as u')->leftJoin('communication_e2ee_devices as d', 'd.principal', '=', DB::raw("'user:' || u.id::text"))->where('u.is_active', true)->whereIn('u.id', DB::table('user_product_access')->where('product_slug', 'communication')->select('user_id'))->orderBy('u.name')->get(['u.id', 'u.name', 'd.id as device_id', 'd.public_key']) : collect();

        return $this->result(['principal' => $principal, 'can_create' => Access::super($r), 'device' => $device, 'threads' => $threads, 'people' => $people, 'calling_enabled' => (bool) config('communication_calls.enabled')]);
    }

    public function register(Request $r)
    {
        $principal = $this->principal($r);
        abort_if(array_diff(array_keys($r->all()), ['id', 'public_key', '_token']), 422, 'Only the public device key may be registered.');
        $v = $r->validate(['id' => 'required|uuid', 'public_key' => 'required|string|max:100']);
        $raw = base64_decode($v['public_key'], true);
        $pem = $raw ? '-----BEGIN PUBLIC KEY-----'.PHP_EOL.chunk_split(base64_encode(hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').$raw), 64, PHP_EOL).'-----END PUBLIC KEY-----' : null;
        $key = $pem ? @openssl_pkey_get_public($pem) : false;
        $details = $key ? openssl_pkey_get_details($key) : [];
        if (! $raw || strlen($raw) !== 65 || ord($raw[0]) !== 4 || ($details['ec']['curve_name'] ?? '') !== 'prime256v1') {
            throw ValidationException::withMessages(['public_key' => 'A valid P-256 public device key is required.']);
        }
        $device = DB::transaction(function () use ($principal, $v) {
            DB::table('communication_e2ee_devices')->insertOrIgnore($v + ['principal' => $principal, 'created_at' => now()]);
            $row = DB::table('communication_e2ee_devices')->where('principal', $principal)->first();
            abort_unless($row && $row->id === $v['id'] && hash_equals($row->public_key, $v['public_key']), 409, 'This account already has an encrypted-chat device. Use its original browser profile. Device keys cannot be overwritten.');

            return $row;
        });

        if (! Access::super($r)) {
            DB::table('communication_e2ee_threads')->where('peer_user_id', $r->user()->id)->whereNull('peer_device_id')->update(['peer_device_id' => $device->id, 'updated_at' => now()]);
            foreach (DB::table('communication_e2ee_threads')->where('peer_user_id', $r->user()->id)->get() as $thread) {
                $this->signal($thread);
            }
        }

        return $this->result(['device' => $device]);
    }

    public function create(Request $r)
    {
        $principal = $this->principal($r);
        abort_unless(Access::super($r), 403, 'Only Super Admin can create encrypted conversations.');
        $owner = $this->device($r);
        $v = $r->validate(['peer_user_id' => 'required|integer|exists:users,id', 'peer_device_id' => 'nullable|uuid']);
        $user = User::where('id', $v['peer_user_id'])->where('is_active', true)->whereIn('id', DB::table('user_product_access')->where('product_slug', 'communication')->select('user_id'))->first();
        abort_unless($user, 422, 'Select an active Communication participant.');
        $peer = DB::table('communication_e2ee_devices')->where('principal', 'user:'.$user->id)->first();
        if (! empty($v['peer_device_id'])) {
            abort_unless($peer && $peer->id === $v['peer_device_id'], 409, 'The recipient device changed; refresh before creating the conversation.');
        }
        $thread = DB::transaction(function () use ($owner, $peer, $user) {
            DB::table('communication_e2ee_threads')->insertOrIgnore(['id' => (string) Str::uuid(), 'owner_device_id' => $owner->id, 'peer_device_id' => $peer?->id, 'peer_user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

            return DB::table('communication_e2ee_threads')->where('owner_device_id', $owner->id)->where('peer_user_id', $user->id)->first();
        });
        $this->signal($thread);

        return $this->result(['id' => $thread->id]);
    }

    public function messages(Request $r, string $id)
    {
        $thread = $this->thread($r, $id);
        $r->validate(['before' => 'nullable|uuid']);
        $q = DB::table('communication_e2ee_messages')->where('thread_id', $id);
        if ($r->filled('before')) {
            $anchor = DB::table('communication_e2ee_messages')->where('thread_id', $id)->where('id', $r->input('before'))->first();
            abort_unless($anchor, 404);
            $q->where('position', '<', $anchor->position);
        }$rows = $q->orderByDesc('position')->limit(26)->get();
        $more = $rows->count() > 25;
        $rows = $rows->take(25)->reverse()->values();
        foreach ($rows as $row) {
            $row->envelopes = json_decode($row->envelopes, true);
        }

        return $this->result(['messages' => $rows, 'more' => $more]);
    }

    public function send(Request $r, string $id)
    {
        $thread = $this->thread($r, $id);
        $device = $this->device($r);
        $v = $this->validateEnvelopes($r, $thread);
        DB::transaction(function () use ($thread, $device, $v) {
            $existing = DB::table('communication_e2ee_messages')->where('id', $v['id'])->first();
            if ($existing) {
                abort_unless($existing->thread_id === $thread->id && $existing->sender_device_id === $device->id && json_decode($existing->envelopes, true) == $v['envelopes'], 409, 'Message identifier already used.');

                return;
            }DB::table('communication_e2ee_messages')->insert(['id' => $v['id'], 'thread_id' => $thread->id, 'sender_device_id' => $device->id, 'envelopes' => json_encode($v['envelopes']), 'created_at' => now()]);
            DB::table('communication_e2ee_threads')->where('id', $thread->id)->update(['updated_at' => now()]);
        });
        $this->signal($thread);

        return $this->result(['id' => $v['id']]);
    }

    private function validateEnvelopes(Request $r, object $thread): array
    {
        $v = $r->validate(['id' => 'required|uuid', 'envelopes' => 'required|array|size:2', 'envelopes.*' => 'required|array:device_id,enc,ciphertext', 'envelopes.*.device_id' => 'required|uuid|distinct', 'envelopes.*.enc' => 'required|string|max:100', 'envelopes.*.ciphertext' => 'required|string|max:2000000']);
        abort_if(array_diff(array_keys($r->all()), ['id', 'envelopes', '_token']), 422, 'Encrypted messages accept ciphertext only.');
        $expected = [$thread->owner_device_id, $thread->peer_device_id];
        $actual = array_column($v['envelopes'], 'device_id');
        sort($expected);
        sort($actual);
        abort_unless($expected === $actual, 422, 'An encrypted envelope is required for each participant device.');
        foreach ($v['envelopes'] as $env) {
            $enc = base64_decode($env['enc'], true);
            $ct = base64_decode($env['ciphertext'], true);
            abort_unless($enc && strlen($enc) === 65 && ord($enc[0]) === 4 && $ct && strlen($ct) >= 16, 422, 'Invalid HPKE envelope.');
        }

        return $v;
    }

    public function sendSignal(Request $r, string $id)
    {
        $thread = $this->thread($r, $id);
        $device = $this->device($r);
        abort_unless(config('communication_calls.enabled'), 404);
        $v = $this->validateEnvelopes($r, $thread);
        foreach ($v['envelopes'] as $envelope) {
            abort_if(strlen($envelope['ciphertext']) > 150000, 422, 'Encrypted call signal is too large.');
        }
        DB::table('communication_e2ee_signals')->where('expires_at', '<', now())->delete();
        DB::transaction(function () use ($thread, $device, $v) {
            $old = DB::table('communication_e2ee_signals')->where('id', $v['id'])->first();
            if ($old) {
                abort_unless($old->thread_id === $thread->id && $old->sender_device_id === $device->id && json_decode($old->envelopes, true) == $v['envelopes'], 409);

                return;
            }DB::table('communication_e2ee_signals')->insert(['id' => $v['id'], 'thread_id' => $thread->id, 'sender_device_id' => $device->id, 'envelopes' => json_encode($v['envelopes']), 'expires_at' => now()->addSeconds(90), 'created_at' => now()]);
        });
        try {
            event(new CommunicationEncryptedChanged($thread->peer_user_id, $thread->id, $v['id']));
        } catch (\Throwable) {
            report(new \RuntimeException('Encrypted call signal could not be broadcast.'));
        }

        return $this->result(['id' => $v['id']]);
    }

    public function getSignal(Request $r, string $id, string $signal)
    {
        $this->thread($r, $id);
        $row = DB::table('communication_e2ee_signals')->where('thread_id', $id)->where('id', $signal)->where('expires_at', '>', now())->first();
        abort_unless($row, 404);
        $row->envelopes = json_decode($row->envelopes, true);

        return $this->result(['signal' => $row]);
    }

    private function signal(object $thread): void
    {
        try {
            event(new CommunicationEncryptedChanged($thread->peer_user_id, $thread->id));
        } catch (\Throwable) {
            report(new \RuntimeException('Encrypted chat update could not be broadcast.'));
        }
    }
}
