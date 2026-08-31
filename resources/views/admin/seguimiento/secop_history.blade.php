@php $updates = $entry->actualizacionesSecop()->latest('aplicado_at')->limit(20)->get(); @endphp
@if($entry->tipo === 'contrato' && $entry->vinculoSecop)
<div class="card mt-4 border-0 shadow-sm" style="border-radius:12px;overflow:hidden">
    <div class="card-header d-flex justify-content-between align-items-center" style="background:#f3edf9">
        <button class="btn btn-link text-start p-0 text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#secop-history-{{ $entry->id }}" aria-expanded="false" aria-controls="secop-history-{{ $entry->id }}">
            <strong style="color:#5f259f"><i class="la la-history"></i> Histórico de aplicaciones SECOP <i class="la la-angle-down"></i></strong><div class="small text-muted">Auditoría y reversión · pulsa para desplegar</div>
        </button>
        <span class="badge bg-secondary">{{ $updates->count() }} registros</span>
    </div>
    <div class="collapse" id="secop-history-{{ $entry->id }}"><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Fecha</th><th>Proceso</th><th>Resultado</th><th>Campos aplicados</th><th></th></tr></thead><tbody>
    @forelse($updates as $update)
        <tr><td>{{ $update->aplicado_at?->format('d/m/Y H:i') }}</td><td>{{ ucfirst($update->modo) }}</td><td><span class="badge bg-{{ $update->resultado==='revertido'?'warning':'success' }}">{{ $update->resultado }}</span></td><td>{{ implode(', ',collect($update->campos_aplicados ?? [])->map(fn($f)=>str_replace('_',' ',$f))->all()) }}</td><td class="text-end">@if($update->modo!=='reversion')<form method="POST" action="{{ route('seguimiento.secop.revert',[$entry,$update]) }}">@csrf<button class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Revertir esta actualización?')">Revertir</button></form>@endif</td></tr>
    @empty<tr><td colspan="5" class="text-center text-muted py-3">Todavía no hay aplicaciones registradas.</td></tr>@endforelse
    </tbody></table></div></div>
</div>
@endif
