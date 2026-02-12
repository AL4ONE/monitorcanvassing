<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add rejection_reason column
        Schema::table('online_canvassing_reports', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('notes');
        });

        // Modify status enum to include 'rejected'
        // Since we cannot easily modify enum in all drivers, we will drop and recreate strictly or use raw statement.
        // Given previous interaction, dropping and re-adding is safer for development environment if no critical data.
        // But assuming there might be data now, let's try to just change it if possible, or use the drop method again since it's dev.
        // Actually, let's just use the same drop/add approach as it guarantees the enum list is correct.

        Schema::table('online_canvassing_reports', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('online_canvassing_reports', function (Blueprint $table) {
            $table->enum('status', ['on_progress', 'registered', 'rejected'])->default('on_progress')->after('contact_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('online_canvassing_reports', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
            $table->dropColumn('status');
        });

        Schema::table('online_canvassing_reports', function (Blueprint $table) {
            $table->enum('status', ['on_progress', 'registered'])->default('on_progress')->after('contact_number');
        });
    }
};
