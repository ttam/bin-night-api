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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Unit\TestCase;

final class BinRequestTest extends TestCase
{
    protected function setUp(): void
    {
        $this->bootEloquent()->migrate();
    }

    /** @return iterable<string, array{mixed}> */
    public static function getBadColours(): iterable
    {
        yield 'Three digits' => ['#abc'];

        yield 'Four digits' => ['#abcd'];

        yield 'Eight digits' => ['#aabbccdd'];

        yield 'Without the hash' => ['aabbcc'];

        yield 'Not hex' => ['#gggggg'];

        yield 'A name' => ['red'];

        yield 'A number' => [123456];

        yield 'An array' => [['#aabbcc']];

        yield 'Trailing text' => ["#aabbcc\n"];

        yield 'Empty' => [''];
    }

    public function testAuthorize(): void
    {
        $request = new class() extends AbstractBinRequest {
            protected string $presence = 'required';
        };

        self::assertTrue($request->authorize());
    }

    #[DataProvider('getBadColours')]
    public function testCreateRequestFailsWithAColourThatIsNotSixHexDigits(mixed $colour): void
    {
        foreach (['bin_color', 'lid_color'] as $field) {
            $request = new CreateBinRequest();
            $data = ['bin_color' => '#00dd00', 'lid_color' => '#00dd00', $field => $colour];
            $validator = $this->getValidator($data, ['bin_color' => $request->rules()['bin_color'], 'lid_color' => $request->rules()['lid_color']], $request->messages());

            self::assertFalse($validator->passes(), $field);
            self::assertSame([$field], \array_keys($validator->errors()->toArray()));
        }
    }

    public function testCreateRequestAcceptsAnyCaseOfSixHexDigits(): void
    {
        $request = new CreateBinRequest();

        foreach (['#aabbcc', '#AABBCC', '#0061a4', '#F0C800'] as $colour) {
            $validator = $this->getValidator(['bin_color' => $colour, 'lid_color' => $colour], ['bin_color' => $request->rules()['bin_color'], 'lid_color' => $request->rules()['lid_color']]);

            self::assertTrue($validator->passes(), $colour);
        }
    }

