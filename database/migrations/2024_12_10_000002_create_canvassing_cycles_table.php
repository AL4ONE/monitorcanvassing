<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('canvassing_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospect_id')->constrained('prospects')->onDelete('cascade');
            $table->foreignId('staff_id')->constrained('users')->onDelete('cascade');
            $table->date('start_date');
            $table->integer('current_stage')->default(0); // 0 = canvassing, 1-7 = follow up
            $table->enum('status', ['active', 'completed', 'invalid'])->default('active');
            $table->timestamps();
            
            $table->index(['prospect_id', 'staff_id']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('canvassing_cycles');
    }
};



