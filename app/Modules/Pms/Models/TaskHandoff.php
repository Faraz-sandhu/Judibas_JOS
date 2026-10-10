<?php

namespace App\Modules\Pms\Models;

use Illuminate\Database\Eloquent\Model;

class TaskHandoff extends Model
{
    protected $table='pms_task_handoffs';
    protected $fillable = ['source_task_id','destination_task_id','source_department_id','destination_department_id','source_project_id','destination_project_id','handed_off_by','notes'];

    public function sourceTask() { return $this->belongsTo(Tasks::class, 'source_task_id'); }
    public function destinationTask() { return $this->belongsTo(Tasks::class, 'destination_task_id'); }
    public function sourceDepartment() { return $this->belongsTo(Department::class, 'source_department_id'); }
    public function destinationDepartment() { return $this->belongsTo(Department::class, 'destination_department_id'); }
    public function sourceProject() { return $this->belongsTo(Projects::class, 'source_project_id'); }
    public function destinationProject() { return $this->belongsTo(Projects::class, 'destination_project_id'); }
    public function actor() { return $this->belongsTo(User::class, 'handed_off_by'); }
}
