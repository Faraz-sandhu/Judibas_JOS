<?php
namespace App\Modules\Pms\Models;
use Illuminate\Database\Eloquent\Model;
class Client extends Model {
 protected $table='pms_clients';
 protected $fillable=['company_id','name','email','phone','website','description','status'];
 protected $casts=['status'=>'boolean'];
 public function company(){return $this->belongsTo(Company::class);}
 public function projects(){return $this->hasMany(Projects::class,'client_id');}
}
