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
        Schema::create('widget_details', function (Blueprint $table) {
            $table->id();
            $table->enum('type',['clock','weather']);
            $table->string('city');
            $table->enum('position',['center','top-right','top-left','bottom-right','bottom-left','center-right','center-left']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('widget_details');
    }
};
