<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailDelivery extends Model
{
    protected $fillable = ['type', 'recipient', 'invitation_id', 'status', 'error', 'sent_at'];

    protected $casts = ['sent_at' => 'datetime'];
}
