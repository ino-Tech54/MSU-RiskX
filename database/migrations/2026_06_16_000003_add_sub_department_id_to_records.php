<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add sub_department_id to risks
        if (Schema::hasTable('risks') && !Schema::hasColumn('risks', 'sub_department_id')) {
            Schema::table('risks', function (Blueprint $table) {
                $table->uuid('sub_department_id')->nullable()->after('department_id');
            });
        }

        // Add sub_department_id to she_events
        if (Schema::hasTable('she_events') && !Schema::hasColumn('she_events', 'sub_department_id')) {
            Schema::table('she_events', function (Blueprint $table) {
                $table->uuid('sub_department_id')->nullable()->after('department_id');
            });
        }

        // Add department_id (FK) and sub_department_id to she_accident_records
        if (Schema::hasTable('she_accident_records')) {
            Schema::table('she_accident_records', function (Blueprint $table) {
                if (!Schema::hasColumn('she_accident_records', 'department_id')) {
                    $table->char('department_id', 36)->nullable()->after('department');
                }
                if (!Schema::hasColumn('she_accident_records', 'sub_department_id')) {
                    $table->uuid('sub_department_id')->nullable()->after('department_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('risks')) {
            Schema::table('risks', function (Blueprint $table) {
                $table->dropColumn('sub_department_id');
            });
        }
        if (Schema::hasTable('she_events')) {
            Schema::table('she_events', function (Blueprint $table) {
                $table->dropColumn('sub_department_id');
            });
        }
        if (Schema::hasTable('she_accident_records')) {
            Schema::table('she_accident_records', function (Blueprint $table) {
                $table->dropColumn(['department_id', 'sub_department_id']);
            });
        }
    }
};
