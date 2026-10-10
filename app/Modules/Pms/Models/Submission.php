<?php

namespace App\Modules\Pms\Models;

use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    protected $table='pms_submissions';
    protected $fillable = ['user_id', 'description', 'file_path', 'status', 'title'];

    public function submittable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
