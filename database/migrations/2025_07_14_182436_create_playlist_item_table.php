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
        Schema::create('playlist_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playlist_id')->constrained('playlist');
            $table->foreignId('media_id')->constrained('media');
            $table->enum('transition',['fade']);
            $table->integer('index');
            $table->integer('duration');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('playlist_item');
    }
};
