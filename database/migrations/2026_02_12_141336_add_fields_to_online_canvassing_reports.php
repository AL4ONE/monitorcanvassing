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
            $table->string('channel_category')->nullable()->after('status'); // Sosmed, Marketplace
            $table->string('channel')->nullable()->after('channel_category'); // Instagram, Shopee
            $table->boolean('has_website')->default(false)->after('channel');
            $table->string('website_url')->nullable()->after('has_website');
            $table->boolean('has_payment_gateway')->default(false)->after('website_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('online_canvassing_reports', function (Blueprint $table) {
            $table->dropColumn([
                'channel_category',
                'channel',
                'has_website',
                'website_url',
                'has_payment_gateway'
            ]);
        });
    }
};
