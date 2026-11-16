<?php

namespace Tests\Feature;

use App\Console\Commands\PreparePmsDepartments;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PmsDepartmentSetupTest extends TestCase
{
    use DatabaseTransactions;

    public function test_preview_does_not_write_departments(): void
    {
        $before = DB::table('pms_departments')->orderBy('id')->get();
        $this->artisan('judibas:prepare-pms-departments')->assertSuccessful();
        $this->assertEquals($before, DB::table('pms_departments')->orderBy('id')->get());
    }

    public function test_apply_adds_missing_departments_and_preserves_existing_records_on_repeat(): void
    {
        DB::table('pms_departments')->where('dept_name', 'SEO')->update(['dept_name' => 'Existing unrelated SEO team']);
        $existing = DB::table('pms_departments')->whereRaw('lower(dept_name) = ?', ['development'])->first();
        $id = $existing?->id ?? DB::table('pms_departments')->insertGetId(['dept_name' => 'development', 'status' => 0]);
        DB::table('pms_departments')->where('id', $id)->update(['dept_name' => 'DEVELOPMENT', 'description' => 'Retain this description', 'status' => 0]);
        $before = DB::table('pms_departments')->where('id', $id)->first();
        $this->artisan('judibas:prepare-pms-departments', ['--apply' => true])->assertSuccessful();
        foreach (array_keys(PreparePmsDepartments::DEPARTMENTS) as $name) {
            $this->assertSame(1, DB::table('pms_departments')->whereRaw('lower(trim(dept_name)) = ?', [strtolower($name)])->count());
        }
        $this->assertEquals($before, DB::table('pms_departments')->where('id', $id)->first());
        $after = DB::table('pms_departments')->orderBy('id')->get();
        $this->artisan('judibas:prepare-pms-departments', ['--apply' => true])->assertSuccessful();
        $this->assertEquals($after, DB::table('pms_departments')->orderBy('id')->get());
    }
}
