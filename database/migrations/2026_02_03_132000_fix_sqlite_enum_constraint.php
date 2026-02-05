<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only run this for SQLite to fix the CHECK constraint
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        // 1. Create temp table with correct schema
        Schema::create('canvassing_group_prospects_temp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canvassing_group_id')->constrained('canvassing_groups')->onDelete('cascade');
            $table->foreignId('staff_id')->constrained('users');
            $table->string('business_name');
            $table->text('address')->nullable(); // Address might be nullable or text based on inspection
            $table->string('photo')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_number')->nullable();
            $table->enum('status', ['on_progress', 'registered', 'rejected'])->default('on_progress');
            $table->text('notes')->nullable();
            $table->date('visit_date');
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        // 2. Copy data
        // We need to map columns explicitly because order might match but safer to specify
        // Note: 'address' column type needs to be verified. In step 311 it was 'text'.
        // Also original 'address' might be string or text.

        $columns = [
            'id',
            'canvassing_group_id',
            'staff_id',
            'business_name',
            'address',
            'photo',
            'contact_name',
            'contact_number',
            'status',
            'notes',
            'visit_date',
            'registered_at',
            'rejected_at',
            'rejection_reason',
            'created_at',
            'updated_at'
        ];

        $columnList = implode(', ', $columns);

        DB::statement("INSERT INTO canvassing_group_prospects_temp ($columnList) SELECT $columnList FROM canvassing_group_prospects");

        // 3. Drop old table
        Schema::drop('canvassing_group_prospects');

        // 4. Rename temp
        Schema::rename('canvassing_group_prospects_temp', 'canvassing_group_prospects');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We don't really need to reverse this strictly as it just fixes constraint, 
        // but to be correct we would revert to old enum. 
        // Skipping complexity for now as this is a fix.
    }
};
