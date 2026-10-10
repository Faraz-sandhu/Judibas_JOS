<?php

namespace App\Modules\Pms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workflow extends Model
{
    protected $table='pms_workflows';
    protected $fillable = ['department_id', 'name', 'description', 'is_default', 'is_active', 'transition_mode'];
    protected $casts = ['is_default' => 'boolean', 'is_active' => 'boolean'];

    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function columns(): HasMany { return $this->hasMany(WorkflowColumn::class)->orderBy('position'); }
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(
            Projects::class,
            'pms_project_workflow',
            'workflow_id',
            'project_id'
        );
    }
    public function tasks(): HasMany { return $this->hasMany(Tasks::class); }
}
