<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Accounting\Services\Permissions;
use App\Services\UserProductAccess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrepareLiveTesting extends Command
{
    protected $signature = 'judibas:prepare-testing {--apply : Create the reviewed testing setup}';

    protected $description = 'Preview or create canonical companies and isolated named testing accounts';

    public function handle(): int
    {
        $names = ['Judibas GOC', 'Skyinfinit', 'Cruxlo', 'Wecoma', 'Bedizen', 'NLTPL', 'China bedding store', 'Yubis', 'Sincwell', 'Suivillion', 'Corzella', 'True North', 'Oxyien', 'Judibas Store', 'Judibas Canteen', 'True Water', 'Gaming Lounge', 'Backend - FSD', 'Sales Office - FSD', 'Sales Office - MUL'];
        $this->info('Companies: '.implode(', ', $names));
        $this->info('Named testing accounts use @example.test and password requested by the owner. Existing accounts are never reset. No emails or accounting postings are created.');
        if (! $this->option('apply')) {
            $this->info('Preview only. Use --apply after taking a database backup.');

            return self::SUCCESS;
        }
        DB::transaction(function () use ($names) {
            // Rename the original Judibas in place to retain all linked data. Never delete unknown companies.
            $canonical = DB::table('communication_companies')->whereRaw('lower(name)=?', ['judibas goc'])->first();
            if (! $canonical) {
                $original = DB::table('communication_companies')->whereRaw('lower(name) in (?,?)', ['judibas', 'judibass'])->orderBy('id')->first();
                if ($original) {
                    DB::table('communication_companies')->where('id', $original->id)->update(['name' => 'Judibas GOC', 'updated_at' => now()]);
                    DB::table('pms_companies')->where('platform_company_id', $original->id)->update(['name' => 'Judibas GOC']);
                }
            }
            foreach ($names as $name) {
                if (! DB::table('communication_companies')->whereRaw('lower(name)=?', [strtolower($name)])->exists()) {
                    DB::table('communication_companies')->insert(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            $companyIds = DB::table('communication_companies')->whereIn('name', $names)->pluck('id', 'name');
            foreach (DB::table('communication_companies')->whereRaw('lower(name) in (?,?)', ['judibas', 'judibass'])->get() as $duplicate) {
                $references = DB::select("SELECT c.conrelid::regclass::text AS table_name,a.attname AS column_name FROM pg_constraint c JOIN pg_attribute a ON a.attrelid=c.conrelid AND a.attnum=ANY(c.conkey) WHERE c.contype='f' AND c.confrelid='communication_companies'::regclass");
                $blocked = [];
                foreach ($references as $ref) {
                    if (in_array($ref->table_name, ['users', 'communication_company_memberships'])) {
                        continue;
                    }if (DB::table($ref->table_name)->where($ref->column_name, $duplicate->id)->exists()) {
                        $blocked[] = $ref->table_name;
                    }
                }
                if ($blocked) {
                    $this->warn('Preserved duplicate '.$duplicate->name.' (#'.$duplicate->id.') because linked data exists in: '.implode(', ', array_unique($blocked)).'. Requires reviewed data merge.');

                    continue;
                }
                $target = $companyIds['Judibas GOC'];
                DB::table('users')->where('company_id', $duplicate->id)->update(['company_id' => $target]);
                foreach (DB::table('communication_company_memberships')->where('company_id', $duplicate->id)->get() as $m) {
                    DB::table('communication_company_memberships')->insertOrIgnore(['user_id' => $m->user_id, 'company_id' => $target]);
                }DB::table('communication_company_memberships')->where('company_id', $duplicate->id)->delete();
                DB::table('communication_companies')->where('id', $duplicate->id)->delete();
            }
            foreach (Permissions::presets() as $slug => [$name,$permissions]) {
                if (! DB::table('accounting_roles')->where('slug', $slug)->exists()) {
                    DB::table('accounting_roles')->insert(['slug' => $slug, 'name' => $name, 'permissions' => json_encode($permissions), 'active' => true, 'protected' => true, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            if (! DB::table('pms_roles')->exists()) {
                $this->callSilent('db:seed', ['--class' => 'App\\Modules\\Pms\\Database\\Seeders\\RoleSeeder', '--force' => true]);
                $this->callSilent('db:seed', ['--class' => 'App\\Modules\\Pms\\Database\\Seeders\\PermissionSeeder', '--force' => true]);
                $this->callSilent('db:seed', ['--class' => 'App\\Modules\\Pms\\Database\\Seeders\\PermissionRoleSeeder', '--force' => true]);
            }
            $company = (int) $companyIds['Skyinfinit'];
            $levels = [];
            foreach ([1 => 50, 2 => 75] as $number => $percent) {
                $name = 'Level '.$number;
                $level = DB::table('canteen_levels')->where('company_id', $company)->where('name', $name)->value('id');
                $levels[$number] = $level ?? DB::table('canteen_levels')->insertGetId(['company_id' => $company, 'name' => $name, 'company_percent' => $percent, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
            if (! DB::table('canteen_settings')->where('company_id', $company)->exists()) {
                DB::table('canteen_settings')->insert(['company_id' => $company, 'within_allowance_mode' => 'free', 'excess_mode' => 'level_share', 'ordering_enabled' => true, 'timezone' => 'Asia/Karachi', 'created_at' => now(), 'updated_at' => now()]);
            }
            $category = DB::table('canteen_categories')->where('company_id', $company)->where('name', 'Testing menu')->value('id') ?? DB::table('canteen_categories')->insertGetId(['company_id' => $company, 'name' => 'Testing menu', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            foreach ([['Chai (Test)', 100, 20, 3], ['Chicken Biryani (Test)', 250, 10, 1], ['Mineral Water (Test)', 60, 30, 3]] as [$name,$price,$stock,$limit]) {
                if (DB::table('canteen_items')->where('company_id', $company)->where('name', $name)->exists()) {
                    continue;
                }$item = DB::table('canteen_items')->insertGetId(['company_id' => $company, 'category_id' => $category, 'name' => $name, 'price' => $price, 'active' => true, 'stock_enabled' => true, 'stock_quantity' => $stock, 'low_stock_threshold' => 3, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('canteen_stock_movements')->insert(['company_id' => $company, 'item_id' => $item, 'quantity_change' => $stock, 'balance' => $stock, 'reason' => 'Testing opening stock', 'created_at' => now()]);
                foreach ($levels as $level) {
                    DB::table('canteen_item_rules')->insert(['item_id' => $item, 'level_id' => $level, 'quantity_limit' => $limit, 'period' => 'day', 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            $specs = [['Ayesha Khan', 'finance', 'finance_officer', 'employee', 'employee', 'developer'], ['Omar Farooq', 'finance-manager', 'senior_finance_manager', 'admin', 'company_admin', 'admin'], ['Hassan Ali', 'ceo', 'ceo', 'employee', 'ceo', 'employee'], ['Hamza Malik', 'project-manager', 'viewer', 'employee', 'pm', 'project_manager'], ['Sara Ahmed', 'hr', 'viewer', 'employee', 'hr', 'employee'], ['Bilal Hussain', 'employee', 'viewer', 'employee', 'employee', 'developer'], ['Usman Raza', 'kitchen', 'viewer', 'kitchen', 'employee', 'employee'], ['Zain Abbas', 'cashier', 'viewer', 'cashier', 'employee', 'employee']];
            foreach ($specs as [$name,$tag,$accounting,$canteen,$daymark,$pms]) {
                $email = 'judibas.test.'.$tag.'@example.test';
                if (User::where('email', $email)->exists()) {
                    $this->line('Existing testing account preserved: '.$email);

                    continue;
                }
                $u = new User;
                $u->forceFill(['name' => $name.' (Test)', 'email' => $email, 'password' => '11223344', 'is_active' => true, 'status' => 1, 'judibas_administrator' => false]);
                $u->save();
                $slugs = in_array($tag, ['kitchen', 'cashier']) ? ['communication', 'canteen'] : ['communication', 'projects', 'canteen', 'daymark', 'cv-bank', 'focus-to-do', 'accounts'];
                $ids = in_array($tag, ['finance-manager', 'finance', 'hr']) ? $companyIds->values()->map(fn ($id) => (int) $id)->all() : [$company];
                $role = DB::table('pms_roles')->where('role_key', $pms)->value('id') ?? DB::table('pms_roles')->where('role_key', 'developer')->value('id');
                UserProductAccess::sync($u, ['product_slugs' => $slugs, 'company_ids' => $ids, 'accounting_role' => $accounting, 'canteen_role' => $canteen, 'canteen_level_ids' => [$company => $levels[1]], 'daymark_company_id' => $company, 'daymark_role' => $daymark, 'daymark_active' => true, 'daymark_designation' => ucwords(str_replace('-', ' ', $tag)), 'pms_role_ids' => $role ? [$role] : []]);
                // Benefit levels belong to their company: only Skyinfinit has this testing menu.
                DB::table('canteen_profiles')->where('user_id', $u->id)->where('company_id', '<>', $company)->update(['level_id' => null]);
                $this->line($name.' (Test) | '.$email.' | '.$tag);
            }
            $manager = User::where('email', 'judibas.test.project-manager@example.test')->value('id');
            $employee = User::where('email', 'judibas.test.employee@example.test')->value('id');
            if ($manager && $employee) {
                DB::table('daymark_assignments')->updateOrInsert(['manager_id' => $manager, 'employee_id' => $employee],[]);
            }
        });
        $this->info('Testing setup complete. Existing user passwords and custom roles were preserved.');

        return self::SUCCESS;
    }
}
