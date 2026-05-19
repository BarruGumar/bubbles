<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user','moderator','admin','site_owner','suspended') NOT NULL DEFAULT 'user'");
    }

    public function down(): void
    {
        // Move any site_owner back to admin before removing the value
        DB::table('users')->where('role', 'site_owner')->update(['role' => 'admin']);

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user','moderator','admin','suspended') NOT NULL DEFAULT 'user'");
    }
};
