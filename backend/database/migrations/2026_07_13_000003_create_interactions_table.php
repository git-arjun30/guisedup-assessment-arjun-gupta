<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['view', 'reaction', 'reply']);
            $table->timestamps();

            $table->index('user_id');
            $table->index('post_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interactions');
    }
};
