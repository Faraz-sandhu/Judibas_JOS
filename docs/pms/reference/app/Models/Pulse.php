<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pulse extends Model
{
    protected $fillable = ['user_id', 'login_time', 'is_online'];


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

