<?php

namespace Tests\Feature;

use App\Models\GoogleIntegration;
use App\Services\GoogleSheetsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GoogleSheetsServiceTest extends TestCase
{
    private string $originalDatabaseConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDatabaseConnection = (string) config('database.default');
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('google_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('account_email')->nullable();
            $table->text('oauth_client_id')->nullable();
            $table->text('oauth_client_secret')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('google_integrations');
        DB::purge('sqlite');
        config(['database.default' => $this->originalDatabaseConnection]);
        parent::tearDown();
    }

    public function test_invalid_grant_clears_only_the_stale_authorization(): void
    {
        GoogleIntegration::query()->create([
            'account_email' => 'cuenta@example.com',
            'oauth_client_id' => 'client-id',
            'oauth_client_secret' => 'client-secret',
            'access_token' => 'expired-access-token',
            'refresh_token' => 'revoked-refresh-token',
            'token_expires_at' => now()->subHour(),
            'scopes' => ['spreadsheets'],
            'connected_at' => now()->subDay(),
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'Bad Request',
            ], 400),
        ]);

        try {
            app(GoogleSheetsService::class)->values('spreadsheet-id', 'Hoja');
            $this->fail('La autorización revocada debía producir un error.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Vuelve a conectar la cuenta', $e->getMessage());
        }

        $integration = GoogleIntegration::query()->firstOrFail();
        $this->assertNull($integration->account_email);
        $this->assertNull($integration->access_token);
        $this->assertNull($integration->refresh_token);
        $this->assertNull($integration->connected_at);
        $this->assertSame('client-id', $integration->oauth_client_id);
        $this->assertSame('client-secret', $integration->oauth_client_secret);
    }
}
