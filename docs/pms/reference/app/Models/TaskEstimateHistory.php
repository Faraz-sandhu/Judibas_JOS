<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskEstimateHistory extends Model
{
    protected $fillable = ['task_id', 'user_id', 'old_minutes', 'new_minutes'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
