<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_books', function (Blueprint $t) {
            $t->foreignId('company_id')->primary()->constrained('communication_companies')->restrictOnDelete();
            $t->string('currency', 3);
            $t->date('start_date');
            $t->date('locked_through')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
        Schema::create('accounting_journals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('communication_companies')->restrictOnDelete();
            $t->uuid('request_key');
            $t->string('number', 40);
            $t->date('posting_date');
            $t->string('kind', 20)->default('general');
            $t->string('status', 20)->default('draft');
            $t->string('reference', 150)->nullable();
            $t->text('remarks');
            $t->unsignedInteger('version')->default(1);
            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('posted_by')->nullable();
            $t->timestamp('posted_at')->nullable();
            $t->foreignId('reversal_of')->nullable()->constrained('accounting_journals')->restrictOnDelete();
            $t->text('reversal_reason')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'id']);
            $t->unique(['company_id', 'request_key']);
            $t->unique(['company_id', 'number']);
            $t->unique('reversal_of');
            $t->index(['company_id', 'posting_date']);
        });
        Schema::create('accounting_journal_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('journal_id');
            $t->unsignedBigInteger('account_id');
            $t->unsignedInteger('position');
            $t->bigInteger('debit_minor')->default(0);
            $t->bigInteger('credit_minor')->default(0);
            $t->string('description', 250)->nullable();
            $t->foreign(['company_id', 'journal_id'])->references(['company_id', 'id'])->on('accounting_journals')->restrictOnDelete();
            $t->foreign(['company_id', 'account_id'])->references(['company_id', 'id'])->on('accounting_accounts')->restrictOnDelete();
            $t->unique(['journal_id', 'position']);
            $t->index(['company_id', 'account_id']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE accounting_journals ADD CONSTRAINT accounting_journal_status CHECK (status IN ('draft','posted','reversed'))");
            DB::statement("ALTER TABLE accounting_journals ADD CONSTRAINT accounting_journal_kind CHECK (kind IN ('general','opening','reversal'))");
            DB::statement('ALTER TABLE accounting_journal_lines ADD CONSTRAINT accounting_one_sided_line CHECK ((debit_minor > 0 AND credit_minor = 0) OR (credit_minor > 0 AND debit_minor = 0))');
            // Posted source lines are the ledger: neither changing nor deleting them is permitted.
            DB::unprepared(<<<'SQL'
CREATE FUNCTION accounting_guard_lines() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE journal_status text;
BEGIN
 IF TG_OP <> 'INSERT' THEN
  SELECT status INTO journal_status FROM accounting_journals WHERE id = OLD.journal_id FOR UPDATE;
  IF journal_status <> 'draft' THEN RAISE EXCEPTION 'Posted accounting lines are immutable'; END IF;
 END IF;
 IF TG_OP <> 'DELETE' THEN
  SELECT status INTO journal_status FROM accounting_journals WHERE id = NEW.journal_id FOR UPDATE;
  IF journal_status <> 'draft' THEN RAISE EXCEPTION 'Posted accounting lines are immutable'; END IF;
  RETURN NEW;
 END IF;
 RETURN OLD;
END $$;
CREATE TRIGGER accounting_lines_guard BEFORE INSERT OR UPDATE OR DELETE ON accounting_journal_lines FOR EACH ROW EXECUTE FUNCTION accounting_guard_lines();
CREATE FUNCTION accounting_guard_journal() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE debit_total numeric; credit_total numeric; line_count integer;
BEGIN
 IF TG_OP = 'DELETE' THEN
  IF OLD.status <> 'draft' THEN RAISE EXCEPTION 'Posted accounting documents cannot be deleted'; END IF;
  RETURN OLD;
 END IF;
 IF OLD.status <> 'draft' THEN
  IF OLD.status <> 'posted' OR NEW.status <> 'reversed' OR (to_jsonb(NEW) - 'status' - 'version' - 'updated_at') IS DISTINCT FROM (to_jsonb(OLD) - 'status' - 'version' - 'updated_at') THEN
   RAISE EXCEPTION 'Posted accounting documents are immutable';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM accounting_journals WHERE reversal_of = OLD.id AND status = 'posted') THEN RAISE EXCEPTION 'A posted reversal is required'; END IF;
 END IF;
 IF NEW.status = 'posted' AND OLD.status = 'draft' THEN
  SELECT COALESCE(SUM(debit_minor),0), COALESCE(SUM(credit_minor),0), COUNT(*) INTO debit_total, credit_total, line_count FROM accounting_journal_lines WHERE journal_id = NEW.id;
  IF line_count < 2 OR debit_total <= 0 OR debit_total <> credit_total THEN RAISE EXCEPTION 'Journal must have balanced debit and credit lines'; END IF;
 END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER accounting_journal_guard BEFORE UPDATE OR DELETE ON accounting_journals FOR EACH ROW EXECUTE FUNCTION accounting_guard_journal();
SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_journal_lines');
        Schema::dropIfExists('accounting_journals');
        Schema::dropIfExists('accounting_books');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS accounting_guard_lines()');
            DB::statement('DROP FUNCTION IF EXISTS accounting_guard_journal()');
        }
    }
};
