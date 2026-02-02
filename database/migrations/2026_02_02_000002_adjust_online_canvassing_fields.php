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
        Schema::table('online_canvassing_reports', function (Blueprint $table) {
            $table->dropColumn('platform');
            $table->renameColumn('contact_info', 'contact_number');
            $table->string('contact_name')->nullable()->after('business_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('online_canvassing_reports', function (Blueprint $table) {
            $table->enum('platform', ['WhatsApp', 'Instagram', 'Facebook', 'TikTok', 'Other'])->nullable();
            $table->renameColumn('contact_number', 'contact_info');
            $table->dropColumn('contact_name');
        });
    }
};
