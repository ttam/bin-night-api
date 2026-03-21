<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }

    public function up(): void
    {
        Schema::create('notifications', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('house_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('sms');
            $table->string('target');
            $table->integer('lead_days')->default(0);
            $table->time('send_time');
            $table->timestamps();
        });
    }
};
