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
        // 1. Add new columns
        Schema::table('canvassing_group_prospects', function (Blueprint $table) {
            if (!Schema::hasColumn('canvassing_group_prospects', 'registered_at')) {
                $table->timestamp('registered_at')->nullable();
            }
            if (!Schema::hasColumn('canvassing_group_prospects', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable();
            }
            if (!Schema::hasColumn('canvassing_group_prospects', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable();
            }
        });

        // 2. Modify enum using raw SQL (standard for MySQL)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE canvassing_group_prospects MODIFY COLUMN status ENUM('on_progress', 'registered', 'rejected') DEFAULT 'on_progress'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('canvassing_group_prospects', function (Blueprint $table) {
            $table->dropColumn(['registered_at', 'rejected_at', 'rejection_reason']);
        });

        // Revert enum
        // Convert 'rejected' to 'on_progress' first to avoid data truncation error
        DB::table('canvassing_group_prospects')->where('status', 'rejected')->update(['status' => 'on_progress']);

        DB::statement("ALTER TABLE canvassing_group_prospects MODIFY COLUMN status ENUM('on_progress', 'registered') DEFAULT 'on_progress'");
    }
};
