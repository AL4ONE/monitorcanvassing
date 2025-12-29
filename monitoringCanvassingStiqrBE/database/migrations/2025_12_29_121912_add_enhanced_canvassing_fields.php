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
        // 1. Add contact_number to prospects
        Schema::table('prospects', function (Blueprint $table) {
            $table->string('contact_number')->nullable()->after('instagram_link');
        });

        // 2. Add new fields to canvassing_cycles
        Schema::table('canvassing_cycles', function (Blueprint $table) {
            $table->date('last_followup_date')->nullable()->after('status');
            $table->date('next_followup_date')->nullable()->after('last_followup_date');
            $table->text('next_action')->nullable()->after('next_followup_date');
        });

        // 3. Modify status enum (make it string to support new values flexibly)
        $driver = DB::getDriverName();

        try {
            if ($driver === 'pgsql') {
                // Postgres syntax
                // Depending on Postgres version and current type, we might need USING.
                // Assuming it was limited set, casting to text then varchar is safe.
                DB::statement("ALTER TABLE canvassing_cycles ALTER COLUMN status TYPE VARCHAR(50) USING status::text");
                DB::statement("ALTER TABLE canvassing_cycles ALTER COLUMN status SET DEFAULT 'active'");
            } else {
                // MySQL syntax
                DB::statement("ALTER TABLE canvassing_cycles MODIFY COLUMN status VARCHAR(50) DEFAULT 'active'");
            }

            // Update existing values to new terminology
            DB::table('canvassing_cycles')->where('status', 'active')->update(['status' => 'ongoing']);
            DB::table('canvassing_cycles')->where('status', 'completed')->update(['status' => 'converted']);
            DB::table('canvassing_cycles')->where('status', 'invalid')->update(['status' => 'rejected']);

        } catch (\Exception $e) {
            // Log error but continue
        }

        // 4. Create cycle_status_logs table
        Schema::create('cycle_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canvassing_cycle_id')->constrained('canvassing_cycles')->onDelete('cascade');
            $table->string('old_status')->nullable();
            $table->string('new_status');
            $table->foreignId('changed_by')->constrained('users'); // User who made the change
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cycle_status_logs');

        Schema::table('canvassing_cycles', function (Blueprint $table) {
            $table->dropColumn(['last_followup_date', 'next_followup_date', 'next_action']);
            // Revert status to enum if possible, or leave as string
        });

        Schema::table('prospects', function (Blueprint $table) {
            $table->dropColumn('contact_number');
        });
    }
};
