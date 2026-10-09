<?php

namespace App\Http\Controllers;

use App\Mail\CommunicationInvitation;
use App\Models\User;
use App\Services\CommunicationAccess as Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommunicationManagementController extends Controller
{
    public function data(Request $r)
    {
        Access::authorize($r);
        abort_unless(Access::manager($r), 403);
        abort_if($r->filled('view_user'), 403);
        $companies = DB::table('communication_companies')->orderBy('name');
        if (! Access::super($r)) {
            $companies->whereIn('id', Access::companies());
        }
        $companies = $companies->get();
        $ids = $companies->pluck('id');
        $peopleQuery=User::query();Access::usersInCompanies($peopleQuery,$ids->all());
        $people = Access::attachCompanies($peopleQuery->orderBy('name')->get(['id', 'name', 'email', 'company_id', 'communication_admin', 'is_active']));
        foreach ($people as $p) {
            $p->communication_access = DB::table('user_product_access')->where('user_id', $p->id)->where('product_slug', 'communication')->exists();
        }

        return response()->json(['super' => Access::super($r), 'companies' => $companies, 'people' => $people,
            'groups' => DB::table('communication_conversations')->where('kind', 'group')->whereIn('company_id', $ids)->orderBy('name')->get()->map(function ($g) {
                $g->member_ids = DB::table('communication_members')->where('conversation_id', $g->id)->pluck('user_id');

                return $g;
            }),
            'invitations' => DB::table('communication_invitations')->whereIn('company_id', $ids)->orderByDesc('id')->limit(100)->get(['id', 'email', 'company_id', 'company_ids', 'communication_admin', 'delivery_status', 'group_ids', 'expires_at', 'accepted_at', 'revoked_at'])->filter(fn($i)=>Access::super($r)||!array_diff($this->invitationCompanies($i),$ids->all()))->values(),
            'mailer' => config('mail.default')]);
    }

    public function company(Request $r, ?int $id = null)
    {
        Access::mutable($r);
        abort_unless(Access::super($r), 403);
        $v = $r->validate(['name' => ['required', 'string', 'max:100', Rule::unique('communication_companies')->ignore($id)]]);
        DB::transaction(function () use ($v, &$id) {
            if ($id) {
                abort_unless(DB::table('communication_companies')->where('id', $id)->exists(), 404);
                DB::table('communication_companies')->where('id', $id)->update($v + ['updated_at' => now()]);
            } else {
                $id = DB::table('communication_companies')->insertGetId($v + ['created_at' => now(), 'updated_at' => now()]);
            }
            Access::community($id);
            DB::table('communication_conversations')->where('company_id', $id)->where('kind', 'community')->update(['name' => $v['name'].' announcements']);
        });

        return response()->json(['id' => $id, 'message' => 'Company community saved.']);
    }

    public function group(Request $r, int $id)
    {
        Access::mutable($r);
        $g = DB::table('communication_conversations')->where('id', $id)->where('kind', 'group')->first();
        abort_unless($g, 404);
        Access::manageCompany($r, $g->company_id);
        $v = $r->validate(['name' => 'required|string|max:100', 'member_ids' => 'required|array|min:1|max:100', 'member_ids.*' => 'integer|distinct|exists:users,id']);
        foreach ($v['member_ids'] as $uid) {
            abort_unless(in_array((int)$g->company_id,Access::companies((int)$uid),true) && User::where('id',$uid)->where('is_active',true)->exists() && DB::table('user_product_access')->where('user_id', $uid)->where('product_slug', 'communication')->exists(), 422, 'Group members must have access to this company.');
        }
        DB::transaction(function () use ($id, $v) {
            DB::table('communication_conversations')->where('id', $id)->update(['name' => $v['name'], 'updated_at' => now()]);
            DB::table('communication_members')->where('conversation_id', $id)->whereNotIn('user_id', $v['member_ids'])->delete();
            foreach ($v['member_ids'] as $uid) {
                DB::table('communication_members')->updateOrInsert(['conversation_id' => $id, 'user_id' => $uid], []);
            }
        });

        return response()->json(['message' => 'Group membership saved.']);
    }

    public function deleteGroup(Request $r, int $id)
    {
        Access::mutable($r);
        abort_unless(Access::super($r), 403);
        $g = DB::table('communication_conversations')->where('id', $id)->where('kind', 'group')->first();
        abort_unless($g, 404);
        $paths = DB::table('communication_messages')->where('conversation_id', $id)->whereNotNull('attachment_path')->pluck('attachment_path')->all();
        DB::table('communication_conversations')->where('id', $id)->delete();
        \App\Services\CommunicationAttachmentStorage::delete($paths);

        return response()->json(['message' => 'Group deleted.']);
    }

    public function announce(Request $r)
    {
        Access::mutable($r);
        abort_unless(Access::super($r), 403);
        $v = $r->validate(['company_ids' => 'required|array|min:1|max:100', 'company_ids.*' => 'required|integer|distinct|exists:communication_companies,id', 'body' => 'nullable|string|max:10000', 'attachment' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,webp,pdf,txt,csv,doc,docx,xls,xlsx,zip,webm,ogg,mp3,wav,m4a,mp4']);
        abort_unless(trim($v['body'] ?? '') !== '' || $r->hasFile('attachment'), 422, 'Write an announcement or attach a file.');
        $paths = [];
        $ids = [];
        try {
            DB::transaction(function () use ($r, $v, &$paths, &$ids) {
                foreach ($v['company_ids'] as $company) {
                    $cid = Access::community($company);
                    $file = $r->file('attachment');
                    $path = $file ? \App\Services\CommunicationAttachmentStorage::store($file, 'communication') : null;
                    if ($path) {
                        $paths[] = $path;
                    }
                    $ids[] = DB::table('communication_messages')->insertGetId(['conversation_id' => $cid, 'sender_id' => null, 'sender_name' => 'Super Admin', 'body' => $v['body'] ?? null, 'attachment_path' => $path, 'attachment_name' => $file?->getClientOriginalName(), 'created_at' => now(), 'updated_at' => now()]);
                    DB::table('communication_conversations')->where('id', $cid)->update(['updated_at' => now()]);
                }
            });
        } catch (\Throwable $e) {
            \App\Services\CommunicationAttachmentStorage::delete($paths);
            throw $e;
        }

        return response()->json(['ids' => $ids, 'message' => 'Announcement published to selected communities.'], 201);
    }

    public function invite(Request $r)
    {
        Access::mutable($r);
        abort_unless(Access::manager($r), 403);
        $r->merge(['email' => strtolower(trim((string) $r->input('email')))]);
        $r->merge(['company_ids'=>$r->input('company_ids', $r->filled('company_id')?[$r->integer('company_id')]:[])]);
        $v = $r->validate(['email' => 'required|email|max:254', 'company_ids'=>'required|array|min:1|max:100','company_ids.*'=>'required|integer|distinct|exists:communication_companies,id', 'group_ids' => 'present|array|max:100', 'group_ids.*' => 'integer|distinct|exists:communication_conversations,id', 'communication_admin' => 'sometimes|boolean']);
        $v['company_ids']=array_map('intval',$v['company_ids']);$v['company_id']=$v['company_ids'][0];
        foreach($v['company_ids'] as $company)Access::manageCompany($r,$company);
        abort_if($v['email'] === strtolower((string) config('judibas.admin_email')), 422, 'This email is reserved for Super Admin.');
        abort_if(! Access::super($r) && ! empty($v['communication_admin']), 403, 'Only Super Admin can grant admin access.');
        $existing = User::where('email', $v['email'])->first();
        abort_if($existing && (! $existing->is_active || (!Access::super($r) && !array_intersect(Access::companies($existing->id),$v['company_ids']))), 422, 'This account belongs to another company or is inactive. Super Admin must update it first.');
        foreach ($v['group_ids'] as $gid) {
            abort_unless(DB::table('communication_conversations')->where('id', $gid)->where('kind', 'group')->whereIn('company_id', $v['company_ids'])->exists(), 422, 'Selected groups must belong to the selected companies.');
        }
        $token = Str::random(64);
        $id = DB::transaction(function () use ($r, $v, $token) {
            $pending=DB::table('communication_invitations')->where('email',$v['email'])->whereNull('accepted_at')->whereNull('revoked_at')->get();
            foreach($pending as $previous){abort_unless(Access::super($r)||!array_diff($this->invitationCompanies($previous),Access::companies()),403,'Super Admin must replace invitations spanning other companies.');}
            DB::table('communication_invitations')->whereIn('id',$pending->pluck('id'))->update(['revoked_at'=>now()]);

            return DB::table('communication_invitations')->insertGetId(['email' => $v['email'], 'company_id' => $v['company_id'], 'company_ids'=>json_encode($v['company_ids']), 'inviter_user_id' => Access::super($r) ? null : Auth::id(), 'invited_by_super' => Access::super($r), 'communication_admin' => (bool) ($v['communication_admin'] ?? false), 'group_ids' => json_encode($v['group_ids']), 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'created_at' => now(), 'updated_at' => now()]);
        });
        $link = $r->getSchemeAndHttpHost().'/communication/invitations/'.$token;
        $status = 'sent';
        $message = 'Invitation email sent.';
        try {
            Mail::to($v['email'])->send(new CommunicationInvitation($link, DB::table('communication_companies')->whereIn('id',$v['company_ids'])->orderBy('name')->pluck('name')->implode(', ')));
            if (in_array(config('mail.default'), ['log', 'array'])) {
                $status = 'log';
                $message = 'Invitation created. Email delivery is not configured; use the acceptance link.';
            }
        } catch (\Throwable $e) {
            $status = 'failed';
            $message = 'Invitation created, but email delivery failed. Check mail settings and resend.';
            report($e);
        }
        DB::table('communication_invitations')->where('id', $id)->update(['delivery_status' => $status, 'updated_at' => now()]);

        return response()->json(['id' => $id, 'message' => $message, 'delivery_status' => $status, 'acceptance_url' => $link], 201);
    }

    public function revoke(Request $r, int $id)
    {
        Access::mutable($r);
        $i = DB::table('communication_invitations')->where('id', $id)->first();
        abort_unless($i, 404);
        foreach($this->invitationCompanies($i) as $company)Access::manageCompany($r,$company);
        abort_if($i->accepted_at, 422, 'This invitation was already accepted.');
        DB::table('communication_invitations')->where('id', $id)->update(['revoked_at' => now(), 'updated_at' => now()]);

        return response()->json(['message' => 'Invitation revoked.']);
    }

    private function invitationCompanies($i): array {return array_map('intval',json_decode($i->company_ids??'null',true)?:[$i->company_id]);}
    private function invitation(string $token, bool $lock = false)
    {
        abort_unless(preg_match('/^[a-zA-Z0-9]{64}$/', $token), 404);
        $q = DB::table('communication_invitations')->where('token_hash', hash('sha256', $token));
        if ($lock) {
            $q->lockForUpdate();
        }$i = $q->first();
        abort_unless($i && ! $i->accepted_at && ! $i->revoked_at && $i->expires_at > now(), 410, 'This invitation has expired, was revoked, or has already been accepted.');
        if (! $i->invited_by_super) {
            $inviter = User::find($i->inviter_user_id);
            abort_unless($inviter && $inviter->is_active && $inviter->communication_admin && !array_diff($this->invitationCompanies($i),Access::companies($inviter->id)) && DB::table('user_product_access')->where('user_id', $inviter->id)->where('product_slug', 'communication')->exists(), 410, 'The inviting administrator no longer has permission.');
        }

        return $i;
    }

    public function invitationPage(Request $r, string $token)
    {
        $i = $this->invitation($token);
        $response = app(PortalController::class)->index($r);
        $portal = $response->getData()['portal'];
        $portal['invitation'] = ['token' => $token, 'email' => $i->email, 'company' => DB::table('communication_companies')->whereIn('id',$this->invitationCompanies($i))->orderBy('name')->pluck('name')->implode(', '), 'existing' => User::where('email', $i->email)->exists()];

        return view('portal', ['portal' => $portal]);
    }

    public function accept(Request $r, string $token)
    {
        $i = $this->invitation($token);
        $existing = User::where('email', $i->email)->first();
        $v = $r->validate($existing ? ['password' => 'required|string|max:200'] : ['name' => 'required|string|max:100', 'password' => 'required|string|min:12|max:200|confirmed']);
        $user = DB::transaction(function () use ($token, $v) {
            $i = $this->invitation($token, true);
            $u = User::where('email', $i->email)->lockForUpdate()->first();
            if ($u) {
                abort_unless($u->is_active && ($i->invited_by_super || array_intersect(Access::companies($u->id),$this->invitationCompanies($i))), 403);
                if (! Hash::check($v['password'], $u->password)) {
                    throw ValidationException::withMessages(['password' => 'Your current account password is incorrect.']);
                }
            } else {
                $u = new User;
                $u->name = $v['name'];
                $u->email = $i->email;
                $u->password = $v['password'];
                $u->is_active = true;
                $u->company_id = $i->company_id;
            }
            if ($i->communication_admin) {
                $u->communication_admin = true;
            }$u->save();
            foreach($this->invitationCompanies($i) as $company){DB::table('communication_company_memberships')->updateOrInsert(['user_id'=>$u->id,'company_id'=>$company],[]);Access::community($company);}
            request()->attributes->remove('communication.eligible.'.$u->id);
            DB::table('user_product_access')->updateOrInsert(['user_id' => $u->id, 'product_slug' => 'communication'], []);
            foreach (json_decode($i->group_ids, true) as $gid) {
                if (DB::table('communication_conversations')->where('id', $gid)->where('kind', 'group')->whereIn('company_id', $this->invitationCompanies($i))->exists()) {
                    DB::table('communication_members')->updateOrInsert(['conversation_id' => $gid, 'user_id' => $u->id], []);
                }
            }
            DB::table('communication_invitations')->where('id', $i->id)->update(['accepted_at' => now(), 'updated_at' => now()]);

            return $u;
        });
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();
        Auth::login($user);
        $r->session()->regenerate();

        return redirect('/communication')->with('success', 'Welcome to your company community.');
    }
}
