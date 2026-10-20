<?php

namespace App\Modules\Daymark\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $table = 'communication_companies';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['company_id' => 'integer'];
    }
}
