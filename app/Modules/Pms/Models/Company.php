<?php

namespace App\Modules\Pms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope('company_context', function ($q) {
            if (\App\Modules\Pms\Services\PmsCompanyContext::restrict()) {
                $q->whereIn('pms_companies.id', \App\Modules\Pms\Services\PmsVisibility::companyIds());
            }
        });
    }

    protected $table='pms_companies';
    protected $fillable = ['name', 'email', 'phone', 'website', 'status'];

    protected $casts = ['status' => 'boolean'];

    public function projects(): HasMany
    {
        return $this->hasMany(Projects::class);
    }
}
