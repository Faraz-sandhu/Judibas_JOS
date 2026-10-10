<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = ['name', 'email', 'phone', 'website', 'status'];

    protected $casts = ['status' => 'boolean'];

    public function projects(): HasMany
    {
        return $this->hasMany(Projects::class);
    }
}
