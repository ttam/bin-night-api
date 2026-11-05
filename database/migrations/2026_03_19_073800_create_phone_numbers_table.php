<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function down(): void
    {
        Schema::dropIfExists('phone_numbers');
    }

    public function up(): void
    {
        Schema::create('phone_numbers', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('number', 16);
            $table->string('status')->default('pending');
            $table->dateTime('verified_at')->nullable();
            $table->dateTimes();
            $table->softDeletesDatetime();

            $table->unique(['user_id', 'number']);
        });
    }
};
