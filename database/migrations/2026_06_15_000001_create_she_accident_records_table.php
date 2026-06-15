<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('she_accident_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('iod_number', 100)->unique();
            $table->string('name_of_injured')->nullable();
            $table->string('day_of_week')->nullable();
            $table->date('date_of_injury')->nullable();
            $table->string('time_of_injury')->nullable();
            $table->string('age')->nullable();
            $table->string('designation')->nullable();
            $table->string('employment_status')->nullable();
            $table->string('nssa_claim_number')->nullable();
            $table->text('description_of_events')->nullable();
            $table->string('department')->nullable();
            $table->string('manager_supervisor')->nullable();
            $table->string('source_of_injury')->nullable();
            $table->string('location_work_area')->nullable();
            $table->string('part_of_body_injured')->nullable();
            $table->string('nature_of_injury')->nullable();
            $table->string('days_lost')->nullable();
            $table->string('medical_treatment')->nullable();
            $table->text('corrective_action')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('she_accident_records');
    }
};
