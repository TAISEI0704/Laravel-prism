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
        Schema::create('minutes_chunks', function (Blueprint $table) {
            $table->id();
            $table->uuid('minute_id');
            $table->unsignedInteger('idx');
            $table->text('chunk');
            $table->vector('embedding', 1536)->nullable();
            $table->timestamps();
            
            $table->foreign('minute_id')
                  ->references('minute_id')
                  ->on('meeting_minutes')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('minutes_chunks');
    }
};
