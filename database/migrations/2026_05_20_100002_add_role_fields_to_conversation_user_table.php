<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_user', function (Blueprint $table) {
            $table->enum('role', ['owner', 'admin', 'member'])->default('member')->after('user_id');
            $table->timestamp('joined_at')->nullable()->after('last_read_at');
            $table->boolean('is_muted')->default(false)->after('joined_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_user', function (Blueprint $table) {
            $table->dropColumn(['role', 'joined_at', 'is_muted']);
        });
    }
};
