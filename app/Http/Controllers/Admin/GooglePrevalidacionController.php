<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GoogleIntegration;
use App\Services\GoogleSheetsService;
use Illuminate\Http\Request;

class GooglePrevalidacionController extends Controller
{
    public function configuration()
    {
        $integration = GoogleIntegration::find(1);

        return view('admin.prevalidacion.google.configuration', [
            'integration' => $integration,
            'callbackUrl' => route('prevalidacion.google.callback'),
            'configured' => filled($integration?->oauth_client_id) && filled($integration?->oauth_client_secret),
            'connected' => filled($integration?->refresh_token) || filled($integration?->access_token),
        ]);
    }

    public function saveCredentials(Request $request)
    {
        $integration = GoogleIntegration::firstOrNew(['id' => 1]);
        $data = $request->validate([
            'oauth_client_id' => ['required', 'string', 'max:2000'],
            'oauth_client_secret' => [$integration->oauth_client_secret ? 'nullable' : 'required', 'string', 'max:2000'],
        ]);

        $integration->oauth_client_id = trim($data['oauth_client_id']);
        if (filled($data['oauth_client_secret'] ?? null)) {
            $integration->oauth_client_secret = trim($data['oauth_client_secret']);
        }
        $integration->save();

        return redirect()->route('prevalidacion.google.configuration')
            ->with('success', 'Credenciales OAuth guardadas de forma cifrada. Ya puedes conectar la cuenta de Google.');
    }

    public function redirect(GoogleSheetsService $google)
    {
        try {
            return redirect()->away($google->authorizationUrl());
        } catch (\Throwable $e) {
            return redirect()->route('prevalidacion.google.configuration')->with('error', $e->getMessage());
        }
    }

    public function callback(Request $request, GoogleSheetsService $google)
    {
        if ($request->filled('error')) {
            return redirect()->route('prevalidacion.google.configuration')->with('error', 'Google rechazó la conexión: '.$request->input('error'));
        }
        try {
            $google->connect((string) $request->input('code'), (string) $request->input('state'));
            return redirect()->route('prevalidacion.google.configuration')->with('success', 'Cuenta de Google Drive conectada. Ya puedes configurar y sincronizar los cuadros.');
        } catch (\Throwable $e) {
            return redirect()->route('prevalidacion.google.configuration')->with('error', 'No fue posible completar la conexión con Google: '.$e->getMessage());
        }
    }

    public function disconnect(GoogleSheetsService $google)
    {
        $google->disconnect();
        return back()->with('success', 'Cuenta de Google Drive desconectada.');
    }
}
