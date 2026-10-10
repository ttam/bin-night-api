<?php

declare(strict_types=1);

use App\Enums\Plan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->dropColumn('plan');
        });
    }

    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->string('plan', 32)->default(Plan::FREE->value)->after('remember_token');
        });
    }
};
