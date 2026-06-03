<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // WHERE blocked_id = ? — para saber quem bloqueou um utilizador
        Schema::table('user_blocks', function (Blueprint $table) {
            $table->index('blocked_id', 'user_blocks_blocked_id_index');
        });

        // WHERE type = 'group' — filtragem de conversas por tipo
        Schema::table('conversations', function (Blueprint $table) {
            $table->index('type', 'conversations_type_index');
        });

        // WHERE community_id = ? — contagem de membros, queries por bolha
        Schema::table('community_user', function (Blueprint $table) {
            $table->index('community_id', 'community_user_community_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('user_blocks', function (Blueprint $table) {
            $table->dropIndex('user_blocks_blocked_id_index');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_type_index');
        });

        Schema::table('community_user', function (Blueprint $table) {
            $table->dropIndex('community_user_community_id_index');
        });
    }
};
