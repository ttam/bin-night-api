<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function down(): void
    {
        Schema::dropIfExists('bins');
    }

    public function up(): void
    {
        Schema::create('bins', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('house_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('cycle');
            $table->date('start_date');
            $table->enum('day_of_week', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])->nullable();
            $table->tinyInteger('week_of_month')->nullable();
            $table->string('lid_color', 6)->nullable();
            $table->string('bin_color', 6)->nullable();
            $table->timestamps();
        });
    }
};
