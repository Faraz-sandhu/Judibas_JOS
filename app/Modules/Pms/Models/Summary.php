<?php

namespace App\Modules\Pms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Modules\Pms\Models\User;
use App\Modules\Pms\Models\Projects;

class Summary extends Model
{
    use HasFactory;
    protected $table = 'pms_summaries';
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
