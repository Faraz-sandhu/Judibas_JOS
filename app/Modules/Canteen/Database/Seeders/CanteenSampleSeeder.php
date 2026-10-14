<?php
namespace App\Modules\Canteen\Database\Seeders;
use App\Models\User;
use App\Modules\Canteen\Http\Controllers\OrderController;
use App\Modules\Canteen\Services\Ordering;
use App\Services\UserProductAccess;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Optional local test data. Never resets existing prices, stock, passwords or policies. */
class CanteenSampleSeeder extends Seeder {
 private function record(string $table,array $key,array $values):int {
  $id=DB::table($table)->where($key)->value('id');
  return $id??DB::table($table)->insertGetId($key+$values+['created_at'=>now(),'updated_at'=>now()]);
 }
 private function request(User $user,array $body):Request {
  $r=Request::create('/canteen/api/orders','POST',$body);
  $r->setLaravelSession(app('session')->driver());
  $r->session()->forget('judibas_admin');
  $r->setUserResolver(fn()=>$user);
  return $r;
 }
 public function run():void {
  $companies=DB::table('communication_companies')->orderBy('id')->get();
  if($companies->isEmpty()){ $this->command?->warn('Create a shared company in Administration first.');return; }
  DB::transaction(function()use($companies){
   $definitions=[
    'employee'=>['Ayesha Khan','canteen.ayesha@example.test','employee',1],
    'premium'=>['Omar Farooq','canteen.omar@example.test','employee',4],
    'kitchen'=>['Hamza Malik','canteen.kitchen@example.test','kitchen',2],
    'cashier'=>['Sara Ahmed','canteen.cashier@example.test','cashier',2],
    'admin'=>['Bilal Hussain','canteen.admin@example.test','admin',3],
   ];
   $users=[];
   foreach($definitions as $key=>[$name,$email,$role,$level]){
    $user=User::where('email',$email)->first();
    if($user){abort_unless(str_starts_with($user->designation??'','Canteen sample'),409,'A reserved sample email is already in use: '.$email);}
    else {$user=new User;$user->name=$name.' (Canteen test)';$user->email=$email;$user->password='CanteenTest123!';$user->is_active=true;$user->status=1;$user->designation='Canteen sample / '.$role;$user->email_verified_at=now();$user->save();
     UserProductAccess::sync($user,['product_slugs'=>['canteen'],'company_ids'=>$companies->pluck('id')->all(),'canteen_role'=>$role]);
    }
    $users[$key]=$user;
   }
   foreach($companies as $company){
    $levels=[];foreach([50,60,70,80] as $i=>$percent)$levels[$i+1]=$this->record('canteen_levels',['company_id'=>$company->id,'name'=>'Level '.($i+1)],['company_percent'=>$percent,'active'=>true]);
    $categories=[];foreach(['Drinks','Tea & Coffee','Snacks','Food'] as $name)$categories[$name]=$this->record('canteen_categories',['company_id'=>$company->id,'name'=>$name],['active'=>true]);
    $this->record('canteen_settings',['company_id'=>$company->id],['within_allowance_mode'=>'free','excess_mode'=>'level_share','ordering_enabled'=>true,'timezone'=>'Asia/Karachi']);
    DB::table('canteen_settings')->where('company_id',$company->id)->whereNull('within_allowance_mode')->update(['within_allowance_mode'=>'free','excess_mode'=>'level_share','ordering_enabled'=>true,'updated_at'=>now()]);
    foreach($definitions as $key=>[$name,$email,$role,$level]){DB::table('canteen_profiles')->where('company_id',$company->id)->where('user_id',$users[$key]->id)->whereNull('level_id')->update(['level_id'=>$levels[$level],'updated_at'=>now()]);}
    $items=[];
    foreach([
     ['Chai','Tea & Coffee',100,80,3,[1,2,3,4]],
     ['Cold Coffee','Tea & Coffee',180,30,2,[2,3,4]],
     ['Hot Coffee','Tea & Coffee',150,25,1,[3,4]],
     ['Mineral Water','Drinks',60,50,3,[1,2,3,4]],
     ['Fresh Orange Juice','Drinks',200,20,1,[2,3,4]],
     ['Premium Biscuits','Snacks',120,15,2,[4]],
     ['Chicken Biryani','Food',250,20,1,[1,2,3,4]],
     ['Vegetable Rice','Food',170,3,1,[1,2,3,4]],
     ['Roti','Food',25,60,3,[1,2,3,4]],
     ['Chicken Curry','Food',300,0,1,[2,3,4]],
    ] as [$name,$category,$price,$stock,$limit,$eligible]){
     $existing=DB::table('canteen_items')->where('company_id',$company->id)->where('name',$name)->first();
     $items[$name]=$existing?->id??$this->record('canteen_items',['company_id'=>$company->id,'name'=>$name],['category_id'=>$categories[$category],'price'=>$price,'active'=>true,'stock_enabled'=>true,'stock_quantity'=>$stock,'low_stock_threshold'=>5]);
     if(!$existing){DB::table('canteen_stock_movements')->insert(['company_id'=>$company->id,'item_id'=>$items[$name],'quantity_change'=>$stock,'balance'=>$stock,'reason'=>'Sample opening stock','created_at'=>now()]);foreach($eligible as $level)$this->record('canteen_item_rules',['item_id'=>$items[$name],'level_id'=>$levels[$level]],['quantity_limit'=>$limit,'period'=>'day']);}
    }
    $scenarios=[
     ['tea-allowance','employee','Chai',3,'delivered',false],
     ['extra-tea','employee','Chai',1,'confirmed',false],
     ['cold-coffee','premium','Cold Coffee',1,'preparing',false],
     ['biryani','premium','Chicken Biryani',1,'ready',false],
     ['biscuits','premium','Premium Biscuits',1,'delivered',false],
     ['cancelled-rice','employee','Vegetable Rice',1,'cancelled',false],
     ['paid-coffee','premium','Hot Coffee',2,'confirmed',true],
    ];
    foreach($scenarios as [$scenario,$person,$item,$quantity,$state,$paid]){
     $hash=md5('canteen-samples-v1-'.$company->id.'-'.$scenario);
     $uuid=substr($hash,0,8).'-'.substr($hash,8,4).'-4'.substr($hash,13,3).'-8'.substr($hash,17,3).'-'.substr($hash,20,12);
     if(DB::table('canteen_orders')->where('request_key',$uuid)->exists())continue;
     $body=['company_id'=>$company->id,'request_key'=>$uuid,'lines'=>[['item_id'=>$items[$item],'quantity'=>$quantity]]];
     $request=$this->request($users[$person],$body);
     try{$quote=Ordering::quote($request,$company->id,$body['lines']);}
     catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->command?->warn('Skipped '.$company->name.' / '.$scenario.': '.$e->getMessage());continue;}
     $request->merge(['quote_token'=>Ordering::token($request,$company->id,$quote)]);
     $controller=app(OrderController::class);$id=$controller->store($request)->getData(true)['order']['id'];
     if($state==='cancelled')$controller->update($this->request($users['cashier'],['status'=>'cancelled']),$id);
     else foreach(['preparing','ready','delivered'] as $next){if($state==='confirmed')break;$controller->update($this->request($users['kitchen'],['status'=>$next]),$id);if($next===$state)break;}
     if($paid&&$quote['employee_share']>0)$controller->update($this->request($users['cashier'],['payment_status'=>'paid']),$id);
    }
   }
  });
  $this->command?->info('Canteen samples added using shared companies. Existing configuration was preserved.');
 }
}
