<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->fullText(['name', 'username'], 'users_search_fulltext');
        });

        Schema::table('bubbles', function (Blueprint $table) {
            $table->fullText(['label', 'community_title', 'community_description'], 'bubbles_search_fulltext');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->fullText(['content'], 'posts_search_fulltext');
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('users', fn (Blueprint $table) => $table->dropFullText('users_search_fulltext'));
        Schema::table('bubbles', fn (Blueprint $table) => $table->dropFullText('bubbles_search_fulltext'));
        Schema::table('posts', fn (Blueprint $table) => $table->dropFullText('posts_search_fulltext'));
    }
};
