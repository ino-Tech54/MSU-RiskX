<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bcm_plans')) {
            Schema::create('bcm_plans', function (Blueprint $table) {
                $table->charset = 'latin1';
                $table->collation = 'latin1_swedish_ci';
                $table->char('plan_id', 36)->primary();
                $table->string('plan_reference')->unique();
                $table->string('plan_name');
                $table->string('critical_process');
                $table->text('dependencies')->nullable();
                $table->char('department_id', 36)->nullable();
                $table->unsignedBigInteger('risk_id')->nullable();
                $table->integer('rto_hours')->default(24);
                $table->integer('rpo_hours')->default(24);
                $table->string('plan_status')->default('Draft');
                $table->char('owner_id', 36)->nullable();
                $table->unsignedTinyInteger('readiness_score')->default(0);
                $table->text('scenario_test_notes')->nullable();
                $table->date('last_tested')->nullable();
                $table->date('next_test_date')->nullable();
                $table->char('approved_by', 36)->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->timestamps();

                $table->index(['department_id', 'plan_status']);
                $table->index('risk_id');
                $table->index('owner_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bcm_plans');
    }
};
