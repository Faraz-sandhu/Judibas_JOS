<?php

namespace App\Modules\Pms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sprint extends Model
{
    protected static function booted():void {static::addGlobalScope('visibility',fn(\Illuminate\Database\Eloquent\Builder $q)=>\App\Modules\Pms\Services\PmsVisibility::sprints($q));}
    protected $table='pms_sprints';
    protected $fillable = [
        'project_id',
        'department_id',
        'name',
        'goal',
        'start_date',
        'end_date',
        'status',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function department(){return $this->belongsTo(Department::class);}

    public function project(): BelongsTo
    {
        return $this->belongsTo(Projects::class, 'project_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Tasks::class)->orderBy('position');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
