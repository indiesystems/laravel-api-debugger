<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('api-debugger.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->table('api_debug_sessions', function (Blueprint $table) {
            $table->string('token', 64)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->table('api_debug_sessions', function (Blueprint $table) {
            $table->dropColumn('token');
        });
    }
};
