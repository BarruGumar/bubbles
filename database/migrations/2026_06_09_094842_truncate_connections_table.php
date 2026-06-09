<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('connections')->truncate();
    }

    public function down(): void
    {
        // Irreversível — dados de conexões manuais não são restaurados
    }
};
