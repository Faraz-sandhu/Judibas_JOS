<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pms_projects', function (Blueprint $table) {
            $table->string('name_key')->nullable()->after('name');
        });

        $seen = [];
        DB::table('pms_projects')->orderBy('id')->get(['id', 'name'])->each(function ($project) use (&$seen): void {
            $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $project->name)));
            // Preserve legacy duplicate rows without making deployment fail. All new saves use the normalized key.
            $key = isset($seen[$normalized]) ? 'legacy-duplicate-'.$project->id.'-'.substr(hash('sha256', $normalized), 0, 20) : $normalized;
            $seen[$normalized] = true;
            DB::table('pms_projects')->where('id', $project->id)->update(['name_key' => $key]);
        });

        Schema::table('pms_projects', function (Blueprint $table) {
            $table->unique('name_key', 'projects_name_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pms_projects', function (Blueprint $table) {
            $table->dropUnique('projects_name_key_unique');
            $table->dropColumn('name_key');
        });
    }
};
