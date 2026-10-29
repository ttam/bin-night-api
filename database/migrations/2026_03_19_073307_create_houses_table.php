<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function down(): void
    {
        Schema::dropIfExists('houses');
    }

    public function up(): void
    {
        Schema::create('houses', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('bin_night', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']);
            $table->string('timezone')->default('Australia/Melbourne');
            $table->dateTimes();
            $table->softDeletesDatetime();

            $table->unique(['user_id', 'name']);
        });
    }
};
