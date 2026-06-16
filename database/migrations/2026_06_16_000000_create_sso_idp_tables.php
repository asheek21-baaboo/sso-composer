<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_authorization_codes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('project_id');
            $table->uuid('device_id')->nullable();
            $table->string('redirect_uri_snapshot', 2048);
            $table->string('code_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['user_id', 'project_id']);
            $table->index('expires_at');
        });

        Schema::create('project_visits', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('project_id');
            $table->timestamp('visited_at');
            $table->timestamps();

            $table->index(['user_id', 'visited_at']);
        });

        Schema::create('user_activity', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('project_id');
            $table->uuid('device_id')->nullable();
            $table->string('jti', 72)->nullable()->unique();
            $table->timestamp('session_start');
            $table->timestamp('last_seen_at');
            $table->timestamp('session_end')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('ip', 45);
            $table->text('user_agent');
            $table->timestamps();

            $table->index(['user_id', 'project_id', 'device_id']);
            $table->index(['user_id', 'session_start']);
            $table->index('last_seen_at');
        });

        Schema::create('auth_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('action', 32);
            $table->string('ip', 45);
            $table->text('user_agent')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_audit_logs');
        Schema::dropIfExists('user_activity');
        Schema::dropIfExists('project_visits');
        Schema::dropIfExists('oauth_authorization_codes');
    }
};
