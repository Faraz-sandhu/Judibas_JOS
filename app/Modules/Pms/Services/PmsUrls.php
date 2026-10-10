<?php
namespace App\Modules\Pms\Services;
class PmsUrls { public static function workspace(array $parameters=[]):string {if(isset($parameters['screen'])){$parameters['section']=$parameters['screen'];unset($parameters['screen']);}return url('/pms').($parameters?'?'.http_build_query($parameters):'');} }
