<?php

namespace App\Modules\Daymark\Services;

use App\Modules\Daymark\Models\Company;
use App\Modules\Daymark\Models\Department;

class DepartmentDirectory
{
    public static function resolve(int $companyId, array $input, bool $allowCreate = true): string
    {
        // All department writers share this lock, including deletion and reassignment.
        Company::whereKey($companyId)->lockForUpdate()->firstOrFail();
        if (! empty($input['department_id'])) {
            $department = Department::where('company_id', $companyId)->whereKey($input['department_id'])->first();
            abort_unless($department, 422, 'This department is no longer available. Choose another department.');

            return $department->name;
        }
        $name = trim(preg_replace('/\s+/u', ' ', $input['department'] ?? ''));
        abort_if($name === '' || mb_strlen($name) > 200, 422, 'Enter a department name between 1 and 200 characters.');
        abort_if(! $allowCreate && ! Department::where('company_id', $companyId)->where('name', $name)->exists(), 403, 'You may select existing departments but cannot create a new department.');

        return Department::firstOrCreate(['company_id' => $companyId, 'name' => $name])->name;
    }
}
