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
        Schema::create('online_canvassing_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('users')->onDelete('cascade');
            $table->string('business_name');
            $table->enum('platform', ['WhatsApp', 'Instagram', 'Facebook', 'TikTok', 'Other']);
            $table->string('contact_info'); // No WA / Username IG / Link
            $table->enum('status', ['prospecting', 'engaged', 'closing', 'registered'])->default('prospecting');
            $table->text('notes')->nullable();
            $table->string('proof_image')->nullable(); // Path to S3 or local
            $table->date('report_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('online_canvassing_reports');
    }
};
