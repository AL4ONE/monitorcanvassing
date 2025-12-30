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
            $table->string('channel')->nullable()->after('category');
        });

        Schema::table('canvassing_cycles', function (Blueprint $table) {
            $table->string('failure_reason')->nullable()->after('status');
            $table->text('failure_notes')->nullable()->after('failure_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('channel');
        });

        Schema::table('canvassing_cycles', function (Blueprint $table) {
            $table->dropColumn(['failure_reason', 'failure_notes']);
        });
    }
};
