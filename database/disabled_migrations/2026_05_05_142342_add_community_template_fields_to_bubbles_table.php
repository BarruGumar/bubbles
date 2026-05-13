<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bubbles', function (Blueprint $table): void {
            $table->string('community_title')->nullable()->after('label');
            $table->text('community_description')->nullable()->after('community_title');
            $table->string('community_cover_color')->nullable()->after('community_description');
            $table->string('community_tagline')->nullable()->after('community_cover_color');
            $table->json('community_guidelines')->nullable()->after('community_tagline');
        });
    }

    public function down(): void
    {
        Schema::table('bubbles', function (Blueprint $table): void {
            $table->dropColumn([
                'community_title',
                'community_description',
                'community_cover_color',
                'community_tagline',
                'community_guidelines',
            ]);
        });
    }
};
