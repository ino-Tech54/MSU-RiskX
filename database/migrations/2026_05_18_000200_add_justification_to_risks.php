<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risks', function (Blueprint $table) {
            if (!Schema::hasColumn('risks', 'likelihood_justification')) {
                $table->text('likelihood_justification')->nullable()->after('inherent_risk_score');
            }
            if (!Schema::hasColumn('risks', 'consequence_justification')) {
                $table->text('consequence_justification')->nullable()->after('likelihood_justification');
            }
        });
    }

    public function down(): void
    {
        Schema::table('risks', function (Blueprint $table) {
            $table->dropColumn(['likelihood_justification', 'consequence_justification']);
        });
    }
};
