<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up():void {if(DB::getDriverName()==='pgsql')DB::statement("ALTER TABLE accounting_invoices ADD CONSTRAINT accounting_invoice_discount_values CHECK (discount_type IN ('fixed','percent') AND discount_minor>=0 AND foreign_discount_minor>=0 AND COALESCE(subtotal_minor,net_minor)=net_minor+discount_minor AND COALESCE(foreign_subtotal_minor,foreign_net_minor,net_minor)=COALESCE(foreign_net_minor,net_minor)+foreign_discount_minor)");}
 public function down():void {}
};
