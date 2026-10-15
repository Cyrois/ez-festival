<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', fn (Blueprint $table) => $table->unique(['id', 'event_id'], 'locations_id_event_unique'));
        Schema::table('entitlement_items', fn (Blueprint $table) => $table->unique(['id', 'event_id'], 'items_id_event_unique'));
        Schema::table('issued_entitlements', fn (Blueprint $table) => $table->unique(['id', 'entitlement_item_id'], 'issued_id_item_unique'));

        $id = DB::getDriverName() === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGSERIAL PRIMARY KEY';

        DB::statement("CREATE TABLE stock_holders (
            id {$id},
            event_id BIGINT NOT NULL,
            location_id BIGINT NULL,
            person_id BIGINT NULL,
            CONSTRAINT sh_one_target CHECK (
                (location_id IS NOT NULL AND person_id IS NULL) OR
                (location_id IS NULL AND person_id IS NOT NULL)
            ),
            CONSTRAINT sh_event_fk FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE RESTRICT,
            CONSTRAINT sh_location_event_fk FOREIGN KEY (location_id, event_id) REFERENCES locations (id, event_id) ON DELETE RESTRICT,
            CONSTRAINT sh_person_fk FOREIGN KEY (person_id) REFERENCES people (id) ON DELETE RESTRICT,
            CONSTRAINT sh_id_event_unique UNIQUE (id, event_id),
            CONSTRAINT sh_event_location_unique UNIQUE (event_id, location_id),
            CONSTRAINT sh_event_person_unique UNIQUE (event_id, person_id)
        )");

        DB::statement("CREATE TABLE stock_movements (
            id {$id},
            event_id BIGINT NOT NULL,
            entitlement_item_id BIGINT NOT NULL,
            quantity INTEGER NOT NULL,
            from_holder_id BIGINT NULL,
            to_holder_id BIGINT NULL,
            type VARCHAR(20) NOT NULL,
            actor_user_id BIGINT NULL,
            note VARCHAR(255) NULL,
            issued_entitlement_id BIGINT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT sm_quantity_check CHECK (quantity > 0 AND quantity <= 2147483647 AND quantity = CAST(quantity AS INTEGER)),
            CONSTRAINT sm_shape_check CHECK (
                (type = 'add' AND from_holder_id IS NULL AND to_holder_id IS NOT NULL AND issued_entitlement_id IS NULL) OR
                (type = 'remove' AND from_holder_id IS NOT NULL AND to_holder_id IS NULL AND issued_entitlement_id IS NULL AND note IS NOT NULL AND LENGTH(TRIM(note)) > 0) OR
                (type = 'give_out' AND from_holder_id IS NOT NULL AND to_holder_id IS NULL AND issued_entitlement_id IS NOT NULL AND quantity = 1) OR
                (type IN ('pull', 'return', 'transfer', 'take_back') AND from_holder_id IS NOT NULL AND to_holder_id IS NOT NULL AND from_holder_id <> to_holder_id AND issued_entitlement_id IS NULL)
            ),
            CONSTRAINT sm_event_fk FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE RESTRICT,
            CONSTRAINT sm_item_event_fk FOREIGN KEY (entitlement_item_id, event_id) REFERENCES entitlement_items (id, event_id) ON DELETE CASCADE,
            CONSTRAINT sm_from_event_fk FOREIGN KEY (from_holder_id, event_id) REFERENCES stock_holders (id, event_id) ON DELETE RESTRICT,
            CONSTRAINT sm_to_event_fk FOREIGN KEY (to_holder_id, event_id) REFERENCES stock_holders (id, event_id) ON DELETE RESTRICT,
            CONSTRAINT sm_actor_fk FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT sm_issue_fk FOREIGN KEY (issued_entitlement_id, entitlement_item_id) REFERENCES issued_entitlements (id, entitlement_item_id) ON DELETE RESTRICT,
            CONSTRAINT sm_issue_unique UNIQUE (issued_entitlement_id)
        )");

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->index(['entitlement_item_id', 'from_holder_id'], 'sm_item_from_idx');
            $table->index(['entitlement_item_id', 'to_holder_id'], 'sm_item_to_idx');
            $table->index(['event_id', 'created_at', 'id'], 'sm_event_time_idx');
            $table->index(['from_holder_id', 'entitlement_item_id'], 'sm_from_item_idx');
            $table->index(['to_holder_id', 'entitlement_item_id'], 'sm_to_item_idx');
            $table->index(['actor_user_id'], 'sm_actor_idx');
        });

        $this->createTriggers();

        // Product confirmed that the legacy stock ledger has no data to retain.
        Schema::drop('entitlement_adjustments');
    }

    private function createTriggers(): void
    {
        $invalidEndpoints = "(NEW.type = 'add' AND NOT EXISTS (SELECT 1 FROM stock_holders WHERE id = NEW.to_holder_id AND location_id IS NOT NULL))
            OR (NEW.type = 'remove' AND NOT EXISTS (SELECT 1 FROM stock_holders WHERE id = NEW.from_holder_id AND location_id IS NOT NULL))
            OR (NEW.type = 'give_out' AND NOT EXISTS (SELECT 1 FROM issued_entitlements WHERE id = NEW.issued_entitlement_id AND entitlement_item_id = NEW.entitlement_item_id))";

        if (DB::getDriverName() === 'sqlite') {
            foreach (['INSERT', 'UPDATE'] as $action) {
                DB::unprepared("CREATE TRIGGER sm_endpoints_{$action} BEFORE {$action} ON stock_movements
                    FOR EACH ROW WHEN {$invalidEndpoints}
                    BEGIN SELECT RAISE(ABORT, 'Invalid stock movement endpoints'); END");
            }
            DB::unprepared("CREATE TRIGGER sh_immutable BEFORE UPDATE ON stock_holders
                FOR EACH ROW WHEN NEW.id IS NOT OLD.id OR NEW.event_id IS NOT OLD.event_id
                    OR NEW.location_id IS NOT OLD.location_id OR NEW.person_id IS NOT OLD.person_id
                BEGIN SELECT RAISE(ABORT, 'Stock holder identity is immutable'); END");
            $changed = collect(['id', 'event_id', 'entitlement_item_id', 'quantity', 'from_holder_id', 'to_holder_id', 'type', 'note', 'issued_entitlement_id', 'created_at'])
                ->map(fn ($column) => "NEW.{$column} IS NOT OLD.{$column}")->implode(' OR ');
            DB::unprepared("CREATE TRIGGER sm_immutable BEFORE UPDATE ON stock_movements
                FOR EACH ROW WHEN {$changed} OR (NEW.actor_user_id IS NOT OLD.actor_user_id AND NEW.actor_user_id IS NOT NULL)
                BEGIN SELECT RAISE(ABORT, 'Stock movements are append-only'); END");

            return;
        }

        DB::unprepared("CREATE OR REPLACE FUNCTION validate_stock_movement() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF {$invalidEndpoints} THEN
                    RAISE EXCEPTION 'Invalid stock movement endpoints' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END; $$");
        DB::unprepared('CREATE TRIGGER sm_endpoints BEFORE INSERT OR UPDATE ON stock_movements FOR EACH ROW EXECUTE FUNCTION validate_stock_movement()');
        DB::unprepared("CREATE OR REPLACE FUNCTION preserve_stock_holder() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF ROW(NEW.id, NEW.event_id, NEW.location_id, NEW.person_id) IS DISTINCT FROM ROW(OLD.id, OLD.event_id, OLD.location_id, OLD.person_id) THEN
                    RAISE EXCEPTION 'Stock holder identity is immutable' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END; $$");
        DB::unprepared('CREATE TRIGGER sh_immutable BEFORE UPDATE ON stock_holders FOR EACH ROW EXECUTE FUNCTION preserve_stock_holder()');
        DB::unprepared("CREATE OR REPLACE FUNCTION preserve_stock_movement() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF ROW(NEW.id, NEW.event_id, NEW.entitlement_item_id, NEW.quantity, NEW.from_holder_id, NEW.to_holder_id, NEW.type, NEW.note, NEW.issued_entitlement_id, NEW.created_at)
                    IS DISTINCT FROM ROW(OLD.id, OLD.event_id, OLD.entitlement_item_id, OLD.quantity, OLD.from_holder_id, OLD.to_holder_id, OLD.type, OLD.note, OLD.issued_entitlement_id, OLD.created_at)
                    OR (NEW.actor_user_id IS DISTINCT FROM OLD.actor_user_id AND NEW.actor_user_id IS NOT NULL) THEN
                    RAISE EXCEPTION 'Stock movements are append-only' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END; $$");
        DB::unprepared('CREATE TRIGGER sm_immutable BEFORE UPDATE ON stock_movements FOR EACH ROW EXECUTE FUNCTION preserve_stock_movement()');
    }

    public function down(): void
    {
        // Destructive schema rollback only: person holdings cannot be converted to
        // the old location ledger. After use, restore a backup or fix forward.
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_holders');
        if (DB::getDriverName() !== 'sqlite') {
            DB::unprepared('DROP FUNCTION IF EXISTS validate_stock_movement()');
            DB::unprepared('DROP FUNCTION IF EXISTS preserve_stock_holder()');
            DB::unprepared('DROP FUNCTION IF EXISTS preserve_stock_movement()');
        }
        Schema::table('locations', fn (Blueprint $table) => $table->dropUnique('locations_id_event_unique'));
        Schema::table('entitlement_items', fn (Blueprint $table) => $table->dropUnique('items_id_event_unique'));
        Schema::table('issued_entitlements', fn (Blueprint $table) => $table->dropUnique('issued_id_item_unique'));
        Schema::create('entitlement_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('entitlement_item_id')->constrained()->cascadeOnDelete();
            $table->integer('delta');
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entitlement_item_id', 'created_at']);
            $table->index(['entitlement_item_id', 'location_id']);
            $table->index('location_id', 'adjustments_location_idx');
        });
    }
};
