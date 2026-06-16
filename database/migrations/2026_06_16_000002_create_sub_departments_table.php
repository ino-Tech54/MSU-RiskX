<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('department_id', 36);
            $table->string('name');
            $table->timestamps();

            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_departments');
    }
};
