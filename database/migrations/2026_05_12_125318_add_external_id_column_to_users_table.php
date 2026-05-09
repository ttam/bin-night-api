<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->dropColumn('external_id');
        });
    }

    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->string('external_id', 32)->after('id')->nullable()->unique()->index();
        });
    }
};
