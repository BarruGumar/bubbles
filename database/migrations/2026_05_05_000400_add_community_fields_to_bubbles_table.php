<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bubbles', function (Blueprint $table) {
            $table->string('community_title')->nullable()->after('label');
            $table->string('community_tagline', 160)->nullable()->after('community_title');
            $table->text('community_description')->nullable()->after('community_tagline');
            $table->string('community_cover_color', 40)->nullable()->after('community_description');
            $table->json('community_guidelines')->nullable()->after('community_cover_color');
        });
    }

    public function down(): void
    {
        Schema::table('bubbles', function (Blueprint $table) {
            $table->dropColumn([
                'community_title',
                'community_tagline',
                'community_description',
                'community_cover_color',
                'community_guidelines',
            ]);
        });
    }
};
