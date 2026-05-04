<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bubbles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->string('label');
            $table->string('color')->default('#009ac7');

            $table->integer('x')->default(0);
            $table->integer('y')->default(0);

            $table->integer('size')->default(80);
            $table->integer('members')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bubbles');
    }
};