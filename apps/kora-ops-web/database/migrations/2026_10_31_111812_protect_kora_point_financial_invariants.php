<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE kora_points ADD CONSTRAINT kora_points_valid_terms CHECK ((commission_basis_points IS NULL OR commission_basis_points BETWEEN 0 AND 10000) AND (ceiling_amount IS NULL OR ceiling_amount >= 0))');
            DB::statement('ALTER TABLE kora_point_organizations ADD CONSTRAINT kora_point_org_valid_terms CHECK ((commission_basis_points IS NULL OR commission_basis_points BETWEEN 0 AND 10000) AND (ceiling_amount IS NULL OR ceiling_amount >= 0))');
            DB::statement('ALTER TABLE kora_point_sales ADD CONSTRAINT kora_point_sale_amounts CHECK (gross_amount >= 0 AND commission_amount BETWEEN 0 AND gross_amount AND net_amount = gross_amount - commission_amount AND outstanding_amount >= 0 AND refunded_amount BETWEEN 0 AND gross_amount AND commission_reversed BETWEEN 0 AND commission_amount)');
            DB::statement("ALTER TABLE kora_point_sales ADD CONSTRAINT kora_point_sale_status CHECK (status IN ('open','settled','reopened'))");
            DB::statement('ALTER TABLE kora_partner_documents ADD CONSTRAINT kora_partner_document_owner CHECK ((point_id IS NOT NULL)::int + (organization_id IS NOT NULL)::int = 1)');
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION reject_kora_point_entry_mutation() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'Partner ledger entries are append-only';
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER kora_point_entries_immutable BEFORE UPDATE OR DELETE ON kora_point_entries FOR EACH ROW EXECUTE FUNCTION reject_kora_point_entry_mutation();
CREATE OR REPLACE FUNCTION protect_kora_point_sale_snapshot() RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'Partner sales cannot be deleted'; END IF;
    IF ROW(OLD.point_id, OLD.organization_id, OLD.reservation_id, OLD.payment_id, OLD.seller_id, OLD.gross_amount, OLD.commission_amount, OLD.net_amount, OLD.terms_snapshot::text, OLD.paid_at)
       IS DISTINCT FROM ROW(NEW.point_id, NEW.organization_id, NEW.reservation_id, NEW.payment_id, NEW.seller_id, NEW.gross_amount, NEW.commission_amount, NEW.net_amount, NEW.terms_snapshot::text, NEW.paid_at) THEN
        RAISE EXCEPTION 'Partner sale snapshot is immutable';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER kora_point_sales_snapshot_immutable BEFORE UPDATE OR DELETE ON kora_point_sales FOR EACH ROW EXECUTE FUNCTION protect_kora_point_sale_snapshot();
SQL);
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER kora_point_entries_no_update BEFORE UPDATE ON kora_point_entries BEGIN SELECT RAISE(ABORT, 'Partner ledger is append-only'); END");
            DB::unprepared("CREATE TRIGGER kora_point_entries_no_delete BEFORE DELETE ON kora_point_entries BEGIN SELECT RAISE(ABORT, 'Partner ledger is append-only'); END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS kora_point_entries_immutable ON kora_point_entries');
            DB::statement('DROP TRIGGER IF EXISTS kora_point_sales_snapshot_immutable ON kora_point_sales');
            DB::statement('DROP FUNCTION IF EXISTS reject_kora_point_entry_mutation()');
            DB::statement('DROP FUNCTION IF EXISTS protect_kora_point_sale_snapshot()');
            DB::statement('ALTER TABLE kora_points DROP CONSTRAINT IF EXISTS kora_points_valid_terms');
            DB::statement('ALTER TABLE kora_point_organizations DROP CONSTRAINT IF EXISTS kora_point_org_valid_terms');
            DB::statement('ALTER TABLE kora_point_sales DROP CONSTRAINT IF EXISTS kora_point_sale_amounts');
            DB::statement('ALTER TABLE kora_point_sales DROP CONSTRAINT IF EXISTS kora_point_sale_status');
            DB::statement('ALTER TABLE kora_partner_documents DROP CONSTRAINT IF EXISTS kora_partner_document_owner');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS kora_point_entries_no_update');
            DB::statement('DROP TRIGGER IF EXISTS kora_point_entries_no_delete');
        }
    }
};
