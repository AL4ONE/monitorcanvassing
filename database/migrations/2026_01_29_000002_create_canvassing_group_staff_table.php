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
        Schema::create('canvassing_group_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canvassing_group_id')->constrained('canvassing_groups')->onDelete('cascade');
            $table->foreignId('staff_id')->constrained('users')->onDelete('cascade');
            $table->date('assigned_start_date');           // Tanggal mulai assignment
            $table->date('assigned_end_date');             // Tanggal selesai assignment
            $table->timestamps();

            $table->unique(['canvassing_group_id', 'staff_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('canvassing_group_staff');
    }
};
