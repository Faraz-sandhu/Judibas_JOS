<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['accounting_journals', 'accounting_invoices'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->unsignedBigInteger('prepared_by')->nullable());
        }
    }
    public function down(): void { throw new RuntimeException('Accounting preparer attribution must be preserved.'); }
};
