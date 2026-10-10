<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\Bins;

use App\Auth\AuthenticatedUser;
use App\DTO\Bin\CreateBin;
use App\Http\Controllers\Api\Bins\CreateController;
use App\Http\Requests\Bin\CreateBinRequest;
use App\Models\Bin;
use App\Repositories\Interfaces\BinRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class CreateControllerTest extends TestCase
{
    public function testCreatesBin(): void
    {
        $user = new AuthenticatedUser(['sub' => 'user_111']);
        $request = new CreateBinRequest([
            'bin_color' => '#000000',
            'cycle' => 'weekly',
            'house_id' => '00000000-0000-0000-0000-000000000000',
            'lid_color' => '#ffffff',
            'name' => 'Example',
        ])->setUserResolver(static fn (): AuthenticatedUser => $user);

        $repository = $this->createMock(BinRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('createBin')
            ->with($user, $this->callback(
                static fn (CreateBin $data): bool => $data->houseId === '00000000-0000-0000-0000-000000000000',
            ))
            ->willReturn(new Bin(['name' => 'Created']));

        $response = new CreateController()($request, $repository);

        self::assertSame('Created', $response->resource->name);
    }
}
