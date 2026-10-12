<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Services\UserProductAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
class PlatformInvitationController extends Controller
{
 private function admin(Request $r):void {\App\Services\PlatformAccess::requireAdmin($r);}
 public function index(Request $r){$this->admin($r);return response()->json(['invitations'=>DB::table('platform_invitations')->latest('id')->limit(200)->get(['id','email','name','access','expires_at','accepted_at','revoked_at','delivery_status','created_at'])->map(function($i){$i->access=json_decode($i->access,true);return $i;})]);}
 public function store(Request $r){
  $this->admin($r);$r->merge(['email'=>strtolower(trim((string)$r->input('email')))]);
  $v=$r->validate(UserProductAccess::rules()+['name'=>'nullable|string|max:100','email'=>'required|email|max:200','delivery_method'=>'required|in:manual,email']);
  abort_if($v['email']===strtolower(config('judibas.admin_email')),422,'This email is reserved for Super Admin.');
  $existing=User::where('email',$v['email'])->first();\App\Services\PlatformAccess::protectAccount($r,$existing?->id);
  UserProductAccess::validateSelection($v);
  if(in_array('projects',$v['product_slugs'])&&!count($v['pms_role_ids']??[]))throw \Illuminate\Validation\ValidationException::withMessages(['pms_role_ids'=>'Select a PMS role.']);
  $access=collect($v)->only(array_keys(UserProductAccess::rules()))->all();$token=Str::random(64);
  $id=DB::transaction(function()use($v,$access,$token){DB::table('platform_invitations')->where('email',$v['email'])->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at'=>now(),'updated_at'=>now()]);return DB::table('platform_invitations')->insertGetId(['email'=>$v['email'],'name'=>$v['name']??null,'access'=>json_encode($access),'token_hash'=>hash('sha256',$token),'expires_at'=>now()->addDays(7),'created_at'=>now(),'updated_at'=>now()]);});
  $url=$r->getSchemeAndHttpHost().'/invitations/'.$token;$status='manual';$message='Invitation created. Share the secure link; it expires in seven days.';
  if($v['delivery_method']==='email') {
   if(!\App\Models\MailSetting::current()->invitation_emails_enabled){$status='email_disabled';$message='Invitation created. Email invitations are disabled; share the link.';}
   elseif(in_array(config('mail.default'),['array','log'])){$status='not_configured';$message='Invitation created. Configure shared email settings or share the link.';}
   else try {Mail::raw("You have been invited to Judibas. Accept your assigned product access: ".$url."\nThis link expires in seven days. Existing users sign in with their current account.",fn($m)=>$m->to($v['email'])->subject('Your Judibas invitation'));$status='sent';$message='Invitation email sent.';}catch(\Throwable $e){report($e);$status='failed';$message='Invitation created, but email delivery failed. Share the link or check shared email settings.';}
  }
  DB::table('platform_invitations')->where('id',$id)->update(['delivery_status'=>$status]);
  return response()->json(['message'=>$message,'invitation_url'=>$url,'delivery_status'=>$status],201);
 }
 public function revoke(Request $r,int $id){$this->admin($r);abort_unless(DB::table('platform_invitations')->where('id',$id)->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at'=>now(),'updated_at'=>now()]),409,'This invitation is already used or revoked.');return response()->json(['message'=>'Invitation revoked.']);}
 private function invitation(string $token,bool $lock=false){$q=DB::table('platform_invitations')->where('token_hash',hash('sha256',$token));if($lock)$q->lockForUpdate();$i=$q->first();abort_unless($i,404);abort_if($i->accepted_at||$i->revoked_at||now()->greaterThanOrEqualTo($i->expires_at),410,'This invitation has expired or is no longer available.');return $i;}
 public function details(string $token){$i=$this->invitation($token);return response()->json(['email'=>$i->email,'name'=>$i->name,'access'=>json_decode($i->access,true),'existing'=>User::where('email',$i->email)->exists(),'can_accept'=>!request()->session()->get('judibas_admin')&&Auth::user()?->email===$i->email,'products'=>collect(config('judibas.products'))->whereIn('slug',json_decode($i->access,true)['product_slugs'])->pluck('name')->values(),'companies'=>DB::table('communication_companies')->whereIn('id',json_decode($i->access,true)['company_ids'])->pluck('name')]);}
 public function page(Request $r,string $token){$i=$this->invitation($token);if(User::where('email',$i->email)->exists()&&($r->session()->get('judibas_admin')||Auth::user()?->email!==$i->email))$r->session()->put('url.intended','/invitations/'.$token);return app(PortalController::class)->index($r);}
 public function accept(Request $r,string $token){
  $user=DB::transaction(function()use($r,$token){$i=$this->invitation($token,true);$access=json_decode($i->access,true);validator($access,UserProductAccess::rules())->validate();UserProductAccess::validateSelection($access);
   $u=User::where('email',$i->email)->lockForUpdate()->first();
   if($u){abort_unless(!$r->session()->get('judibas_admin')&&Auth::id()===$u->id&&$u->is_active,403,'Sign in using the invited email to accept this invitation.');}
   else {$v=$r->validate(['name'=>'required|string|max:100','password'=>['required','confirmed',Password::min(12)]]);$u=new User;$u->name=$v['name'];$u->email=$i->email;$u->password=$v['password'];$u->is_active=true;$u->status=1;$u->email_verified_at=now();$u->save();}
   UserProductAccess::sync($u,$access,true);DB::table('platform_invitations')->where('id',$i->id)->update(['accepted_at'=>now(),'updated_at'=>now()]);return $u;
  });
  $r->session()->forget('judibas_admin');Auth::login($user);$r->session()->regenerate();return response()->json(['message'=>'Your product access is ready.','redirect_url'=>'/']);
 }
}
