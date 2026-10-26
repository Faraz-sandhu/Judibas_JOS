<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        DB::statement("ALTER TABLE accounting_parties ADD CONSTRAINT accounting_party_kind CHECK (kind IN ('customer','supplier'))");
        DB::statement("ALTER TABLE accounting_invoices ADD CONSTRAINT accounting_invoice_values CHECK (kind IN ('sale','purchase') AND status IN ('draft','posted','reversed') AND net_minor>0 AND tax_minor>=0 AND total_minor=net_minor+tax_minor AND tax_basis_points BETWEEN 0 AND 10000 AND due_date>=posting_date AND ((status='draft' AND journal_id IS NULL) OR (status<>'draft' AND journal_id IS NOT NULL)))");
        DB::statement("ALTER TABLE accounting_payments ADD CONSTRAINT accounting_payment_values CHECK (amount_minor>0 AND status IN ('posted','reversed'))");
        DB::unprepared(<<<'SQL'
CREATE FUNCTION accounting_guard_trade() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF TG_OP = 'DELETE' THEN
  IF OLD.status <> 'draft' THEN RAISE EXCEPTION 'Posted trade records cannot be deleted'; END IF;
  RETURN OLD;
 END IF;
 IF OLD.status <> 'draft' THEN
  IF OLD.status <> 'posted' OR NEW.status <> 'reversed' OR (to_jsonb(NEW)-'status'-'version'-'updated_at') IS DISTINCT FROM (to_jsonb(OLD)-'status'-'version'-'updated_at') THEN
   RAISE EXCEPTION 'Posted trade records are immutable';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM accounting_journals WHERE id=OLD.journal_id AND status='reversed') THEN RAISE EXCEPTION 'Reverse the source ledger entry first'; END IF;
 END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER accounting_invoice_guard BEFORE UPDATE OR DELETE ON accounting_invoices FOR EACH ROW EXECUTE FUNCTION accounting_guard_trade();
CREATE TRIGGER accounting_payment_guard BEFORE UPDATE OR DELETE ON accounting_payments FOR EACH ROW EXECUTE FUNCTION accounting_guard_trade();
CREATE FUNCTION accounting_validate_party_line() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF NEW.status='posted' AND OLD.status='draft' AND EXISTS (
  SELECT 1 FROM accounting_journal_lines l JOIN accounting_accounts a ON a.id=l.account_id
  LEFT JOIN accounting_parties p ON p.id=l.party_id AND p.company_id=l.company_id
  WHERE l.journal_id=NEW.id AND a.account_type IN ('Receivable','Payable') AND
   (p.id IS NULL OR (a.account_type='Receivable' AND p.kind<>'customer') OR (a.account_type='Payable' AND p.kind<>'supplier'))
 ) THEN RAISE EXCEPTION 'Customer or supplier control lines need the matching party'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER accounting_party_line_guard BEFORE UPDATE ON accounting_journals FOR EACH ROW EXECUTE FUNCTION accounting_validate_party_line();
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        DB::statement('DROP TRIGGER IF EXISTS accounting_invoice_guard ON accounting_invoices');
        DB::statement('DROP TRIGGER IF EXISTS accounting_payment_guard ON accounting_payments');
        DB::statement('DROP TRIGGER IF EXISTS accounting_party_line_guard ON accounting_journals');
        DB::statement('DROP FUNCTION IF EXISTS accounting_guard_trade()');
        DB::statement('DROP FUNCTION IF EXISTS accounting_validate_party_line()');
        DB::statement('ALTER TABLE accounting_parties DROP CONSTRAINT accounting_party_kind');
        DB::statement('ALTER TABLE accounting_invoices DROP CONSTRAINT accounting_invoice_values');
        DB::statement('ALTER TABLE accounting_payments DROP CONSTRAINT accounting_payment_values');
    }
};
