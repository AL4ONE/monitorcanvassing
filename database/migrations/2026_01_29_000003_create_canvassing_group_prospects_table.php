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
        Schema::create('canvassing_group_prospects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canvassing_group_id')->constrained('canvassing_groups')->onDelete('cascade');
            $table->foreignId('staff_id')->constrained('users');
            $table->string('business_name');               // Nama usaha
            $table->string('photo')->nullable();           // Path foto (S3)
            $table->string('contact_name')->nullable();    // Nama PIC
            $table->string('contact_number')->nullable();  // Nomor kontak
            $table->enum('status', ['on_progress', 'registered'])->default('on_progress');
            $table->text('notes')->nullable();             // Catatan
            $table->date('visit_date');                    // Tanggal kunjungan
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('canvassing_group_prospects');
    }
};
