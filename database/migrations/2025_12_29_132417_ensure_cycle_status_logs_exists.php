<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Disable transaction wrapping to ensure independent execution
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('cycle_status_logs')) {
            Schema::create('cycle_status_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('canvassing_cycle_id')->constrained('canvassing_cycles')->onDelete('cascade');
                $table->string('old_status')->nullable();
                $table->string('new_status');
                $table->foreignId('changed_by')->constrained('users'); // User who made the change
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We generally don't drop in this ensure migration to avoid conflict with the main one
        // if rollback happens.
    }
};
