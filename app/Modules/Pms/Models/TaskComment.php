<?php

namespace App\Modules\Pms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskComment extends Model
{
    protected $table='pms_task_comments';
    protected $fillable = ['task_id', 'user_id', 'body', 'author_name'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Tasks::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
