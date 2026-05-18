<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->id();
                $table->string('user_id', 36)->nullable();
                $table->string('action');
                $table->text('details')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('status', 20)->default('success');
                $table->timestamp('created_at')->nullable();
            });
            return;
        }

        Schema::table('activity_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('activity_logs', 'status')) {
                $table->string('status', 20)->default('success')->after('ip_address');
            }
            if (!Schema::hasColumn('activity_logs', 'details')) {
                $table->text('details')->nullable()->after('action');
            }
        });
    }

    public function down()
    {
        if (Schema::hasTable('activity_logs')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                if (Schema::hasColumn('activity_logs', 'status')) {
                    $table->dropColumn('status');
                }
                if (Schema::hasColumn('activity_logs', 'details')) {
                    $table->dropColumn('details');
                }
            });
        }
    }
};
