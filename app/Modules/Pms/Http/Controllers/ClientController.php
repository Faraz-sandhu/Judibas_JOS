<?php
namespace App\Modules\Pms\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Modules\Pms\Models\{Client,Company,Projects};
use App\Modules\Pms\Services\{PmsAccess,PmsVisibility};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class ClientController extends Controller {
 public function index(){PmsVisibility::syncCompanies();abort_unless(PmsAccess::allows(request(),'project-view')||PmsAccess::allows(request(),'client-view'),403);$companies=Company::whereIn('id',PmsVisibility::companyIds())->orderBy('name')->get();$clients=Client::whereIn('company_id',$companies->pluck('id'))->when(!PmsVisibility::allProjects(),fn($q)=>$q->whereHas('projects'))->with('company:id,name')->withCount('projects')->orderBy('name')->get();return response()->json(['clients'=>$clients,'companies'=>$companies]);}
 public function hierarchy(){
  PmsAccess::requirePermission(request(),'project-view');PmsVisibility::syncCompanies();
  $all=PmsVisibility::allProjects();$companyIds=PmsVisibility::companyIds();
  $projects=Projects::where('is_general',false)->select('id','name','company_id','client_id')->with('departments:id,dept_name')->orderBy('name')->get();
  if(!$all){
   $pairs=\App\Modules\Pms\Models\Tasks::whereIn('project_id',$projects->pluck('id'))->select('project_id','department_id')->distinct()->get()->groupBy('project_id');
   foreach($projects as $project)$project->setRelation('departments',$project->departments->whereIn('id',($pairs->get($project->id)??collect())->pluck('department_id'))->values());
  }
  $clients=Client::whereIn('company_id',$companyIds)->when(!$all,fn($q)=>$q->whereHas('projects'))->orderBy('name')->get();
  foreach($clients as $client)$client->setRelation('projects',$projects->where('client_id',$client->id)->values());
  $companies=Company::whereIn('id',$companyIds)->orderBy('name')->get();
  foreach($companies as $company){$company->setRelation('clients',$clients->where('company_id',$company->id)->values());$company->setRelation('unassigned_projects',$projects->where('company_id',$company->id)->whereNull('client_id')->values());}
  if(!$all)$companies=$companies->filter(fn($c)=>$c->clients->isNotEmpty()||$c->unassigned_projects->isNotEmpty())->values();
  $legacy=PmsVisibility::unrestricted()?$projects->whereNotIn('company_id',$companyIds)->values():collect();
  return response()->json(['companies'=>$companies,'unassigned_projects'=>$legacy,'show_companies'=>$all]);
 }
 private function visible(Client $client):void {abort_unless(in_array($client->company_id,PmsVisibility::companyIds()),404);}
 public function save(Request $r,?Client $client=null){PmsAccess::requirePermission($r,$client?'client-edit':'client-add');if($client)$this->visible($client);$v=$r->validate(['company_id'=>['required','integer',Rule::in(PmsVisibility::companyIds())],'name'=>['required','string','max:255',Rule::unique('pms_clients')->where('company_id',$r->company_id)->ignore($client?->id)],'email'=>'nullable|email|max:255','phone'=>'nullable|string|max:50','website'=>'nullable|url|max:2048','description'=>'nullable|string|max:10000','status'=>'required|boolean']);if($client&&$client->company_id!=(int)$v['company_id'])abort_if($client->projects()->exists(),422,'A client with projects cannot move to another company.');$client?$client->update($v):$client=Client::create($v);return response()->json(['client'=>$client,'message'=>'Client saved.'],$client->wasRecentlyCreated?201:200);}
 public function destroy(Client $client){PmsAccess::requirePermission(request(),'client-trash');$this->visible($client);abort_if($client->projects()->exists(),422,'Move or delete the client projects before deleting the client.');$client->delete();return response()->json(['message'=>'Client deleted.']);}
}
