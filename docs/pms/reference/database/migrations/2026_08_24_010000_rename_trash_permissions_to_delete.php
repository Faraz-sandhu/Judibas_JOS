<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $labels = [
        'employee-trash' => 'Employee Delete',
        'role-trash' => 'Role Delete',
        'permission-trash' => 'Permission Delete',
        'project-trash' => 'Project Delete',
        'department-trash' => 'Department Delete',
        'task-trash' => 'Task Delete',
        'sub-task-trash' => 'Sub Task Delete',
    ];

    public function up(): void
    {
        foreach ($this->labels as $key => $label) {
            DB::table('permissions')->where('permission_key', $key)->update([
                'permission_name' => $label,
            ]);
        }
    }

    public function down(): void
    {
        foreach ($this->labels as $key => $label) {
            DB::table('permissions')->where('permission_key', $key)->update([
                'permission_name' => str_replace('Delete', 'Trash', $label),
            ]);
        }
    }
};
