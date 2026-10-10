<?php

namespace App\Modules\Pms\Models;

use Illuminate\Database\Eloquent\Model;

class EmailDelivery extends Model
{
    protected $table='pms_email_deliveries';
    protected $fillable = ['type', 'recipient', 'invitation_id', 'status', 'error', 'sent_at'];

    protected $casts = ['sent_at' => 'datetime'];
}
