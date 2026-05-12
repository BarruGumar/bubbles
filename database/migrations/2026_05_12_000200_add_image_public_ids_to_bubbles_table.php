<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bubbles', function (Blueprint $table) {
            $table->string('community_image_public_id')->nullable()->after('community_image');
            $table->string('community_banner_public_id')->nullable()->after('community_banner');
        });
    }

    public function down(): void
    {
        Schema::table('bubbles', function (Blueprint $table) {
            $table->dropColumn(['community_image_public_id', 'community_banner_public_id']);
        });
    }
};
