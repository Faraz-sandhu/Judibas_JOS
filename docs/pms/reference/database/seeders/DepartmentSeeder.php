<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['dept_name' => 'Admin', 'description' => 'admin' , 'status' => 1],
            ['dept_name' => 'Development', 'description' => 'Development' , 'status' => 1],
            ['dept_name' => 'SEO', 'description' => 'SEO' , 'status' => 1],
            ['dept_name' => 'Graphic', 'description' => 'Graphic' , 'status' => 1],
            ['dept_name' => 'Management', 'description' => 'Management' , 'status' => 1],
            ['dept_name' => 'Project Manager', 'description' => 'Project Manager' , 'status' => 1],
            ['dept_name' => 'Content Writer', 'description' => 'Content Writer' , 'status' => 1],
            ['dept_name' => 'Ecommerce', 'description' => 'Ecommerce' , 'status' => 1],
        ];

        foreach ($departments as $dept) {
            Department::firstOrCreate(
                ['dept_name' => $dept['dept_name']],
                ['description' => $dept['description'], 'status' => $dept['status']]
            );
        }
    }
}
