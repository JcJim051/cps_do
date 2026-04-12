<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_vinculacion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
        });

        if (DB::table('tipos_vinculacion')->count() === 0) {
            DB::table('tipos_vinculacion')->insert([
                ['nombre' => 'LNR', 'created_at' => now(), 'updated_at' => now()],
                ['nombre' => 'Provisionalidad', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_vinculacion');
    }
};
