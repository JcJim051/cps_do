<?php

namespace App\Jobs;

use App\Models\SecopConciliacionLote;
use App\Services\SecopVinculacionMasivaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcesarVinculacionSecopExacta implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    // Debe permanecer por debajo de DB_QUEUE_RETRY_AFTER (180 por defecto)
    // para impedir que otro worker reserve el mismo bloque todavía activo.
    public int $timeout = 150;
    public array $backoff = [5, 20];

    public function __construct(public int $loteId)
    {
    }

    public function handle(SecopVinculacionMasivaService $service): void
    {
        if ($service->processNext($this->loteId)) {
            // El pequeño retraso permite que el trabajo actual cierre antes de
            // que otro trabajador tome el siguiente bloque del mismo lote.
            self::dispatch($this->loteId)->delay(now()->addSecond());
        }
    }

    public function failed(?\Throwable $exception): void
    {
        SecopConciliacionLote::query()->whereKey($this->loteId)
            ->whereIn('estado', ['pendiente', 'procesando'])
            ->update([
                'estado' => 'fallido',
                'ultimo_error' => $exception?->getMessage() ?: 'El proceso terminó inesperadamente.',
                'finalizado_at' => now(),
            ]);
    }
}
