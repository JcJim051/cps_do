<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Spatie\Permission\Models\Role;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('campaign:attach-personas {campaignId : ID de la campaña} {--ref= : ID de referencia para filtrar} {--all : Asociar todas las personas} {--set-origin : Si está vacío, asigna campaña como origen} {--dry-run : Solo muestra el total sin escribir}', function () {
    $campaignId = (int) $this->argument('campaignId');
    $refId = $this->option('ref');
    $all = (bool) $this->option('all');
    $setOrigin = (bool) $this->option('set-origin');
    $dryRun = (bool) $this->option('dry-run');

    if (!$all && !$refId) {
        $this->error('Debes usar --all o --ref=ID.');
        return self::FAILURE;
    }

    $campaign = DB::table('ejercicios_politicos')->where('id', $campaignId)->first();
    if (!$campaign) {
        $this->error('La campaña no existe.');
        return self::FAILURE;
    }

    $query = DB::table('personas')->select('personas.id');

    if ($refId) {
        $query->where(function ($q) use ($refId) {
            $q->where('referencia_id', $refId)
              ->orWhereExists(function ($sub) use ($refId) {
                  $sub->select(DB::raw(1))
                      ->from('persona_referencia')
                      ->whereColumn('persona_referencia.persona_id', 'personas.id')
                      ->where('persona_referencia.referencia_id', $refId);
              });
        });
    }

    $total = $query->count();
    $this->info("Personas encontradas: {$total}");

    if ($dryRun) {
        $this->info('Dry-run activado. No se realizaron cambios.');
        return self::SUCCESS;
    }

    $query->orderBy('id')->chunkById(1000, function ($personas) use ($campaignId, $setOrigin) {
        $rows = [];
        foreach ($personas as $persona) {
            $rows[] = [
                'ejercicio_politico_id' => $campaignId,
                'persona_id' => $persona->id,
            ];
        }

        if (!empty($rows)) {
            DB::table('ejercicio_politico_persona')->insertOrIgnore($rows);
        }

        if ($setOrigin) {
            DB::table('personas')
                ->whereIn('id', collect($personas)->pluck('id')->all())
                ->whereNull('ejercicio_politico_origen_id')
                ->update(['ejercicio_politico_origen_id' => $campaignId]);
        }
    });

    $this->info('Proceso finalizado correctamente.');
    return self::SUCCESS;
})->purpose('Asocia personas a campañas de forma masiva y segura');

Artisan::command('campaign:seed-teams {--campaign=Gobernacion 2023} {--dry-run} {--default-password=Coordinador2023!} {--no-users} {--coordinator-user-id=}', function () {
    $campaignName = $this->option('campaign');
    $dryRun = (bool) $this->option('dry-run');
    $defaultPassword = (string) $this->option('default-password');
    $noUsers = (bool) $this->option('no-users');
    $coordinatorUserId = $this->option('coordinator-user-id');

    $campaign = DB::table('ejercicios_politicos')->where('nombre', $campaignName)->first();
    if (!$campaign) {
        $this->error("La campaña '{$campaignName}' no existe.");
        return self::FAILURE;
    }

    if ($noUsers) {
        if (!$coordinatorUserId) {
            $this->error("Debes indicar --coordinator-user-id cuando usas --no-users.");
            return self::FAILURE;
        }
        $coordinatorUserId = (int) $coordinatorUserId;
        $existsUser = DB::table('users')->where('id', $coordinatorUserId)->exists();
        if (!$existsUser) {
            $this->error("El user_id indicado no existe.");
            return self::FAILURE;
        }
    } else {
        $role = Role::where('name', 'coordinador')->first();
        if (!$role) {
            $this->error("El rol 'coordinador' no existe.");
            return self::FAILURE;
        }
    }

    $referencias = DB::table('referencias')->orderBy('id')->get();
    $this->info('Referencias encontradas: ' . $referencias->count());

    if ($dryRun) {
        $this->info('Dry-run activado. No se realizaron cambios.');
        return self::SUCCESS;
    }

    foreach ($referencias as $ref) {
        if ($noUsers) {
            $userId = $coordinatorUserId;
        } else {
            $email = 'coordinador_' . $ref->id . '@local.test';
            $user = User::where('referencia_id', $ref->id)
                ->whereHas('roles', fn($q) => $q->where('name', 'coordinador'))
                ->first();

            if (!$user) {
                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => 'Coordinador ' . $ref->nombre,
                        'password' => Hash::make($defaultPassword),
                        'role_id' => $role->id,
                        'referencia_id' => $ref->id,
                    ]
                );
                $user->syncRoles(['coordinador']);
            }
            $userId = $user->id;
        }

        $teamName = 'Equipo ' . $ref->nombre;
        $equipo = DB::table('equipos_campania')
            ->where('ejercicio_politico_id', $campaign->id)
            ->where('nombre', $teamName)
            ->first();

        if (!$equipo) {
            $equipoId = DB::table('equipos_campania')->insertGetId([
                'ejercicio_politico_id' => $campaign->id,
                'coordinador_user_id' => $userId,
                'nombre' => $teamName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $equipoId = $equipo->id;
        }

        $personasIds = DB::table('persona_referencia')
            ->where('referencia_id', $ref->id)
            ->pluck('persona_id');

        $rowsEquipo = [];
        $rowsCampania = [];
        foreach ($personasIds as $personaId) {
            $rowsEquipo[] = [
                'equipo_campania_id' => $equipoId,
                'persona_id' => $personaId,
            ];
            $rowsCampania[] = [
                'ejercicio_politico_id' => $campaign->id,
                'persona_id' => $personaId,
            ];
        }

        if (!empty($rowsEquipo)) {
            DB::table('equipo_campania_persona')->insertOrIgnore($rowsEquipo);
            DB::table('ejercicio_politico_persona')->insertOrIgnore($rowsCampania);
        }
    }

    $this->info('Proceso finalizado correctamente.');
    return self::SUCCESS;
})->purpose('Crea coordinadores y equipos por referencia y asocia personas por persona_referencia');
