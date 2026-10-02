<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Indexes cover existing ledger lookups, owner loads, and FK delete checks. */
    private const INDEXES = [
        'expected_entitlements' => [
            'expected_assignment_idx' => ['pass_assignment_id'],
            'expected_item_status_idx' => ['entitlement_item_id', 'status'],
        ],
        'issued_entitlements' => [
            'issued_item_time_idx' => ['entitlement_item_id', 'issued_at'],
            'issued_location_idx' => ['location_id'],
        ],
        'pass_type_entitlements' => [
            'pass_lines_type_order_idx' => ['pass_type_id', 'sort_order'],
            'pass_lines_item_idx' => ['entitlement_item_id'],
        ],
        'pass_assignments' => [
            'assignments_artist_person_idx' => ['artist_engagement_id', 'person_id'],
            'assignments_vendor_person_idx' => ['vendor_engagement_id', 'person_id'],
            'assignments_team_person_idx' => ['team_engagement_id', 'person_id'],
            'assignments_patron_idx' => ['event_patron_id'],
            'assignments_person_idx' => ['person_id'],
        ],
        'team_engagements' => [
            'team_role_person_idx' => ['role_id', 'person_id'],
        ],
        'entitlement_adjustments' => [
            'adjustments_location_idx' => ['location_id'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $tableName => $indexes) {
            Schema::table($tableName, function (Blueprint $table) use ($indexes): void {
                foreach ($indexes as $name => $columns) {
                    $table->index($columns, $name);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $tableName => $indexes) {
            Schema::table($tableName, function (Blueprint $table) use ($indexes): void {
                foreach (array_keys($indexes) as $name) {
                    $table->dropIndex($name);
                }
            });
        }
    }
};
