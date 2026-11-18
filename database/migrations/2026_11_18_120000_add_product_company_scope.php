<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_product_access', function (Blueprint $table) {
            // Null preserves existing shared-company access; [] explicitly grants no companies.
            $table->json('company_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_product_access', fn (Blueprint $table) => $table->dropColumn('company_ids'));
    }
};
