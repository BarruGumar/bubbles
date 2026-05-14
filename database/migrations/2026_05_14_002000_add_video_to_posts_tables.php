<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('video')->nullable()->after('image');
            $table->string('video_public_id')->nullable()->after('video');
        });

        Schema::table('community_posts', function (Blueprint $table) {
            $table->string('video')->nullable()->after('image');
            $table->string('video_public_id')->nullable()->after('video');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['video', 'video_public_id']);
        });

        Schema::table('community_posts', function (Blueprint $table) {
            $table->dropColumn(['video', 'video_public_id']);
        });
    }
};
