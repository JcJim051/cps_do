<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE equipo_campania_persona MODIFY prioridad VARCHAR(30) NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE equipo_campania_persona MODIFY prioridad VARCHAR(10) NULL");
    }
};
