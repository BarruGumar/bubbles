<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') return;

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user','moderator','admin','site_owner','suspended','banned') NOT NULL DEFAULT 'user'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') return;

        DB::table('users')->where('role', 'banned')->update(['role' => 'user']);

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user','moderator','admin','site_owner','suspended') NOT NULL DEFAULT 'user'");
    }
};
