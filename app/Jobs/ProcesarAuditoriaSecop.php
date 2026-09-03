<?php

namespace App\Jobs;

use App\Models\SecopAuditoriaLote;
use App\Services\SecopAuditoriaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcesarAuditoriaSecop implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public array $backoff = [5, 20];

    public function __construct(public int $loteId) {}

    public function handle(SecopAuditoriaService $service): void
    {
        if ($service->processNext($this->loteId)) {
            self::dispatch($this->loteId)->delay(now()->addSecond());
        }
    }

    public function failed(?\Throwable $exception): void
    {
        SecopAuditoriaLote::query()->whereKey($this->loteId)
            ->whereIn('estado', ['pendiente', 'procesando'])
            ->update([
                'estado' => 'fallido',
                'errores' => DB::raw('errores + 1'),
                'ultimo_error' => 'SECOP no respondió después de 3 intentos. La persona quedó pendiente y puede volver a auditarse. Detalle: '
                    .($exception?->getMessage() ?: 'La auditoría terminó inesperadamente.'),
                'finalizado_at' => now(),
            ]);
    }
}
