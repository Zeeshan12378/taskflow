<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cycle_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->string('snapshot_type', 20);
            $table->unsignedInteger('total_tasks');
            $table->unsignedInteger('completed_tasks');
            $table->unsignedInteger('total_story_points');
            $table->unsignedInteger('completed_story_points');
            $table->timestamps();

            $table->unique(['cycle_id', 'snapshot_date', 'snapshot_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cycle_snapshots');
    }
};
