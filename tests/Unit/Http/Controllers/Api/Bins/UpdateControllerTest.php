<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\Bins;

use App\Auth\AuthenticatedUser;
use App\DTO\Bin\UpdateBin;
use App\Http\Controllers\Api\Bins\UpdateController;
use App\Http\Requests\Bin\UpdateBinRequest;
use App\Models\Bin;
use App\Repositories\Interfaces\BinRepositoryInterface;
use Illuminate\Contracts\Validation\Validator;
use PHPUnit\Framework\MockObject\Generator\Generator as MockGenerator;
use PHPUnit\Framework\TestCase;

final class UpdateControllerTest extends TestCase
{
    public function testUpdatesBin(): void
    {
        $validator = new MockGenerator()->testDouble(Validator::class, false);
        $validator->method('validated')->willReturn(['bin_color' => '#000000', 'name' => 'Updated']);

        $request = new UpdateBinRequest()->setValidator($validator)->setUserResolver(static fn (): AuthenticatedUser => new AuthenticatedUser(['sub' => 'user_111']));
        $bin = new Bin();

        $repository = $this->createMock(BinRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('updateBin')
            ->with($bin, $this->callback(
                static fn (UpdateBin $data): bool => $data->toDatabaseArray() === ['bin_color' => '000000', 'name' => 'Updated'],
            ))
            ->willReturn(new Bin(['name' => 'Updated']));

        $response = new UpdateController()($request, $repository, $bin);

        self::assertSame('Updated', $response->resource->name);
    }
}
