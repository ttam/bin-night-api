<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Auth\AuthenticatedUser;
use App\DTO\Bin\CreateBin;
use App\DTO\Bin\UpdateBin;
use App\Enums\Cycle;
use App\Models\Bin;
use App\Models\House;
use App\Models\Notification;
use App\Repositories\BinRepository;
use App\Services\Schedule\ScheduleService;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use Tests\Unit\TestCase;

final class BinRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        $this->bootEloquent()->migrate();
    }

    public function testCreateBin(): void
    {
        DB::table('users')->insert([['id' => '1', 'external_id' => 'user_111']]);
        $bin = $this->repository()->createBin(
            new AuthenticatedUser(['sub' => 'user_111']),
            new CreateBin('000000', Cycle::FORTNIGHTLY, 'house-1', 'ffffff', 'Example', '1985-11-06'),
        );

        self::assertSame('Example', $bin->name);
        self::assertSame(Cycle::FORTNIGHTLY, $bin->cycle);
        self::assertSame('000000', $bin->binColor);
        self::assertSame('ffffff', $bin->lidColor);
        self::assertSame('1', $bin->userId);
        self::assertSame('house-1', $bin->getAttribute('house_id'));
        self::assertSame('1985-11-06', DB::table('bins')->value('start_date'));
        self::assertSame('000000', DB::table('bins')->value('bin_color'));
    }

    public function testDeleteBin(): void
    {
        DB::table('bins')->insert([
            ['id' => '1', 'user_id' => '1', 'house_id' => '1', 'cycle' => 'weekly', 'name' => 'Rubbish'],
            ['id' => '2', 'user_id' => '1', 'house_id' => '1', 'cycle' => 'weekly', 'name' => 'Keep'],
        ]);

        $this->repository()->deleteBin(new Bin()->newQuery()->findOrFail('1'));

        self::assertSame(['2'], new Bin()->newQuery()->pluck('id')->all());
        self::assertNotNull(DB::table('bins')->where('id', '1')->value('deleted_at'));
        self::assertSame('1-Rubbish', DB::table('bins')->where('id', '1')->value('name'));
        self::assertSame('Keep', DB::table('bins')->where('id', '2')->value('name'));
    }

    public function testDeleteBinKeepsTheRenamedNameWithinTheColumnLength(): void
    {
        $id = '11111111-2222-3333-4444-555555555555';
        DB::table('bins')->insert([
            ['id' => $id, 'user_id' => '1', 'house_id' => '1', 'cycle' => 'weekly', 'name' => \str_repeat('é', 255)],
        ]);

        $this->repository()->deleteBin(new Bin()->newQuery()->findOrFail($id));

        $name = DB::table('bins')->where('id', $id)->value('name');
        self::assertSame(255, \mb_strlen($name));
        self::assertSame($id . '-' . \str_repeat('é', 218), $name);
    }

    public function testGetBinsForNotificationFollowsTheLeadDays(): void
    {
        DB::table('bins')->insert(['id' => '1', 'user_id' => '1', 'house_id' => '1', 'cycle' => 'weekly', 'start_date' => '1985-10-05']);

        // Thursday the 24th in Melbourne. Bin night is Saturday, two days on.
        $now = new DateTimeImmutable('1985-10-24 02:00', new DateTimeZone('UTC'));

        self::assertSame(['1'], $this->repository()->getBinsForNotification($this->notification(2), $now)->pluck('id')->all());
        self::assertCount(0, $this->repository()->getBinsForNotification($this->notification(1), $now));
        self::assertCount(0, $this->repository()->getBinsForNotification($this->notification(0), $now));
    }

    public function testGetBinsForNotificationLeavesOutBinsThatHaveNotStartedYet(): void
    {
        DB::table('bins')->insert([
            ['id' => '1', 'user_id' => '1', 'house_id' => '1', 'cycle' => 'weekly', 'start_date' => '1985-10-26'],
            ['id' => '2', 'user_id' => '1', 'house_id' => '1', 'cycle' => 'weekly', 'start_date' => '1985-10-25'],
            ['id' => '3', 'user_id' => '1', 'house_id' => '1', 'cycle' => 'weekly', 'start_date' => '1985-10-27'],
            ['id' => '4', 'user_id' => '1', 'house_id' => '1', 'cycle' => 'weekly', 'start_date' => '1985-11-02'],
        ]);

        // Saturday the 26th: a bin starting that day goes out, and so does one that started the day before.
        $results = $this->repository()->getBinsForNotification($this->notification(0), new DateTimeImmutable('1985-10-26 01:21:00', new DateTimeZone('UTC')));

        self::assertEquals(['1', '2'], $results->pluck('id')->all());
    }

    public function testGetBinsForNotificationLeavesOutDeletedBins(): void
    {
        DB::table('bins')->insert([
            ['id' => '1', 'user_id' => '1', 'house_id' => '1', 'cycle' => 'weekly', 'start_date' => '1985-10-05', 'deleted_at' => null],
            ['id' => '2', 'user_id' => '1', 'house_id' => '1', 'cycle' => 'weekly', 'start_date' => '1985-10-05', 'deleted_at' => '1985-10-20 00:00:00'],
        ]);

        $results = $this->repository()->getBinsForNotification($this->notification(0), new DateTimeImmutable('1985-10-26 01:21:00', new DateTimeZone('UTC')));

        self::assertSame(['1'], $results->pluck('id')->all());
    }

    public function testGetBinsForNotificationMatchesCyclesCorrectly(): void
    {
        // Bins we expect to see: Saturday the 26th is the fourth Saturday of October.
        DB::table('bins')->insert([
            ['id' => '1', 'user_id' => '1', 'house_id' => '1', 'cycle' => 'weekly', 'start_date' => '1985-10-05'],
            ['id' => '2', 'user_id' => '1', 'house_id' => '1', 'cycle' => 'fortnightly', 'start_date' => '1985-10-26'],
            ['id' => '3', 'user_id' => '1', 'house_id' => '1', 'cycle' => 'monthly', 'start_date' => '1985-09-28'],
            ['id' => '4', 'user_id' => '1', 'house_id' => '1', 'cycle' => 'fortnightly', 'start_date' => '1985-10-12'],
        ]);

        // Bins we don't want to see
        DB::table('bins')->insert([
            ['id' => '5', 'user_id' => '1', 'house_id' => '1', 'cycle' => 'fortnightly', 'start_date' => '1985-10-19'], // The other week of the fortnight
            ['id' => '6', 'user_id' => '1', 'house_id' => '1', 'cycle' => 'monthly', 'start_date' => '1985-10-05'], // The first Saturday, not the fourth
            ['id' => '7', 'user_id' => '1', 'house_id' => '2', 'cycle' => 'weekly', 'start_date' => '1985-10-05'], // Another house
        ]);

        $now = new DateTimeImmutable('1985-10-26 01:21:00', new DateTimeZone('UTC'));

        $results = $this->repository()->getBinsForNotification($this->notification(0), $now);

        self::assertCount(4, $results);
        self::assertInstanceOf(Bin::class, $results->first());
        self::assertEquals(['1', '2', '3', '4'], $results->pluck('id')->all());
    }

    public function testGetBinsForUser(): void
    {
        DB::table('users')->insert([
            ['id' => 1, 'external_id' => 'user_111'],
            ['id' => 2, 'external_id' => 'user_222'],
        ]);

        DB::table('bins')->insert([
            ['id' => '1', 'user_id' => '1', 'house_id' => '1', 'cycle' => 'weekly'],
            ['id' => '2', 'user_id' => '1', 'house_id' => '1', 'cycle' => 'weekly'],
            ['id' => '3', 'user_id' => '2', 'house_id' => '1', 'cycle' => 'weekly'],
        ]);

        $results = $this->repository()->getBinsForUser(new AuthenticatedUser(['sub' => 'user_111']));

        self::assertCount(2, $results);
        self::assertInstanceOf(Bin::class, $results->first());
        self::assertEquals(['1', '2'], $results->pluck('id')->all());
    }

    public function testUpdateBin(): void
    {
        DB::table('bins')->insert([
            ['id' => '1', 'user_id' => '1', 'house_id' => '1', 'cycle' => 'weekly', 'name' => 'Old'],
        ]);

        $result = $this->repository()->updateBin(
            new Bin()->newQuery()->first(),
            new UpdateBin(['bin_color' => '00dd00', 'name' => 'Example']),
        );

        self::assertSame('Example', $result->name);
        self::assertSame('00dd00', $result->binColor);
        self::assertSame('Example', DB::table('bins')->value('name'));
        self::assertSame('00dd00', DB::table('bins')->value('bin_color'));
    }

    public function testUpdateBinChangesTheStartDate(): void
    {
        DB::table('bins')->insert([
            ['id' => '1', 'user_id' => '1', 'house_id' => '1', 'cycle' => 'fortnightly', 'start_date' => '1985-10-26', 'name' => 'Old'],
        ]);

        $result = $this->repository()->updateBin(new Bin()->newQuery()->first(), new UpdateBin(['start_date' => '1985-11-02']));

        self::assertSame('1985-11-02', $result->startDate->format('Y-m-d'));
        self::assertSame('1985-11-02', DB::table('bins')->value('start_date'));
        self::assertSame('Old', DB::table('bins')->value('name'));
    }

    private function migrate(): void
    {
        DB::schema()->create('users', static function (Blueprint $table): void {
            $table->string('id');
            $table->string('external_id');
        });

        DB::schema()->create('houses', static function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('bin_night')->nullable();
            $table->string('timezone')->nullable();
            $table->softDeletes();
        });

        DB::schema()->create('bins', static function (Blueprint $table): void {
            $table->string('id')->nullable();
            $table->string('house_id');
            $table->string('user_id');
            $table->string('cycle');
            $table->string('name')->nullable();
            $table->string('bin_color')->nullable();
            $table->string('lid_color')->nullable();
            $table->date('start_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function notification(int $leadDays): Notification
    {
        $house = new House()->setAttribute('id', '1')->setAttribute('bin_night', 'saturday')->setAttribute('timezone', 'Australia/Melbourne');

        return new Notification()->house()->associate($house)->setAttribute('lead_days', $leadDays);
    }

    private function repository(): BinRepository
    {
        return new BinRepository(new ScheduleService());
    }
}
