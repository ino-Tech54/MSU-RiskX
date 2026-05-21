<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loss_events', function (Blueprint $table) {
            $table->string('record_type', 20)->default('loss_event')->after('loss_reference');
            $table->string('case_number', 50)->nullable()->after('record_type');
            $table->string('priority_level', 20)->nullable()->after('case_number');
            $table->string('complainant')->nullable()->after('priority_level');
            $table->string('accused_person')->nullable()->after('complainant');
            $table->time('time_of_occurrence')->nullable()->after('accused_person');
            $table->string('case_against', 50)->nullable()->after('time_of_occurrence');
            $table->string('police_ref', 50)->nullable()->after('case_against');
            $table->string('case_category', 100)->nullable()->after('police_ref');
            $table->string('location')->nullable()->after('case_category');
            $table->string('property_involved')->nullable()->after('location');
            $table->decimal('estimate_value', 15, 2)->nullable()->after('property_involved');
            $table->text('corrective_action')->nullable()->after('estimate_value');
            $table->string('action_owner')->nullable()->after('corrective_action');
            $table->string('quarter', 30)->nullable()->after('action_owner');

            $table->index('record_type');
            $table->index('case_number');
        });
    }

    public function down(): void
    {
        Schema::table('loss_events', function (Blueprint $table) {
            $table->dropIndex(['record_type']);
            $table->dropIndex(['case_number']);
            $table->dropColumn([
                'record_type', 'case_number', 'priority_level', 'complainant',
                'accused_person', 'time_of_occurrence', 'case_against', 'police_ref',
                'case_category', 'location', 'property_involved', 'estimate_value',
                'corrective_action', 'action_owner', 'quarter'
            ]);
        });
    }
};
