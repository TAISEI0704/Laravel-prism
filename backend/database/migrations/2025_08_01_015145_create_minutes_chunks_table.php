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
        Schema::create('minute_chunks', function (Blueprint $t) {
            $t->uuid('minute_id');
            $t->integer('idx');
            $t->text('chunk');
            $t->vector('embedding', 1536);  // pgvector column type
            $t->primary(['minute_id','idx']);
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
