<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowColumn extends Model
{
    protected $fillable = ['workflow_id', 'name', 'color', 'position', 'is_initial', 'is_completed'];
    protected $casts = ['is_initial' => 'boolean', 'is_completed' => 'boolean'];

    public function workflow(): BelongsTo { return $this->belongsTo(Workflow::class); }
    public function tasks(): HasMany { return $this->hasMany(Tasks::class); }
}
