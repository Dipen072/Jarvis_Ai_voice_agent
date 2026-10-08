<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jarvis_command_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('raw_command', 500);
            $table->string('normalized_command', 500);
            $table->string('detected_intent', 100);
            $table->json('parameters')->nullable();
            $table->text('response_message')->nullable();
            $table->enum('execution_status', ['success', 'failed', 'blocked', 'unknown'])
                  ->default('unknown');
            $table->unsignedInteger('execution_time_ms')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('detected_intent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jarvis_command_histories');
    }
};
