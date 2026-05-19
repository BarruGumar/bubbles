<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Composite index for poll/show queries: WHERE conversation_id = ? AND id > ? ORDER BY id
            $table->index(['conversation_id', 'id'], 'messages_conv_id_composite');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_conv_id_composite');
        });
    }
};
