<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\DTO\Schedule\UpcomingCollection;
use App\Http\Resources\UpcomingCollectionResource;
use App\Models\Bin;
use App\Models\House;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class UpcomingCollectionResourceTest extends TestCase
{
    public function testBinsAreInAlphabeticalOrderWhateverOrderTheyCameIn(): void
    {
        $collection = new UpcomingCollection(
            new DateTimeImmutable('1985-10-30'),
            new House()->setAttribute('id', 'house-1'),
            new Collection([$this->bin('b2', 'recycling'), $this->bin('b3', 'Garden'), $this->bin('b1', 'Rubbish')]),
        );

        $names = \array_column(new UpcomingCollectionResource($collection)->toArray(new Request())['bins'], 'name');

        self::assertSame(['Garden', 'recycling', 'Rubbish'], $names);
    }

    public function testResource(): void
    {
        $collection = new UpcomingCollection(
            new DateTimeImmutable('1985-10-30'),
            new House()->setAttribute('id', 'house-1'),
            new Collection([$this->bin('bin-1', 'Rubbish')]),
        );

        self::assertSame([
            'bins' => [['bin_color' => '#000000', 'id' => 'bin-1', 'lid_color' => '#ffffff', 'name' => 'Rubbish']],
            'date' => '1985-10-30',
            'house_id' => 'house-1',
        ], new UpcomingCollectionResource($collection)->toArray(new Request()));
    }

    private function bin(string $id, string $name): Bin
    {
        return new Bin()
            ->setAttribute('bin_color', '000000')
            ->setAttribute('id', $id)
            ->setAttribute('lid_color', 'ffffff')
            ->setAttribute('name', $name);
    }
}
