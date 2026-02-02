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
        Schema::create('canvassing_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');                        // Nama group
            $table->string('category');                    // Category (UMKM, dll)
            $table->integer('target_per_day');             // Target merchant per hari
            $table->date('start_date');                    // Tanggal mulai
            $table->date('end_date');                      // Tanggal selesai
            $table->enum('status', ['open', 'on_progress', 'closed', 'cancelled'])->default('open');
            $table->string('cancel_reason')->nullable();   // Alasan cancel (jika cancelled)
            $table->string('city');                        // Kota
            $table->string('district')->nullable();        // Kecamatan
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('canvassing_groups');
    }
};
