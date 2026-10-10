<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\Bin\AbstractBinRequest;
use App\Http\Requests\Bin\CreateBinRequest;
use App\Http\Requests\Bin\UpdateBinRequest;
use App\Models\Bin;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\DatabasePresenceVerifier;
use Illuminate\Validation\Validator;
use Tests\Unit\TestCase;

final class BinRequestTest extends TestCase
{
    protected function setUp(): void
    {
        $this->bootEloquent()->migrate();
    }

    public function testAuthorize(): void
    {
        $request = new class() extends AbstractBinRequest {
            protected string $presence = 'required';
        };

        self::assertTrue($request->authorize());
    }

    public function testCreateRequestAllowsSameNameInAnotherHouse(): void
    {
        DB::table('bins')->insert(['id' => 'b1', 'house_id' => 'h1', 'name' => 'Example']);

        $validator = $this->validateName(new CreateBinRequest(['house_id' => 'h2']));

        self::assertTrue($validator->passes());
    }

    public function testCreateRequestFailsWithDuplicateName(): void
    {
        DB::table('bins')->insert(['id' => 'b1', 'house_id' => 'h1', 'name' => 'Example']);

        $validator = $this->validateName(new CreateBinRequest(['house_id' => 'h1']));

        self::assertFalse($validator->passes());
        self::assertSame(['name'], \array_keys($validator->errors()->toArray()));
    }

    public function testCreateRequestFailsWithInvalidHouseId(): void
    {
        $validator = $this->getValidator([
            'bin_color' => '#00dd00',
            'cycle' => 'weekly',
            'house_id' => 'not-a-uuid',
            'lid_color' => '#00dd00',
            'name' => 'Example',
        ], new CreateBinRequest()->rules());

        self::assertFalse($validator->passes());
        self::assertSame(['house_id'], \array_keys($validator->errors()->toArray()));
    }

    public function testCreateRequestRequiresAllFields(): void
    {
        $validator = $this->getValidator([], new CreateBinRequest()->rules());

        self::assertFalse($validator->passes());
        self::assertSame(
            ['bin_color', 'cycle', 'lid_color', 'name', 'house_id'],
            \array_keys($validator->errors()->toArray()),
        );
    }

    public function testUpdateRequestAllowsABinToKeepItsOwnName(): void
    {
        DB::table('bins')->insert(['id' => 'b1', 'house_id' => 'h1', 'name' => 'Example']);

        $validator = $this->validateName($this->makeUpdateRequest('b1', 'h1'));

        self::assertTrue($validator->passes());
    }

    public function testUpdateRequestAllowsANameUsedInAnotherHouse(): void
    {
        DB::table('bins')->insert([
            ['id' => 'b1', 'house_id' => 'h1', 'name' => 'Rubbish'],
            ['id' => 'b2', 'house_id' => 'h2', 'name' => 'Example'],
        ]);

        $validator = $this->validateName($this->makeUpdateRequest('b1', 'h1'));

        self::assertTrue($validator->passes());
    }

    public function testUpdateRequestDoesNotAcceptHouseId(): void
    {
        self::assertArrayNotHasKey('house_id', new UpdateBinRequest()->rules());
    }

    public function testUpdateRequestFailsWithDuplicateNameInSameHouse(): void
    {
        DB::table('bins')->insert([
            ['id' => 'b1', 'house_id' => 'h1', 'name' => 'Rubbish'],
            ['id' => 'b2', 'house_id' => 'h1', 'name' => 'Example'],
        ]);

        $validator = $this->validateName($this->makeUpdateRequest('b1', 'h1'));

        self::assertFalse($validator->passes());
        self::assertSame(['name'], \array_keys($validator->errors()->toArray()));
    }

    public function testUpdateRequestFailsWithInvalidColour(): void
    {
        $validator = $this->getValidator(['lid_color' => 'green'], new UpdateBinRequest()->rules());

        self::assertFalse($validator->passes());
        self::assertArrayHasKey('lid_color', $validator->errors()->toArray());
    }

    public function testUpdateRequestFailsWithInvalidCycle(): void
    {
        $validator = $this->getValidator(['cycle' => 'bad_cycle'], new UpdateBinRequest()->rules());

        self::assertFalse($validator->passes());
        self::assertArrayHasKey('cycle', $validator->errors()->toArray());
    }

    public function testUpdateRequestPassesWithEmptyPayload(): void
    {
        self::assertTrue($this->getValidator([], new UpdateBinRequest()->rules())->passes());
    }

    public function testUpdateRequestPassesWithPartialPayload(): void
    {
        $validator = $this->getValidator(
            ['name' => 'Example', 'bin_color' => '#00dd00'],
            new UpdateBinRequest()->rules()
        );

        self::assertTrue($validator->passes());
    }

    private function getValidator(array $data, array $rules): Validator
    {
        $validator = new Validator(new Translator(new ArrayLoader(), 'en-AU'), $data, $rules);
        $validator->setPresenceVerifier(new DatabasePresenceVerifier(Bin::getConnectionResolver()));

        return $validator;
    }

    private function makeUpdateRequest(string $binId, string $houseId): UpdateBinRequest
    {
        $bin = new Bin()->setAttribute('id', $binId)->setAttribute('house_id', $houseId);

        $route = new Route('PATCH', 'bins/{bin}', static fn (): null => null);
        $route->bind(Request::create('/bins/' . $binId, 'PATCH'));
        $route->setParameter('bin', $bin);

        return new UpdateBinRequest()->setRouteResolver(static fn (): Route => $route);
    }

    private function migrate(): void
    {
        DB::schema()->create('bins', static function (Blueprint $table): void {
            $table->string('id');
            $table->string('house_id');
            $table->string('name');
        });
    }

    private function validateName(AbstractBinRequest $request): Validator
    {
        return $this->getValidator(['name' => 'Example'], ['name' => $request->rules()['name']]);
    }
}
