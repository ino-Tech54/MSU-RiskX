<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loss_events', function (Blueprint $table) {
            // Police report file upload
            $table->string('police_report_file', 255)->nullable()->after('police_ref');
            
            // Separate corrective action fields
            $table->text('corrective_action_recommendation')->nullable()->after('corrective_action');
            $table->text('corrective_action_taken')->nullable()->after('corrective_action_recommendation');
            
            // Financial tracking - estimated loss vs recovery
            $table->decimal('estimated_loss_value', 15, 2)->nullable()->after('estimate_value');
            $table->decimal('estimated_recovery_value', 15, 2)->nullable()->after('estimated_loss_value');
            
            // Misconduct categorization
            $table->string('misconduct_type', 50)->nullable()->after('case_category');
            
            // Case numbering prefix for configurable format
            $table->string('case_prefix', 10)->nullable()->after('case_number');
        });
    }

    public function down(): void
    {
        Schema::table('loss_events', function (Blueprint $table) {
            $table->dropColumn([
                'police_report_file',
                'corrective_action_recommendation',
                'corrective_action_taken',
                'estimated_loss_value',
                'estimated_recovery_value',
                'misconduct_type',
                'case_prefix'
            ]);
        });
    }
};
