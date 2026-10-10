<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAttachment extends Model
{
    protected $appends = ['url'];

    protected $fillable = ['task_id', 'user_id', 'original_name', 'path', 'mime_type', 'size'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Tasks::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/'.$this->path);
    }
}
