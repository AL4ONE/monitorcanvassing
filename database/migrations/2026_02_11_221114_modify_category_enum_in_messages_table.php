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
            $driver = DB::getDriverName(); // 'mysql' or 'pgsql'

            if ($driver === 'pgsql') {
                // PostgreSQL: Laravel implements ENUMs as 'check' constraints on a TEXT column.
                // We must drop the old constraint and add a new one.
                DB::statement("ALTER TABLE messages DROP CONSTRAINT IF EXISTS messages_category_check");
                DB::statement("ALTER TABLE messages ADD CONSTRAINT messages_category_check CHECK (category::text = ANY (ARRAY['umkm_fb'::character varying, 'coffee_shop'::character varying, 'restoran'::character varying, 'product_digital'::character varying]::text[]))");
            } else {
                // MySQL: Use standard MODIFY COLUMN
                DB::statement("ALTER TABLE messages MODIFY COLUMN category ENUM('umkm_fb', 'coffee_shop', 'restoran', 'product_digital') NULL");
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $driver = DB::getDriverName();

            if ($driver === 'pgsql') {
                DB::statement("ALTER TABLE messages DROP CONSTRAINT IF EXISTS messages_category_check");
                DB::statement("ALTER TABLE messages ADD CONSTRAINT messages_category_check CHECK (category::text = ANY (ARRAY['umkm_fb'::character varying, 'coffee_shop'::character varying, 'restoran'::character varying]::text[]))");
            } else {
                DB::statement("ALTER TABLE messages MODIFY COLUMN category ENUM('umkm_fb', 'coffee_shop', 'restoran') NULL");
            }
        });
    }
};
