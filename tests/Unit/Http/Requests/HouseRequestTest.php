<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Enums\DayOfWeek;
use App\Http\Requests\House\AbstractHouseRequest;
use App\Http\Requests\House\CreateHouseRequest;
use App\Http\Requests\House\UpdateHouseRequest;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Validator;
use PHPUnit\Framework\TestCase;

final class HouseRequestTest extends TestCase
{
    public function testAuthorize(): void
    {
        $request = new class() extends AbstractHouseRequest {
            protected string $presence = 'required';
        };

        self::assertTrue($request->authorize());
    }

    public function testCreateRequestFailsWithInvalidPayload(): void
    {
        $validator = $this->getValidator([
            'bin_night' => 'not-a-day',
            'name' => 'Example',
            'timezone' => 'America/Los_Angeles',
        ], new CreateHouseRequest()->rules());

        self::assertFalse($validator->passes());
        self::assertArrayHasKey('bin_night', $validator->errors()->toArray());
    }

    public function testCreateRequestFailsWithInvalidTimezone(): void
    {
        $validator = $this->getValidator([
            'bin_night' => DayOfWeek::MONDAY->value,
            'name' => 'Example',
            'timezone' => 'Not/A_Timezone',
        ], new CreateHouseRequest()->rules());

        self::assertFalse($validator->passes());
        self::assertArrayHasKey('timezone', $validator->errors()->toArray());
    }

    public function testCreateRequestPassesWithValidPayload(): void
    {
        $validator = $this->getValidator([
            'bin_night' => DayOfWeek::MONDAY->value,
            'name' => 'Example',
            'timezone' => 'America/Los_Angeles',
        ], new CreateHouseRequest()->rules());

        self::assertTrue($validator->passes());
    }

    public function testCreateRequestRequiresAllFields(): void
    {
        $validator = $this->getValidator([], new CreateHouseRequest()->rules());

        self::assertFalse($validator->passes());
        self::assertSame(['bin_night', 'name', 'timezone'], \array_keys($validator->errors()->toArray()));
    }

    public function testUpdateRequestPassesWithEmptyPayload(): void
    {
        $validator = $this->getValidator([], new UpdateHouseRequest()->rules());

        self::assertTrue($validator->passes());
    }

    public function testUpdateRequestPassesWithPartialPayload(): void
    {
        $validator = $this->getValidator([
            'name' => 'Updated house name',
        ], new UpdateHouseRequest()->rules());

        self::assertTrue($validator->passes());
    }

    public function testUpdateRequestPassesWithPresentFields(): void
    {
        $validator = $this->getValidator([
            'timezone' => 'Not/A_Timezone',
        ], new UpdateHouseRequest()->rules());

        self::assertFalse($validator->passes());

        self::assertArrayHasKey('timezone', $validator->errors()->toArray());
    }

    private function getValidator(array $data, array $rules): Validator
    {
        $translator = new Translator(new ArrayLoader(), 'en-AU');
        return new Validator($translator, $data, $rules);
    }
}
