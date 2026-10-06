<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CommunicationAccess
{
    public static function super(Request $r): bool
    {
        return (bool) $r->session()->get('judibas_admin');
    }

    public static function authorize(Request $r): void
    {
        if (self::super($r)) {
            return;
        }
        abort_unless(Auth::check() && Auth::user()->is_active && DB::table('user_product_access')->where('user_id', Auth::id())->where('product_slug', 'communication')->exists(), 403, 'Communication access is required.');
    }

    public static function company(?int $uid = null): ?int
    {
        return $uid || Auth::id() ? DB::table('users')->where('id', $uid ?: Auth::id())->value('company_id') : null;
    }

    public static function manager(Request $r): bool
    {
        return self::super($r) || (Auth::check() && Auth::user()->is_active && (bool) Auth::user()->communication_admin && DB::table('user_product_access')->where('user_id', Auth::id())->where('product_slug', 'communication')->exists());
    }

    public static function manageCompany(Request $r, int $company): void
    {
        self::authorize($r);
        abort_unless(self::super($r) || (self::manager($r) && self::company() === $company), 403, 'You can manage only your assigned company.');
    }

    public static function eligible(int $uid): array
    {
        $company = self::company($uid);
        $groups = DB::table('communication_members as m')->join('communication_conversations as c', 'c.id', '=', 'm.conversation_id')->where('m.user_id', $uid)->where('c.kind', 'group')->pluck('c.id');
        $peers = DB::table('communication_members')->whereIn('conversation_id', $groups)->pluck('user_id');

        return User::where('is_active', true)->whereIn('id', DB::table('user_product_access')->where('product_slug', 'communication')->select('user_id'))->where(function ($q) use ($company, $peers) {
            $q->where('company_id', $company)->orWhereIn('id', $peers);
        })->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public static function mutable(Request $r): void
    {
        self::authorize($r);
        abort_if($r->filled('view_user'), 403, 'Employee review is read-only.');
    }

    public static function subject(Request $r): ?int
    {
        self::authorize($r);
        if ($r->filled('view_user')) {
            abort_unless(self::super($r), 403);
            $v = $r->validate(['view_user' => 'required|integer|exists:users,id']);

            return (int) $v['view_user'];
        }

return self::super($r) ? null : (int) Auth::id();
    }

    public static function scope($query, ?int $uid): void
    {
        if ($uid === null) {
            return;
        }
        $eligible = self::eligible($uid);
        $query->where(function ($q) use ($uid, $eligible) {
            $q->where(function ($q) use ($uid, $eligible) {
                $q->whereIn('c.kind', ['group', 'direct'])->whereExists(function ($m) use ($uid) {
                    $m->selectRaw('1')->from('communication_members as cm')->whereColumn('cm.conversation_id', 'c.id')->where('cm.user_id', $uid);
                });
                $q->where(function ($d) use ($uid, $eligible) {
                    $d->where('c.kind', 'group')->orWhereExists(function ($m) use ($uid, $eligible) {
                        $m->selectRaw('1')->from('communication_members as peer')->whereColumn('peer.conversation_id', 'c.id')->where('peer.user_id', '!=', $uid)->whereIn('peer.user_id', $eligible);
                    });
                });
            })->orWhere(function ($q) use ($uid) {
                $q->where('c.kind', 'community')->where('c.company_id', self::company($uid));
            });
        });
    }

    public static function conversation(Request $r, int $id)
    {
        $q = DB::table('communication_conversations as c')->where('c.id', $id);
        self::scope($q, self::subject($r));
        $c = $q->first();
        abort_unless($c, 403, 'You cannot access this conversation.');

        return $c;
    }

    public static function community(int $company): int
    {
        $id = DB::table('communication_conversations')->where('kind', 'community')->where('company_id', $company)->value('id');
        if ($id) {
            return (int) $id;
        }

        return DB::table('communication_conversations')->insertGetId(['kind' => 'community', 'company_id' => $company, 'name' => DB::table('communication_companies')->where('id',$company)->value('name').' announcements', 'created_at' => now(), 'updated_at' => now()]);
    }
}
