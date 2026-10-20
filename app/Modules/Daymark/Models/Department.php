<?php

namespace App\Modules\Daymark\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $table = 'daymark_departments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['company_id' => 'integer'];
    }
}
