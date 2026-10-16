<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cv_bank_departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        $names = DB::table('cv_bank_jobs')->pluck('department')->merge(DB::table('cv_bank_applications')->pluck('department'))->unique();
        foreach ($names as $name) {
            DB::table('cv_bank_departments')->insert(['name'=>$name,'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_bank_departments');
    }
};
