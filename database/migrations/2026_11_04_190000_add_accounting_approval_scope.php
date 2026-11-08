<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up():void
    {
        // Null preserves the existing policy: approval applies to all document types.
        Schema::table('accounting_books',fn(Blueprint $t)=>$t->json('approval_types')->nullable());
    }
    public function down():void {throw new RuntimeException('Accounting approval scope must be retained.');}
};
