<?php
namespace App\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class CvBankAccess {
 public static function authorize(Request $r):void {abort_unless(PlatformAccess::product($r,'cv-bank'),403,'CV Bank access is required.');}
 public static function legacyId():int {return (int)DB::table('cv_bank_settings')->where('id',1)->value('company_id');}
 public static function companies(Request $r){
  self::authorize($r);$q=DB::table('communication_companies as c')->leftJoin('cv_bank_company_pages as p','p.company_id','=','c.id');
  if(!PlatformAccess::administrator($r))$q->whereIn('c.id',CommunicationAccess::companies($r->user()->id));
  return $q->orderBy('c.name')->get(['c.id','c.name','p.slug as career_slug','p.published as career_published'])->map(function($c){$c->career_url=$c->career_slug?'/careers/'.$c->career_slug:null;return $c;});
 }
 public static function company(Request $r,?int $id=null):object {
  $r->validate(['company_id'=>'nullable|integer|min:1']);$companies=self::companies($r);$id??=$r->filled('company_id')?(int)$r->input('company_id'):(PlatformAccess::administrator($r)?self::legacyId():($companies->first()->id??0));
  $company=$companies->firstWhere('id',$id);abort_unless($company,403,'This company is not assigned to your CV Bank access.');return $company;
 }
 public static function row(Request $r,string $table,int $id):object { $row=DB::table($table)->whereIn('company_id',self::companies($r)->pluck('id'))->find($id);abort_unless($row,404);if($r->filled('company_id'))abort_unless((int)$r->input('company_id')===$row->company_id,404);return $row; }
 public static function page(?string $slug):object {
  $q=DB::table('cv_bank_company_pages as p')->join('communication_companies as c','c.id','=','p.company_id');
  if($slug!==null)$q->where('p.slug',$slug);else $q->where('p.company_id',self::legacyId());
  $page=$q->first(['p.*','c.name as company_name']);abort_unless($page,404);return $page;
 }
}
