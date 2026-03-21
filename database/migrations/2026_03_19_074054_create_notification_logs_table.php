<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }

    public function up(): void
    {
        Schema::create('notification_logs', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bin_id')->constrained()->cascadeOnDelete();
            $table->timestamp('sent_at');
            $table->date('scheduled_for');
            $table->timestamps();
        });
    }
};
