@extends(backpack_view('blank'))

@section('content')
@php
    $clientId = old('oauth_client_id', $integration?->oauth_client_id);
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h2 class="mb-0">Configuración de Google Drive</h2>
        <small class="text-muted">Crea la aplicación OAuth, guarda sus credenciales y conecta la cuenta que tiene acceso a los cuadros.</small>
    </div>
    <a class="btn btn-outline-secondary mt-2 mt-md-0" href="{{ route('prevalidacion.sources.index') }}">
        <i class="la la-arrow-left"></i> Volver a fuentes
    </a>
</div>

@foreach(['success', 'warning', 'error'] as $type)
    @if(session($type))
        <div class="alert alert-{{ $type === 'error' ? 'danger' : $type }}">{{ session($type) }}</div>
    @endif
@endforeach

<div class="alert alert-warning border-warning mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div class="mr-3">
            <strong><i class="la la-exclamation-triangle"></i> ¿Google muestra “Error 403: access_denied”?</strong><br>
            <span>Si la aplicación está en modo Pruebas, agrega primero la cuenta de Gmail que vas a conectar en <strong>Audience → Test users</strong> del mismo proyecto que generó estas credenciales.</span>
        </div>
        <a class="btn btn-warning mt-2 mt-lg-0" href="https://console.cloud.google.com/auth/audience" target="_blank" rel="noopener">
            Abrir Audience en Google <i class="la la-external-link-alt"></i>
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-5 mb-4">
        <div class="card h-100">
            <div class="card-header"><strong>Guía para crear el acceso en Google</strong></div>
            <div class="card-body oauth-guide">
                <div class="oauth-step">
                    <span class="oauth-number">1</span>
                    <div>
                        <strong>Crea o selecciona un proyecto</strong>
                        <p>Entra con la cuenta que administrará la integración y abre un proyecto en Google Cloud.</p>
                        <a href="https://console.cloud.google.com/projectcreate" target="_blank" rel="noopener">Abrir Google Cloud <i class="la la-external-link-alt"></i></a>
                    </div>
                </div>
                <div class="oauth-step">
                    <span class="oauth-number">2</span>
                    <div>
                        <strong>Habilita las dos API</strong>
                        <p>Activa <em>Google Sheets API</em> y <em>Google Drive API</em> en ese mismo proyecto.</p>
                        <a href="https://console.cloud.google.com/apis/library/sheets.googleapis.com" target="_blank" rel="noopener">Habilitar Sheets</a>
                        <span class="text-muted mx-1">·</span>
                        <a href="https://console.cloud.google.com/apis/library/drive.googleapis.com" target="_blank" rel="noopener">Habilitar Drive</a>
                    </div>
                </div>
                <div class="oauth-step">
                    <span class="oauth-number">3</span>
                    <div>
                        <strong>Configura Google Auth Platform</strong>
                        <p>En <em>Branding</em> registra el nombre de Integra y los correos solicitados. En <em>Audience</em> elige Interno si toda la organización usa Google Workspace; de lo contrario, Externo. Si el estado es <strong>Testing</strong>, entra a <strong>Test users → Add users</strong>, escribe exactamente el Gmail que conectarás y guarda el cambio.</p>
                        <a href="https://console.cloud.google.com/auth/overview" target="_blank" rel="noopener">Abrir Google Auth Platform <i class="la la-external-link-alt"></i></a>
                    </div>
                </div>
                <div class="oauth-step">
                    <span class="oauth-number">4</span>
                    <div>
                        <strong>Crea el cliente OAuth</strong>
                        <p>En <em>Clients</em>, selecciona <strong>Create client</strong> y usa el tipo <strong>Web application</strong>.</p>
                        <a href="https://console.cloud.google.com/auth/clients" target="_blank" rel="noopener">Crear cliente OAuth <i class="la la-external-link-alt"></i></a>
                    </div>
                </div>
                <div class="oauth-step">
                    <span class="oauth-number">5</span>
                    <div>
                        <strong>Registra la URL de retorno</strong>
                        <p>Copia exactamente la URL mostrada a la derecha y agrégala en <em>Authorized redirect URIs</em>. No la registres como origen JavaScript.</p>
                    </div>
                </div>
                <div class="oauth-step">
                    <span class="oauth-number">6</span>
                    <div>
                        <strong>Guarda y conecta</strong>
                        <p>Copia el Client ID y Client Secret en el formulario. Después de guardarlos, autoriza la cuenta de Google que puede leer y editar los cuadros.</p>
                    </div>
                </div>

                <div class="alert alert-light border mb-0">
                    <i class="la la-shield-alt text-primary"></i>
                    Integra solicita edición de hojas y lectura de metadatos de Drive. <strong>No solicita acceso a correos de Gmail.</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7 mb-4">
        <div class="card mb-3">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                <strong>Credenciales OAuth 2.0</strong>
                <div>
                    <span class="badge bg-{{ $configured ? 'success' : 'warning' }}">{{ $configured ? 'Credenciales configuradas' : 'Credenciales pendientes' }}</span>
                    <span class="badge bg-{{ $connected ? 'success' : 'secondary' }}">{{ $connected ? 'Cuenta conectada' : 'Cuenta sin conectar' }}</span>
                </div>
            </div>
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger">
                        <strong>Revisa la información:</strong>
                        <ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <div class="form-group">
                    <label for="callback_url"><strong>URI de redirección autorizada</strong></label>
                    <div class="input-group">
                        <input id="callback_url" class="form-control" type="text" value="{{ $callbackUrl }}" readonly>
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" onclick="copyCallback(this)"><i class="la la-copy"></i> Copiar</button>
                        </div>
                    </div>
                    <small class="form-text text-muted">Debe coincidir exactamente con la registrada en Google Cloud, incluido http/https, dominio y puerto.</small>
                </div>

                <form method="POST" action="{{ route('prevalidacion.google.credentials') }}" autocomplete="off">
                    @csrf
                    @method('PUT')
                    <div class="form-group">
                        <label for="oauth_client_id"><strong>Google Client ID</strong></label>
                        <input id="oauth_client_id" name="oauth_client_id" class="form-control @error('oauth_client_id') is-invalid @enderror" type="text" value="{{ $clientId }}" placeholder="000000000000-xxxx.apps.googleusercontent.com" required>
                    </div>
                    <div class="form-group">
                        <label for="oauth_client_secret"><strong>Google Client Secret</strong></label>
                        <input id="oauth_client_secret" name="oauth_client_secret" class="form-control @error('oauth_client_secret') is-invalid @enderror" type="password" placeholder="{{ $configured ? 'Déjalo vacío para conservar el secreto actual' : 'Ingresa el secreto generado por Google' }}" {{ $configured ? '' : 'required' }} autocomplete="new-password">
                        @if($configured)<small class="form-text text-muted">El secreto existente no se muestra. Solo escribe uno si deseas reemplazarlo.</small>@endif
                    </div>
                    <div class="alert alert-info py-2">
                        <i class="la la-lock"></i> Las credenciales se guardan cifradas con la llave de seguridad de Integra y el Client Secret nunca vuelve a mostrarse en pantalla.
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="la la-save"></i> Guardar credenciales</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Cuenta autorizada</strong></div>
            <div class="card-body">
                @if($connected)
                    <div class="d-flex flex-wrap justify-content-between align-items-center">
                        <div class="mb-2 mb-md-0">
                            <span class="badge bg-success">Conectada</span>
                            <strong>{{ $integration->account_email ?: 'Cuenta de Google' }}</strong><br>
                            <small class="text-muted">Conectada {{ $integration->connected_at?->format('d/m/Y H:i') }}</small>
                        </div>
                        <form method="POST" action="{{ route('prevalidacion.google.disconnect') }}" onsubmit="return confirm('¿Desconectar esta cuenta de Google? Las credenciales OAuth se conservarán.');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-outline-danger"><i class="la la-unlink"></i> Desconectar cuenta</button>
                        </form>
                    </div>
                @elseif($configured)
                    <p class="mb-3">Las credenciales están listas. Ahora inicia sesión con la cuenta que tiene acceso a los cuadros de Google Sheets.</p>
                    <div class="alert alert-light border py-2">
                        <strong>Antes de conectar:</strong> si Google indica que Integra está en pruebas, confirma que este correo aparezca en <a href="https://console.cloud.google.com/auth/audience" target="_blank" rel="noopener">Audience → Test users</a>. En modo Testing la autorización caduca a los 7 días.
                    </div>
                    <a class="btn btn-danger" href="{{ route('prevalidacion.google.redirect') }}"><i class="la la-google"></i> Conectar cuenta de Google</a>
                @else
                    <div class="alert alert-warning mb-0">Primero guarda el Client ID y Client Secret para habilitar la conexión.</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('after_styles')
<style>
    .oauth-guide { padding-left: 1.35rem; }
    .oauth-step { position: relative; display: flex; gap: 1rem; padding: 0 0 1.5rem 0; }
    .oauth-step:not(:last-of-type)::before { content: ''; position: absolute; left: 1rem; top: 2rem; bottom: 0; width: 2px; background: #dfe3e8; }
    .oauth-number { position: relative; z-index: 1; flex: 0 0 2rem; height: 2rem; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; color: #fff; background: #1967d2; font-weight: 700; }
    .oauth-step p { margin: .25rem 0 .35rem; color: #5f6368; }
</style>
@endpush

@push('after_scripts')
<script>
function copyCallback(button) {
    var input = document.getElementById('callback_url');
    navigator.clipboard.writeText(input.value).then(function () {
        var original = button.innerHTML;
        button.innerHTML = '<i class="la la-check"></i> Copiada';
        setTimeout(function () { button.innerHTML = original; }, 1800);
    });
}
</script>
@endpush
