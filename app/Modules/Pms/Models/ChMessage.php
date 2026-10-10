<?php

namespace App\Modules\Pms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\Broadcast;

class ChMessage extends Model
{
    protected $table='pms_ch_messages';
    use HasUuids;

}
