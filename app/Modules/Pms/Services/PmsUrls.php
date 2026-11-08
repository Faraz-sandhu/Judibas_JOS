<?php
namespace App\Modules\Pms\Services;
class PmsUrls {
 public static function workspace(array $parameters=[]):string {
  if(isset($parameters['screen'])){$parameters['section']=$parameters['screen'];unset($parameters['screen']);}
  $company=PmsCompanyContext::selected();
  $path=$company?\App\Services\CompanyWorkspace::url('pms',$company->id):'/pms/all';
  return url($path).($parameters?'?'.http_build_query($parameters):'');
 }
}
