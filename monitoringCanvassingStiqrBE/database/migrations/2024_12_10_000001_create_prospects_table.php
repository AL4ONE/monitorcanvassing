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
        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->string('instagram_username')->unique();
            $table->string('category')->nullable(); // Menengah, Kecil, etc
            $table->string('business_type')->nullable(); // Coffee Shop, etc
            $table->string('channel')->nullable(); // IG, etc
            $table->string('instagram_link')->nullable();
            $table->timestamps();
            
            $table->index('instagram_username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prospects');
    }
};

