<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function down(): void
    {
        Schema::table('bins', static function (Blueprint $table): void {
            $table->dropUnique(['house_id', 'name']);
        });
    }

    public function up(): void
    {
        Schema::table('bins', static function (Blueprint $table): void {
            $table->unique(['house_id', 'name']);
        });
    }
};
