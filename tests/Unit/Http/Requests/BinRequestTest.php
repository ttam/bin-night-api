<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\Bin\AbstractBinRequest;
use App\Http\Requests\Bin\CreateBinRequest;
use App\Http\Requests\Bin\UpdateBinRequest;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Validator;
use PHPUnit\Framework\TestCase;

final class BinRequestTest extends TestCase
{
    public function testAuthorize(): void
    {
        $request = new class() extends AbstractBinRequest {
            protected string $presence = 'required';
        };

        self::assertTrue($request->authorize());
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

    public function testUpdateRequestDoesNotAcceptHouseId(): void
    {
        self::assertArrayNotHasKey('house_id', new UpdateBinRequest()->rules());
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
        return new Validator(new Translator(new ArrayLoader(), 'en-AU'), $data, $rules);
    }
}
