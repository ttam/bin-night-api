<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\Schedule\ShowScheduleRequest;
use Illuminate\Http\Request;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Validator;
use PHPUnit\Framework\TestCase;

final class ShowScheduleRequestTest extends TestCase
{
    public function testAuthorize(): void
    {
        self::assertTrue(new ShowScheduleRequest()->authorize());
    }

    public function testRulesAcceptOneToTwelveWeeksOrNone(): void
    {
        foreach ([[], ['weeks' => '1'], ['weeks' => '4'], ['weeks' => '12']] as $query) {
            self::assertTrue($this->validator($query)->passes(), \json_encode($query) ?: '');
        }
    }

    public function testRulesRefuseAnythingElse(): void
    {
        foreach (['0', '13', '-1', '1.5', 'abc'] as $weeks) {
            $validator = $this->validator(['weeks' => $weeks]);

            self::assertFalse($validator->passes(), $weeks);
            self::assertSame(['weeks'], \array_keys($validator->errors()->toArray()));
        }
    }

    public function testWeeksIsFourWhenNotAsked(): void
    {
        self::assertSame(4, $this->request([])->weeks());
        self::assertSame(4, $this->request(['weeks' => ''])->weeks());
    }

    public function testWeeksIsWhatWasAsked(): void
    {
        self::assertSame(9, $this->request(['weeks' => '9'])->weeks());
    }

    /** @param array<string, string> $query */
    private function request(array $query): ShowScheduleRequest
    {
        return ShowScheduleRequest::createFromBase(Request::create('/api/v1/schedule', 'GET', $query));
    }

    /** @param array<string, string> $query */
    private function validator(array $query): Validator
    {
        return new Validator(new Translator(new ArrayLoader(), 'en'), $query, new ShowScheduleRequest()->rules());
    }
}
