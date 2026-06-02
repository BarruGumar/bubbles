<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_user', function (Blueprint $table) {
            $table->string('bg_preset', 30)->nullable()->after('is_muted');
            $table->string('bg_image_url')->nullable()->after('bg_preset');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_user', function (Blueprint $table) {
            $table->dropColumn(['bg_preset', 'bg_image_url']);
        });
    }
};
