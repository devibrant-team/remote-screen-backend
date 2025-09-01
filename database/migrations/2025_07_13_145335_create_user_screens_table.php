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
        Schema::create('user_screens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->foreignId('screen_id')->nullable()->constrained('screens');
            $table->foreignId('group_id')->nullable()->constrained('groups');
            $table->foreignId('ratio_id')->nullable()->constrained('ratio');
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->boolean('is_assigned')->default(0);
            $table->boolean('is_extra');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_screens');
    }
};
