<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loss_events')) {
            Schema::table('loss_events', function (Blueprint $table) {
                if (!Schema::hasColumn('loss_events', 'loss_reference')) {
                    $table->string('loss_reference')->nullable()->unique()->after('loss_id');
                }
                if (!Schema::hasColumn('loss_events', 'she_event_id')) {
                    $table->unsignedBigInteger('she_event_id')->nullable()->after('risk_id');
                }
                if (!Schema::hasColumn('loss_events', 'event_title')) {
                    $table->string('event_title')->nullable()->after('loss_date');
                }
                if (!Schema::hasColumn('loss_events', 'description')) {
                    $table->text('description')->nullable()->after('event_title');
                }
                if (!Schema::hasColumn('loss_events', 'non_financial_impact')) {
                    $table->text('non_financial_impact')->nullable()->after('financial_impact');
                }
                if (!Schema::hasColumn('loss_events', 'root_cause')) {
                    $table->text('root_cause')->nullable()->after('non_financial_impact');
                }
                if (!Schema::hasColumn('loss_events', 'status')) {
                    $table->string('status')->default('Open')->after('root_cause');
                }
                if (!Schema::hasColumn('loss_events', 'evidence')) {
                    $table->string('evidence')->nullable()->after('status');
                }
            });
        }

        if (Schema::hasTable('bcm_plans')) {
            Schema::table('bcm_plans', function (Blueprint $table) {
                if (!Schema::hasColumn('bcm_plans', 'plan_reference')) {
                    $table->string('plan_reference')->nullable()->unique()->after('plan_id');
                }
                if (!Schema::hasColumn('bcm_plans', 'critical_process')) {
                    $table->string('critical_process')->nullable()->after('plan_name');
                }
                if (!Schema::hasColumn('bcm_plans', 'dependencies')) {
                    $table->text('dependencies')->nullable()->after('critical_process');
                }
                if (!Schema::hasColumn('bcm_plans', 'readiness_score')) {
                    $table->unsignedTinyInteger('readiness_score')->default(0)->after('owner_id');
                }
                if (!Schema::hasColumn('bcm_plans', 'scenario_test_notes')) {
                    $table->text('scenario_test_notes')->nullable()->after('readiness_score');
                }
                if (!Schema::hasColumn('bcm_plans', 'approved_by')) {
                    $table->string('approved_by', 36)->nullable()->after('next_test_date');
                }
                if (!Schema::hasColumn('bcm_plans', 'approved_at')) {
                    $table->dateTime('approved_at')->nullable()->after('approved_by');
                }
            });
        }
    }

    public function down(): void
    {
        // Additive compatibility migration; no destructive rollback.
    }
};
