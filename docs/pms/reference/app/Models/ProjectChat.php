<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProjectChat extends Model
{
    use HasFactory;
    protected $fillable = ['project_id', 'user_id', 'message', 'parent_id', 'seen_by'];

    protected $casts = [
        'seen_by' => 'array',
    ];

      public function project()
    {
        return $this->belongsTo(Projects::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent()
    {
        return $this->belongsTo(ProjectChat::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(ProjectChat::class, 'parent_id');
    }
}
