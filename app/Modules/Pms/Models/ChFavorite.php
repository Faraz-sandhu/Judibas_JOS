<?php

namespace App\Modules\Pms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ChFavorite extends Model
{
    protected $table='pms_ch_favorites';
    use HasUuids;
}
