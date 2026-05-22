<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user','moderator','admin','site_owner','suspended') NOT NULL DEFAULT 'user'");

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('user','moderator','admin','site_owner','suspended'))");

            return;
        }

        // SQLite doesn't support MODIFY COLUMN for enum/check constraints in-place.
        // Keep migration non-failing in local/dev environments.
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user','moderator','admin','suspended') NOT NULL DEFAULT 'user'");

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('user','moderator','admin','suspended'))");

            return;
        }

        // SQLite no-op.
    }
};
