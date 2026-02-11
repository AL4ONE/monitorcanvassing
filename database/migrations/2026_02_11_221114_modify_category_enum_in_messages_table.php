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
        Schema::table('messages', function (Blueprint $table) {
            // Modify the ENUM column to include 'product_digital'
            // Using raw statement for maximum compatibility with existing data
            DB::statement("ALTER TABLE messages MODIFY COLUMN category ENUM('umkm_fb', 'coffee_shop', 'restoran', 'product_digital') NULL");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Revert back to original ENUM list
            // WARNING: This will fail if there are rows with 'product_digital'
            DB::statement("ALTER TABLE messages MODIFY COLUMN category ENUM('umkm_fb', 'coffee_shop', 'restoran') NULL");
        });
    }
};
