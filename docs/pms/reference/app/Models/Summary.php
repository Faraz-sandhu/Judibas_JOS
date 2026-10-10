<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\Projects;

class Summary extends Model
{
    use HasFactory;
    protected $table = 'summaries';
    protected $fillable = [
        'user_id',
        'project_id',
        'task',
        'date',
    ];
    public function users()
    {
        return $this->belongsTo(User::class);
    }
    public function projects()
    {
        return $this->belongsTo(Projects::class);
    }
}
