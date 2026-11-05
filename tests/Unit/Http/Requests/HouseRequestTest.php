<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Auth\AuthenticatedUser;
use App\Enums\DayOfWeek;
use App\Http\Requests\House\AbstractHouseRequest;
use App\Http\Requests\House\CreateHouseRequest;
use App\Http\Requests\House\UpdateHouseRequest;
use App\Models\House;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\DatabasePresenceVerifier;
use Illuminate\Validation\Validator;
use Tests\Unit\TestCase;

final class HouseRequestTest extends TestCase
{
    protected function setUp(): void
    {
        $this->bootEloquent()->migrate();

        DB::table('users')->insert([
            ['id' => 'u1', 'external_id' => 'user_111'],
            ['id' => 'u2', 'external_id' => 'user_222'],
        ]);
    }

    public function testAuthorize(): void
    {
        $request = new class() extends AbstractHouseRequest {
            protected string $presence = 'required';
        };

        self::assertTrue($request->authorize());
    }

    public function testCreateRequestAllowsANameAnotherUserHas(): void
    {
        DB::table('houses')->insert(['id' => 'h1', 'user_id' => 'u2', 'name' => 'Example']);

        self::assertTrue($this->getValidator($this->validPayload(), $this->makeCreateRequest()->rules())->passes());
    }

    public function testCreateRequestDoesNotFailForAUserWithNoRowYet(): void
    {
        $request = new CreateHouseRequest()->setUserResolver(static fn (): AuthenticatedUser => new AuthenticatedUser(['sub' => 'user_new']));

        self::assertTrue($this->getValidator($this->validPayload(), $request->rules())->passes());
    }

    public function testCreateRequestFailsWithANameTheUserAlreadyHas(): void
    {
        DB::table('houses')->insert(['id' => 'h1', 'user_id' => 'u1', 'name' => 'Example']);

        $validator = $this->getValidator($this->validPayload(), $this->makeCreateRequest()->rules());

        self::assertFalse($validator->passes());
        self::assertSame(['name'], \array_keys($validator->errors()->toArray()));
    }

    public function testCreateRequestFailsWithAnInvalidName(): void
    {
        foreach ([\str_repeat('a', 256), ['array'], ''] as $name) {
            $validator = $this->getValidator([...$this->validPayload(), 'name' => $name], $this->makeCreateRequest()->rules());

            self::assertFalse($validator->passes());
            self::assertSame(['name'], \array_keys($validator->errors()->toArray()));
        }
    }

    public function testCreateRequestFailsWithInvalidPayload(): void
    {
        $validator = $this->getValidator([
            'bin_night' => 'not-a-day',
            'name' => 'Example',
            'timezone' => 'America/Los_Angeles',
        ], $this->makeCreateRequest()->rules());

        self::assertFalse($validator->passes());
        self::assertArrayHasKey('bin_night', $validator->errors()->toArray());
    }

    public function testCreateRequestFailsWithInvalidTimezone(): void
    {
        $validator = $this->getValidator([
            'bin_night' => DayOfWeek::MONDAY->value,
            'name' => 'Example',
            'timezone' => 'Not/A_Timezone',
        ], $this->makeCreateRequest()->rules());

        self::assertFalse($validator->passes());
        self::assertArrayHasKey('timezone', $validator->errors()->toArray());
    }

    public function testCreateRequestPassesWithValidPayload(): void
    {
        $validator = $this->getValidator([
            'bin_night' => DayOfWeek::MONDAY->value,
            'name' => 'Example',
            'timezone' => 'America/Los_Angeles',
        ], $this->makeCreateRequest()->rules());

        self::assertTrue($validator->passes());
    }

    public function testCreateRequestRequiresAllFields(): void
    {
        $validator = $this->getValidator([], $this->makeCreateRequest()->rules());

        self::assertFalse($validator->passes());
        self::assertSame(['bin_night', 'name', 'timezone'], \array_keys($validator->errors()->toArray()));
    }

    public function testUpdateRequestAllowsTheHousesOwnNameAndNamesOtherUsersHave(): void
    {
        DB::table('houses')->insert([
            ['id' => 'h1', 'user_id' => 'u1', 'name' => 'Example'],
            ['id' => 'h2', 'user_id' => 'u2', 'name' => 'Other'],
        ]);

        foreach (['Example', 'Other'] as $name) {
            $data = ['name' => $name];

            self::assertTrue($this->getValidator($data, $this->makeUpdateRequest($data)->rules())->passes());
        }
    }

    public function testUpdateRequestFailsWithANameTheUserAlreadyHasOnAnotherHouse(): void
    {
        DB::table('houses')->insert([
            ['id' => 'h1', 'user_id' => 'u1', 'name' => 'Example'],
            ['id' => 'h2', 'user_id' => 'u1', 'name' => 'Other'],
        ]);
        $data = ['name' => 'Other'];

        $validator = $this->getValidator($data, $this->makeUpdateRequest($data)->rules());

        self::assertFalse($validator->passes());
        self::assertSame(['name'], \array_keys($validator->errors()->toArray()));
    }

    public function testUpdateRequestPassesWithEmptyPayload(): void
    {
        $validator = $this->getValidator([], $this->makeUpdateRequest()->rules());

        self::assertTrue($validator->passes());
    }

    public function testUpdateRequestPassesWithPartialPayload(): void
    {
        $validator = $this->getValidator([
            'name' => 'Updated house name',
        ], $this->makeUpdateRequest()->rules());

        self::assertTrue($validator->passes());
    }

    public function testUpdateRequestPassesWithPresentFields(): void
    {
        $validator = $this->getValidator([
            'timezone' => 'Not/A_Timezone',
        ], $this->makeUpdateRequest()->rules());

        self::assertFalse($validator->passes());

        self::assertArrayHasKey('timezone', $validator->errors()->toArray());
    }

    private function getValidator(array $data, array $rules): Validator
    {
        $validator = new Validator(new Translator(new ArrayLoader(), 'en-AU'), $data, $rules);
        $validator->setPresenceVerifier(new DatabasePresenceVerifier(House::getConnectionResolver()));

        return $validator;
    }

    private function makeCreateRequest(): CreateHouseRequest
    {
        return new CreateHouseRequest()->setUserResolver(static fn (): AuthenticatedUser => new AuthenticatedUser(['sub' => 'user_111']));
    }

    private function makeUpdateRequest(array $input = []): UpdateHouseRequest
    {
        $house = new House()->setAttribute('id', 'h1')->setAttribute('user_id', 'u1');

        $route = new Route('PATCH', 'houses/{house}', static fn (): null => null);
        $route->bind(Request::create('/houses/h1', 'PATCH'));
        $route->setParameter('house', $house);

        return new UpdateHouseRequest($input)->setRouteResolver(static fn (): Route => $route);
    }

    private function migrate(): void
    {
        DB::schema()->create('users', static function (Blueprint $table): void {
            $table->string('id');
            $table->string('external_id');
            $table->softDeletes();
        });

        DB::schema()->create('houses', static function (Blueprint $table): void {
            $table->string('id');
            $table->string('user_id');
            $table->string('name');
        });
    }

    private function validPayload(): array
    {
        return ['bin_night' => DayOfWeek::MONDAY->value, 'name' => 'Example', 'timezone' => 'America/Los_Angeles'];
    }
}
