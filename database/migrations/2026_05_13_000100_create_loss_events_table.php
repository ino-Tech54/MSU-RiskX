<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('loss_events')) {
            Schema::create('loss_events', function (Blueprint $table) {
                $table->charset = 'latin1';
                $table->collation = 'latin1_swedish_ci';
                $table->char('loss_id', 36)->primary();
                $table->string('loss_reference')->unique();
                $table->unsignedBigInteger('risk_id')->nullable();
                $table->unsignedBigInteger('she_event_id')->nullable();
                $table->char('department_id', 36)->nullable();
                $table->char('reported_by', 36)->nullable();
                $table->dateTime('loss_date');
                $table->string('event_title');
                $table->text('description')->nullable();
                $table->decimal('financial_impact', 15, 2)->default(0);
                $table->text('non_financial_impact')->nullable();
                $table->text('root_cause')->nullable();
                $table->string('status')->default('Open');
                $table->string('evidence')->nullable();
                $table->timestamps();

                $table->index(['loss_date', 'department_id']);
                $table->index('risk_id');
                $table->index('she_event_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loss_events');
    }
};
