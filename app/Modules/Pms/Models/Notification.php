<?php

namespace App\Modules\Pms\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'pms_notifications';

    protected $fillable = [
        'type',
        'notifiable_id',
        'notifiable_type',
        'data',
        'read_at',
    ];
}
