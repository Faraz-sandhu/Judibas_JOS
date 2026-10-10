<?php
namespace App\Modules\Pms\Services;
class PmsGate {
 public static function authorize(string $ability):void { PmsAccess::requirePermission(request(),$ability); }
 public static function allows(string $ability):bool { return PmsAccess::allows(request(),$ability); }
 public static function denies(string $ability):bool { return !self::allows($ability); }
}
