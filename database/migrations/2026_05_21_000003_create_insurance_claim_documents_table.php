<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_claim_documents', function (Blueprint $table) {
            $table->uuid('document_id')->primary();
            $table->uuid('claim_id');
            $table->string('document_type'); // police_report, drivers_licence, pictures, release_form, quotation, proof_of_payment, other
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->integer('file_size')->nullable();
            $table->uuid('uploaded_by')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('claim_id')->references('claim_id')->on('insurance_claims')->onDelete('cascade');
            $table->index('claim_id');
            $table->index('document_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_claim_documents');
    }
};
