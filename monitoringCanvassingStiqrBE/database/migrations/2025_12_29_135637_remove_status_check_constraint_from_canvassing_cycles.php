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
        // Drop the check constraint if it exists.
        // This is specific to Postgres usually, but SQLite also supports check constraints which might need recreation of table.
        // Given the error is Postgres, we target Postgres syntax.

        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE canvassing_cycles DROP CONSTRAINT IF EXISTS canvassing_cycles_status_check");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('canvassing_cycles', function (Blueprint $table) {
            //
        });
    }
};
