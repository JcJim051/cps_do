<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('google_integrations', function (Blueprint $table) {
            $table->text('oauth_client_id')->nullable()->after('account_email');
            $table->text('oauth_client_secret')->nullable()->after('oauth_client_id');
        });
    }

    public function down(): void
    {
        Schema::table('google_integrations', function (Blueprint $table) {
            $table->dropColumn(['oauth_client_id', 'oauth_client_secret']);
        });
    }
};
