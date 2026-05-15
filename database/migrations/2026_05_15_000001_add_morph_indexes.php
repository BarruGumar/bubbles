<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('likes', function (Blueprint $table) {
            $table->index(['likeable_type', 'likeable_id'], 'likes_likeable_type_id_index');
            $table->index('user_id', 'likes_user_id_index');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->index(['commentable_type', 'commentable_id'], 'comments_commentable_type_id_index');
            $table->index('user_id', 'comments_user_id_index');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_notifiable_read_index');
        });
    }

    public function down(): void
    {
        Schema::table('likes', function (Blueprint $table) {
            $table->dropIndex('likes_likeable_type_id_index');
            $table->dropIndex('likes_user_id_index');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex('comments_commentable_type_id_index');
            $table->dropIndex('comments_user_id_index');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_notifiable_read_index');
        });
    }
};
