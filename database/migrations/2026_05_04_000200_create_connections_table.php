<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('from_bubble_id')->constrained('bubbles')->cascadeOnDelete();
            $table->foreignId('to_bubble_id')->constrained('bubbles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['from_bubble_id', 'to_bubble_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connections');
    }
};
