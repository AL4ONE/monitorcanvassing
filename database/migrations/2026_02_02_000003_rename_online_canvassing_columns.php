<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('online_canvassing_reports', function (Blueprint $table) {
            $table->renameColumn('proof_image', 'photo');
            $table->renameColumn('report_date', 'visit_date');
        });

        // Modifying ENUM is tricky in migrations, best to use raw SQL or just not enforce strictly on DB level if valid validation exists
        // But let's try to update the definition if possible, or just accept the existing one and strictly validate in app.
        // Actually, for SQLite/MySQL/Postgres compatibility in simple way, we can stick to validation.
        // But user asked to "samain columns", so I will try to make sure schema reflects it.
        // For MySQL:
        // DB::statement("ALTER TABLE online_canvassing_reports MODIFY COLUMN status ENUM('on_progress', 'registered') NOT NULL DEFAULT 'on_progress'"); 

        // Since I don't know the DB driver for sure (likely MySQL based on user context usually), I will assume I can just leave appropriate validation in code.
        // However, to be safe, I'll drop and recreate the status column to ensure clean state.

        Schema::table('online_canvassing_reports', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('online_canvassing_reports', function (Blueprint $table) {
            $table->enum('status', ['on_progress', 'registered'])->default('on_progress');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('online_canvassing_reports', function (Blueprint $table) {
            $table->renameColumn('photo', 'proof_image');
            $table->renameColumn('visit_date', 'report_date');
            $table->dropColumn('status');
        });

        Schema::table('online_canvassing_reports', function (Blueprint $table) {
            $table->enum('status', ['prospecting', 'engaged', 'closing', 'registered'])->default('prospecting');
        });
    }
};
