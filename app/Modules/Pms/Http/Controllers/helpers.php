<?php
namespace App\Modules\Pms\Http\Controllers;
function view($name,$data=[]){return response()->json(['screen'=>$name,'data'=>$data]);}
function route($name,$parameters=[],$absolute=true){if(in_array($name,['pms_invitations.join','invitations.join'],true))return url('/pms/join/'.(is_array($parameters)?($parameters['token']??reset($parameters)):$parameters));return \route('pms.'.preg_replace('/^pms_/', '',$name),$parameters,$absolute);}
