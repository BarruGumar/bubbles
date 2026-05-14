<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->morphs('reportable');
            $table->string('reason', 500);
            $table->enum('status', ['pending', 'resolved', 'dismissed'])->default('pending');
            $table->text('admin_note')->nullable();
            $table->timestamps();
            $table->unique(['reporter_id', 'reportable_type', 'reportable_id'], 'reports_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
