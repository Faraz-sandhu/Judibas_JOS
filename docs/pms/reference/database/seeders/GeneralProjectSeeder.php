<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Projects;
use Livewire\Features\SupportQueryString\BaseUrl;

class GeneralProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Projects::updateOrCreate(
            ['is_general' => true],
            [
                'name' => 'General Project',
                'image' => url('project.png'),
                'description' => 'This project contains general tasks assigned to all users.',
                'start_date' => now(),
                'end_date' =>null,
                'status' => 'in_progress',
            ]
        );
    }
}
