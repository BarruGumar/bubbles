<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'posts_user_id_created_at_index');
        });

        Schema::table('community_posts', function (Blueprint $table) {
            $table->index(['bubble_id', 'created_at'], 'community_posts_bubble_id_created_at_index');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'created_at'], 'messages_conversation_id_created_at_index');
        });

        Schema::table('friends', function (Blueprint $table) {
            $table->index(['user_id', 'status'], 'friends_user_id_status_index');
            $table->index(['friend_id', 'status'], 'friends_friend_id_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_user_id_created_at_index');
        });

        Schema::table('community_posts', function (Blueprint $table) {
            $table->dropIndex('community_posts_bubble_id_created_at_index');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_conversation_id_created_at_index');
        });

        Schema::table('friends', function (Blueprint $table) {
            $table->dropIndex('friends_user_id_status_index');
            $table->dropIndex('friends_friend_id_status_index');
        });
    }
};
