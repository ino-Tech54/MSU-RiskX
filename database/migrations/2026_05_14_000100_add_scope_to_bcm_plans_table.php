<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bcm_plans')) {
            Schema::table('bcm_plans', function (Blueprint $table) {
                if (!Schema::hasColumn('bcm_plans', 'scope_type')) {
                    $table->string('scope_type')->default('Process-only')->after('plan_name');
                }
                if (!Schema::hasColumn('bcm_plans', 'she_event_id')) {
                    $table->unsignedBigInteger('she_event_id')->nullable()->after('risk_id');
                }
            });
        }
    }

    public function down(): void
    {
        // Additive compatibility migration; no destructive rollback.
    }
};
