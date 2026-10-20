<?php

namespace App\Modules\Daymark\Models;

use Illuminate\Database\Eloquent\Model;

class CustomRole extends Model
{
    protected $table = 'daymark_custom_roles';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['permissions' => 'array', 'company_id' => 'integer'];
    }
}
