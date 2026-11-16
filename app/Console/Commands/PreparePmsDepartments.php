<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PreparePmsDepartments extends Command
{
    protected $signature = 'judibas:prepare-pms-departments {--apply : Create missing departments without changing existing records}';

    protected $description = 'Preview or add the reviewed PMS department directory used by the local installation';

    public const DEPARTMENTS = [
        'Development' => 'Software development and technical delivery.',
        'Graphic' => 'Graphic design and visual creative work.',
        'Admin' => 'Administrative operations.',
        'Content Writer' => 'Content writing and editorial work.',
        'Ecommerce' => 'E-commerce operations and delivery.',
        'Management' => 'Management and operational coordination.',
        'Marketing' => 'Marketing campaigns and delivery.',
        'Project Manager' => 'Project management and delivery coordination.',
        'SEO' => 'Search engine optimisation.',
    ];

    public function handle(): int
    {
        $this->info('PMS departments: '.implode(', ', array_keys(self::DEPARTMENTS)));
        if (! $this->option('apply')) {
            $this->info('Preview only. Use --apply to add missing departments.');

            return self::SUCCESS;
        }

        $created = DB::transaction(function () {
            $created = 0;
            foreach (self::DEPARTMENTS as $name => $description) {
                // Preserve existing IDs, status, spelling, descriptions and all relationships.
                if (DB::table('pms_departments')->whereRaw('lower(trim(dept_name)) = ?', [strtolower($name)])->exists()) {
                    continue;
                }
                $created += DB::table('pms_departments')->insertOrIgnore([
                    'dept_name' => $name,
                    'description' => $description,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $created;
        });

        $this->info("Created {$created} missing PMS departments. Existing records were preserved.");
        $this->info('No users, project links, workflows, tasks or permissions were changed. Assign departments to people/projects through the existing forms.');

        return self::SUCCESS;
    }
}
