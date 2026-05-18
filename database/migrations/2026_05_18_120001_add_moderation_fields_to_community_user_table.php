<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_user', function (Blueprint $table) {
            $table->enum('role', ['member', 'moderator', 'admin'])->default('member')->after('community_id');
            $table->enum('status', ['active', 'banned', 'muted'])->default('active')->after('role');
            $table->timestamp('joined_at')->nullable()->after('status');
            $table->timestamp('banned_at')->nullable()->after('joined_at');
            $table->timestamp('banned_until')->nullable()->after('banned_at');
            $table->foreignId('banned_by')->nullable()->constrained('users')->nullOnDelete()->after('banned_until');
            $table->string('ban_reason', 500)->nullable()->after('banned_by');
            $table->timestamp('muted_until')->nullable()->after('ban_reason');
            $table->foreignId('muted_by')->nullable()->constrained('users')->nullOnDelete()->after('muted_until');
            $table->string('mute_reason', 500)->nullable()->after('muted_by');

            $table->index(['community_id', 'status'], 'cu_community_status_idx');
            $table->index(['community_id', 'role'], 'cu_community_role_idx');
        });
    }

    public function down(): void
    {
        Schema::table('community_user', function (Blueprint $table) {
            $table->dropForeign(['banned_by']);
            $table->dropForeign(['muted_by']);
            $table->dropIndex('cu_community_status_idx');
            $table->dropIndex('cu_community_role_idx');
            $table->dropColumn([
                'role', 'status', 'joined_at',
                'banned_at', 'banned_until', 'banned_by', 'ban_reason',
                'muted_until', 'muted_by', 'mute_reason',
            ]);
        });
    }
};
