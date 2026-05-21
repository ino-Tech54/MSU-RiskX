<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_claims', function (Blueprint $table) {
            $table->uuid('claim_id')->primary();
            $table->string('claim_number')->unique();
            $table->date('date_received')->nullable();
            $table->string('claim_type')->nullable(); // Motor, Property, Equipment, Fidelity, Livestock, etc.
            $table->string('claim_description')->nullable(); // Vehicle reg, item description
            $table->string('quotation_1')->nullable();
            $table->string('quotation_2')->nullable();
            $table->string('quotation_3')->nullable();
            $table->enum('police_report', ['YES', 'NO', 'N/A'])->default('N/A');
            $table->enum('drivers_licence', ['YES', 'NO', 'N/A'])->default('N/A');
            $table->enum('pictures', ['YES', 'NO'])->default('NO');
            $table->enum('release_form', ['YES', 'NO'])->default('NO');
            $table->string('status')->default('Open'); // Open, Quotation, Approved, Completed, Under Investigation
            $table->enum('pop', ['YES', 'NO', 'RECEIVED', 'N/A'])->default('N/A'); // Proof of Payment
            $table->uuid('department_id')->nullable();
            $table->string('claimant_name')->nullable();
            $table->decimal('claim_value', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->uuid('reported_by')->nullable();
            $table->timestamps();

            $table->index('claim_type');
            $table->index('status');
            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_claims');
    }
};
