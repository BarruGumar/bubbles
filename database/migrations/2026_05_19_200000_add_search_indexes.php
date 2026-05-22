<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('name', 'users_name_search_idx');
        });

        Schema::table('bubbles', function (Blueprint $table) {
            $table->index('label', 'bubbles_label_search_idx');
            $table->index('community_title', 'bubbles_community_title_search_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_name_search_idx');
        });

        Schema::table('bubbles', function (Blueprint $table) {
            $table->dropIndex('bubbles_label_search_idx');
            $table->dropIndex('bubbles_community_title_search_idx');
        });
    }
};
