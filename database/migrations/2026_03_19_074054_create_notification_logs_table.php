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
            $table->foreignUuid('notification_id')->constrained()->cascadeOnDelete();
            $table->dateTime('scheduled_for');
            $table->string('status');
            $table->dateTime('sent_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->dateTimes();

            $table->unique(['notification_id', 'scheduled_for']);
        });
    }
};
