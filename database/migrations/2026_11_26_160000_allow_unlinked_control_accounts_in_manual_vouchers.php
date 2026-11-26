<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION accounting_validate_party_line() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF NEW.status='posted' AND OLD.status='draft' AND EXISTS (
  SELECT 1 FROM accounting_journal_lines l JOIN accounting_accounts a ON a.id=l.account_id
  LEFT JOIN accounting_parties p ON p.id=l.party_id AND p.company_id=l.company_id
  WHERE l.journal_id=NEW.id AND a.account_type IN ('Receivable','Payable') AND
   ((p.id IS NULL AND NOT (l.party_id IS NULL AND NEW.kind IN ('general','opening','reversal','cash_receipt','bank_receipt','cash_payment','bank_payment') AND NOT EXISTS (SELECT 1 FROM accounting_parties linked WHERE linked.account_id=a.id AND linked.company_id=a.company_id))) OR (a.account_type='Receivable' AND p.kind<>'customer') OR (a.account_type='Payable' AND p.kind<>'supplier'))
 ) THEN RAISE EXCEPTION 'Customer or supplier control lines need the matching party'; END IF;
 RETURN NEW;
END $$;
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION accounting_validate_party_line() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF NEW.status='posted' AND OLD.status='draft' AND EXISTS (
  SELECT 1 FROM accounting_journal_lines l JOIN accounting_accounts a ON a.id=l.account_id
  LEFT JOIN accounting_parties p ON p.id=l.party_id AND p.company_id=l.company_id
  WHERE l.journal_id=NEW.id AND a.account_type IN ('Receivable','Payable') AND
   (p.id IS NULL OR (a.account_type='Receivable' AND p.kind<>'customer') OR (a.account_type='Payable' AND p.kind<>'supplier'))
 ) THEN RAISE EXCEPTION 'Customer or supplier control lines need the matching party'; END IF;
 RETURN NEW;
END $$;
SQL);
    }
};
