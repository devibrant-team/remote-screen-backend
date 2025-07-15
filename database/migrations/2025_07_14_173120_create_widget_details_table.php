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
            $table->enum('type',['oclock','weather']);
            $table->string('city');
            $table->decimal('x');
            $table->decimal('y');
            $table->decimal('width');
            $table->decimal('height');
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
