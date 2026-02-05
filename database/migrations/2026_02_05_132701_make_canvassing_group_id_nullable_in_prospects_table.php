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
        Schema::table('canvassing_group_prospects', function (Blueprint $table) {
            $table->dropForeign(['canvassing_group_id']);
            $table->unsignedBigInteger('canvassing_group_id')->nullable()->change();
            $table->foreign('canvassing_group_id')->references('id')->on('canvassing_groups')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('canvassing_group_prospects', function (Blueprint $table) {
            $table->dropForeign(['canvassing_group_id']);
            $table->unsignedBigInteger('canvassing_group_id')->nullable(false)->change();
            $table->foreign('canvassing_group_id')->references('id')->on('canvassing_groups')->onDelete('cascade');
        });
    }
};
