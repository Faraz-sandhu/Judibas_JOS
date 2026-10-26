<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_journals', function (Blueprint $t) {
            $t->foreign(['company_id', 'reversal_of'], 'accounting_reversal_company_fk')->references(['company_id', 'id'])->on('accounting_journals')->restrictOnDelete();
        });
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::unprepared(<<<'SQL'
CREATE FUNCTION accounting_validate_posting() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE book accounting_books%ROWTYPE;
BEGIN
 IF TG_OP = 'INSERT' THEN
  IF NEW.status <> 'draft' THEN RAISE EXCEPTION 'Accounting documents must start as drafts'; END IF;
  RETURN NEW;
 END IF;
 IF OLD.company_id <> NEW.company_id OR OLD.number <> NEW.number OR OLD.request_key <> NEW.request_key OR OLD.reversal_of IS DISTINCT FROM NEW.reversal_of THEN
  RAISE EXCEPTION 'Accounting document identity is immutable';
 END IF;
 IF OLD.status = 'draft' AND NEW.status = 'reversed' THEN RAISE EXCEPTION 'Draft entries cannot be reversed'; END IF;
 IF OLD.status = 'draft' AND NEW.status = 'posted' THEN
  PERFORM id FROM communication_companies WHERE id = NEW.company_id FOR UPDATE;
  SELECT * INTO book FROM accounting_books WHERE company_id = NEW.company_id;
  IF NOT FOUND OR NEW.posting_date < book.start_date OR (book.locked_through IS NOT NULL AND NEW.posting_date <= book.locked_through) THEN
   RAISE EXCEPTION 'Posting date is outside an open accounting period';
  END IF;
  IF NEW.kind = 'opening' AND (NEW.posting_date <> book.start_date OR EXISTS (SELECT 1 FROM accounting_journal_lines l JOIN accounting_accounts a ON a.id=l.account_id WHERE l.journal_id=NEW.id AND a.root_type IN ('Income','Expense'))) THEN
   RAISE EXCEPTION 'Invalid opening balance entry';
  END IF;
  IF EXISTS (SELECT 1 FROM accounting_journal_lines l JOIN accounting_accounts a ON a.id=l.account_id WHERE l.journal_id=NEW.id AND (a.is_group OR (NOT a.active AND NEW.reversal_of IS NULL))) THEN
   RAISE EXCEPTION 'Posting requires active ledger accounts';
  END IF;
  IF NEW.reversal_of IS NOT NULL THEN
   IF NOT EXISTS (SELECT 1 FROM accounting_journals WHERE id=NEW.reversal_of AND status='posted' AND reversal_of IS NULL AND posting_date<=NEW.posting_date) THEN RAISE EXCEPTION 'Invalid reversal source'; END IF;
   IF EXISTS (
    (SELECT account_id,position,debit_minor,credit_minor FROM accounting_journal_lines WHERE journal_id=NEW.id
     EXCEPT SELECT account_id,position,credit_minor,debit_minor FROM accounting_journal_lines WHERE journal_id=NEW.reversal_of)
    UNION ALL
    (SELECT account_id,position,credit_minor,debit_minor FROM accounting_journal_lines WHERE journal_id=NEW.reversal_of
     EXCEPT SELECT account_id,position,debit_minor,credit_minor FROM accounting_journal_lines WHERE journal_id=NEW.id)
   ) THEN RAISE EXCEPTION 'Reversal must exactly offset the original entry'; END IF;
  END IF;
 END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER accounting_posting_guard BEFORE INSERT OR UPDATE ON accounting_journals FOR EACH ROW EXECUTE FUNCTION accounting_validate_posting();
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS accounting_posting_guard ON accounting_journals');
            DB::statement('DROP FUNCTION IF EXISTS accounting_validate_posting()');
        }
        Schema::table('accounting_journals', fn (Blueprint $t) => $t->dropForeign('accounting_reversal_company_fk'));
    }
};
