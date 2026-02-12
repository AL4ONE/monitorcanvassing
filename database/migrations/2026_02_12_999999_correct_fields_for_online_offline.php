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
        // 1. Add fields to 'messages' (Online DM)
        Schema::table('messages', function (Blueprint $table) {
            $table->string('channel_category')->nullable()->after('channel');
            $table->boolean('has_website')->default(false)->after('channel_category');
            $table->string('website_url')->nullable()->after('has_website');
            $table->boolean('has_payment_gateway')->default(false)->after('website_url');
        });

        // 2. Adjust 'online_canvassing_reports' (Offline Out Group)
        Schema::table('online_canvassing_reports', function (Blueprint $table) {
            // Add category (UMKM F&B, Coffee Shop, etc.)
            $table->string('category')->nullable()->after('business_name');

            // Remove fields that belong to 'messages' (Online)
            $table->dropColumn([
                'channel_category',
                'channel',
                'has_website',
                'website_url',
                'has_payment_gateway'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn([
                'channel_category',
                'has_website',
                'website_url',
                'has_payment_gateway'
            ]);
        });

        Schema::table('online_canvassing_reports', function (Blueprint $table) {
            $table->dropColumn('category');
            $table->string('channel_category')->nullable();
            $table->string('channel')->nullable();
            $table->boolean('has_website')->default(false);
            $table->string('website_url')->nullable();
            $table->boolean('has_payment_gateway')->default(false);
        });
    }
};
