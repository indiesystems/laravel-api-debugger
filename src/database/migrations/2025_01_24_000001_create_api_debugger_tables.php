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
        // Debug sessions - controls who/what is being debugged
        Schema::connection($this->getConnection())->create('api_debug_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('label')->nullable(); // Optional description
            $table->boolean('active')->default(true)->index();
            $table->timestamp('expires_at')->index();
            $table->unsignedBigInteger('created_by')->nullable(); // Admin who enabled it
            $table->timestamps();

            // Unique constraint: one session per tenant+user combo
            $table->unique(['tenant_id', 'user_id'], 'api_debug_sessions_tenant_user_unique');
        });

        // API logs - the actual request/response data
        Schema::connection($this->getConnection())->create('api_logs', function (Blueprint $table) {
            $table->id();

            // Session reference
            $table->foreignId('api_debug_session_id')
                ->constrained('api_debug_sessions')
                ->cascadeOnDelete();

            // Identifiers
            $table->string('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->uuid('request_id')->index(); // Unique ID for this request

            // Request data
            $table->string('method', 10)->index();
            $table->text('url');
            $table->text('full_url'); // Including query string
            $table->string('route_name')->nullable()->index();
            $table->string('route_action')->nullable();
            $table->json('request_headers');
            $table->json('request_query')->nullable();
            $table->mediumText('request_body')->nullable();
            $table->string('request_content_type')->nullable();
            $table->unsignedInteger('request_size')->nullable(); // Bytes

            // Client info
            $table->string('ip_address', 45)->index();
            $table->text('user_agent')->nullable();

            // Response data
            $table->smallInteger('status_code')->nullable()->index();
            $table->json('response_headers')->nullable();
            $table->mediumText('response_body')->nullable();
            $table->string('response_content_type')->nullable();
            $table->unsignedInteger('response_size')->nullable(); // Bytes

            // Performance
            $table->float('duration_ms')->nullable()->index(); // Response time
            $table->float('memory_peak_mb')->nullable(); // Peak memory usage

            // Exception info (if any)
            $table->string('exception_class')->nullable();
            $table->text('exception_message')->nullable();
            $table->mediumText('exception_trace')->nullable();

            // Timestamps
            $table->timestamp('requested_at')->index();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            // Composite indexes for common queries
            $table->index(['tenant_id', 'requested_at']);
            $table->index(['user_id', 'requested_at']);
            $table->index(['status_code', 'requested_at']);
            $table->index(['method', 'requested_at']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('api_logs');
        Schema::connection($this->getConnection())->dropIfExists('api_debug_sessions');
    }
};
