<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_punishments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issued_by')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['warning', 'mute', 'suspension', 'ban']);
            $table->string('reason', 1000);
            $table->text('notes')->nullable();
            $table->timestamp('starts_at')->useCurrent();
            $table->timestamp('ends_at')->nullable(); // null = permanente
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revoked_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type', 'revoked_at'], 'up_user_type_revoked_idx');
            $table->index(['user_id', 'ends_at'], 'up_user_ends_idx');
            $table->index('issued_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_punishments');
    }
};
