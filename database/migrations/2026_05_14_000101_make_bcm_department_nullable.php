<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bcm_plans') && Schema::hasColumn('bcm_plans', 'department_id')) {
            DB::statement('ALTER TABLE bcm_plans MODIFY department_id CHAR(36) NULL');
        }
    }

    public function down(): void
    {
        // Compatibility migration; do not make the column non-null again.
    }
};