    public function testColourMessagesSayWhatIsWanted(): void
    {
        $request = new CreateBinRequest();
        $validator = $this->getValidator(['bin_color' => '#abc', 'lid_color' => '#abc'], ['bin_color' => $request->rules()['bin_color'], 'lid_color' => $request->rules()['lid_color']], $request->messages());

        $validator->passes();

        self::assertSame([
            'bin_color' => ['The bin colour must be a colour like #0061a4.'],
            'lid_color' => ['The lid colour must be a colour like #0061a4.'],
        ], $validator->errors()->toArray());
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
            'start_date' => '1985-10-26',
        ], new CreateBinRequest()->rules());

        self::assertFalse($validator->passes());
        self::assertSame(['house_id'], \array_keys($validator->errors()->toArray()));
    }

    public function testCreateRequestRequiresAllFields(): void
    {
        $validator = $this->getValidator([], new CreateBinRequest()->rules());

        self::assertFalse($validator->passes());
        self::assertSame(
            ['bin_color', 'cycle', 'lid_color', 'name', 'start_date', 'house_id'],
            \array_keys($validator->errors()->toArray()),
        );
    }

    public function testCreateRequestRequiresAStartDate(): void
    {
        $rules = ['start_date' => new CreateBinRequest()->rules()['start_date']];

        self::assertFalse($this->getValidator([], $rules)->passes());
        self::assertFalse($this->getValidator(['start_date' => null], $rules)->passes());
        self::assertFalse($this->getValidator(['start_date' => ''], $rules)->passes());
        self::assertTrue($this->getValidator(['start_date' => '1985-11-06'], $rules)->passes());
    }

    public function testStartDateMustBeARealDateInYearMonthDayForm(): void
    {
        $rules = ['start_date' => new CreateBinRequest()->rules()['start_date']];

        foreach (['06/11/1985', '1985-11-6', '1985-02-30', '1985-13-01', 'next wednesday', '1985-11-06 10:00', 20251106, ['1985-11-06']] as $bad) {
            $validator = $this->getValidator(['start_date' => $bad], $rules);

            self::assertFalse($validator->passes(), \json_encode($bad, JSON_THROW_ON_ERROR));
        }
    }

    public function testUpdateRequestAcceptsAStartDate(): void
    {
        $rules = $this->makeUpdateRequest()->rules();

        self::assertTrue($this->getValidator([], ['start_date' => $rules['start_date']])->passes());
        self::assertTrue($this->getValidator(['start_date' => '1985-11-06'], ['start_date' => $rules['start_date']])->passes());
        self::assertFalse($this->getValidator(['start_date' => 'soon'], ['start_date' => $rules['start_date']])->passes());
    }

    public function testUpdateRequestAllowsABinToKeepItsOwnName(): void
    {
        DB::table('bins')->insert(['id' => 'b1', 'house_id' => 'h1', 'name' => 'Example']);

        $validator = $this->validateName($this->makeUpdateRequest());

        self::assertTrue($validator->passes());
    }

    public function testUpdateRequestAllowsANameUsedInAnotherHouse(): void
    {
        DB::table('bins')->insert([
            ['id' => 'b1', 'house_id' => 'h1', 'name' => 'Rubbish'],
            ['id' => 'b2', 'house_id' => 'h2', 'name' => 'Example'],
        ]);

        $validator = $this->validateName($this->makeUpdateRequest());

        self::assertTrue($validator->passes());
    }

    public function testUpdateRequestDoesNotAcceptHouseId(): void
    {
        self::assertArrayNotHasKey('house_id', $this->makeUpdateRequest()->rules());
    }

    public function testUpdateRequestFailsWithDuplicateNameInSameHouse(): void
    {
        DB::table('bins')->insert([
            ['id' => 'b1', 'house_id' => 'h1', 'name' => 'Rubbish'],
            ['id' => 'b2', 'house_id' => 'h1', 'name' => 'Example'],
        ]);

        $validator = $this->validateName($this->makeUpdateRequest());

        self::assertFalse($validator->passes());
        self::assertSame(['name'], \array_keys($validator->errors()->toArray()));
    }

    public function testUpdateRequestFailsWithInvalidColour(): void
    {
        $validator = $this->getValidator(['lid_color' => 'green'], $this->makeUpdateRequest()->rules());

        self::assertFalse($validator->passes());
        self::assertArrayHasKey('lid_color', $validator->errors()->toArray());
    }

    public function testUpdateRequestFailsWithInvalidCycle(): void
    {
        $validator = $this->getValidator(['cycle' => 'bad_cycle'], $this->makeUpdateRequest()->rules());

        self::assertFalse($validator->passes());
        self::assertArrayHasKey('cycle', $validator->errors()->toArray());
    }

    public function testUpdateRequestPassesWithEmptyPayload(): void
    {
        self::assertTrue($this->getValidator([], $this->makeUpdateRequest()->rules())->passes());
    }

    public function testUpdateRequestPassesWithPartialPayload(): void
    {
        $validator = $this->getValidator(
            ['name' => 'Example', 'bin_color' => '#00dd00'],
            $this->makeUpdateRequest()->rules()
        );

        self::assertTrue($validator->passes());
    }

    private function getValidator(array $data, array $rules, array $messages = []): Validator
    {
        $validator = new Validator(new Translator(new ArrayLoader(), 'en-AU'), $data, $rules, $messages);
        $validator->setPresenceVerifier(new DatabasePresenceVerifier(Bin::getConnectionResolver()));

        return $validator;
    }

    private function makeUpdateRequest(): UpdateBinRequest
    {
        $bin = new Bin()->setAttribute('id', 'b1')->setAttribute('house_id', 'h1');

        $route = new Route('PATCH', 'bins/{bin}', static fn (): null => null);
        $route->bind(Request::create('/bins/b1', 'PATCH'));
        $route->setParameter('bin', $bin);

        return new UpdateBinRequest()->setRouteResolver(static fn (): Route => $route);
    }

    private function migrate(): void
    {
        DB::schema()->create('bins', static function (Blueprint $table): void {
            $table->string('id');
            $table->string('house_id');
            $table->string('name');
            $table->softDeletes();
        });
    }

    private function validateName(AbstractBinRequest $request): Validator
    {
        return $this->getValidator(['name' => 'Example'], ['name' => $request->rules()['name']]);
    }
}
