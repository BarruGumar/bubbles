<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bubbles', function (Blueprint $table) {
            $table->string('community_image')->nullable()->after('community_guidelines');
            $table->string('community_banner')->nullable()->after('community_image');
        });
    }

    public function down(): void
    {
        Schema::table('bubbles', function (Blueprint $table) {
            $table->dropColumn(['community_image', 'community_banner']);
        });
    }
};
