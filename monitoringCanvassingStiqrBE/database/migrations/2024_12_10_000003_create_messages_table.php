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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canvassing_cycle_id')->constrained('canvassing_cycles')->onDelete('cascade');
            $table->integer('stage')->default(0); // 0 = canvassing, 1-7 = follow up
            $table->string('screenshot_path');
            $table->string('screenshot_hash', 64)->unique(); // SHA-256 hash
            $table->string('ocr_instagram_username')->nullable();
            $table->text('ocr_message_snippet')->nullable();
            $table->date('ocr_date')->nullable();
            $table->timestamp('submitted_at');
            $table->enum('validation_status', ['pending', 'valid', 'invalid'])->default('pending');
            $table->text('invalid_reason')->nullable();
            $table->timestamps();

            $table->index(['canvassing_cycle_id', 'stage']);
            $table->index('validation_status');
            $table->index('submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};

