<?php

namespace Tests\Unit;

use App\Support\FixedShiftsJson;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FixedShiftsJsonTest extends TestCase
{
    public function test_empty_week_structure_has_seven_days_with_lunch_and_dinner(): void
    {
        $s = FixedShiftsJson::emptyWeekStructure();

        $this->assertCount(7, $s);
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            $this->assertArrayHasKey($day, $s);
            $this->assertArrayHasKey('lunch', $s[$day]);
            $this->assertArrayHasKey('dinner', $s[$day]);
            $this->assertNull($s[$day]['lunch']);
            $this->assertNull($s[$day]['dinner']);
        }
    }

    public function test_merge_with_template_fills_missing_days(): void
    {
        $partial = [
            'monday' => ['lunch' => ['09:00', '14:00'], 'dinner' => null],
        ];

        $m = FixedShiftsJson::mergeWithTemplate($partial);

        $this->assertSame(['09:00', '14:00'], $m['monday']['lunch']);
        $this->assertNull($m['tuesday']['lunch']);
        $this->assertNull($m['sunday']['dinner']);
    }

    public function test_merge_with_template_null_returns_full_template(): void
    {
        $m = FixedShiftsJson::mergeWithTemplate(null);

        $this->assertCount(7, $m);
        $this->assertNull($m['wednesday']['dinner']);
    }

    #[DataProvider('prettyJsonProvider')]
    public function test_to_pretty_json_string_normalizes_input(mixed $input, string $contains): void
    {
        $out = FixedShiftsJson::toPrettyJsonString($input);

        $this->assertStringContainsString($contains, $out);
        $this->assertJson($out);
        $decoded = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(7, $decoded);
    }

    /**
     * @return array<string, array{0: mixed, 1: string}>
     */
    public static function prettyJsonProvider(): array
    {
        return [
            'null' => [null, 'monday'],
            'empty string' => ['', 'monday'],
            'partial array' => [[
                'friday' => ['lunch' => null, 'dinner' => ['18:00', '22:00']],
            ], 'friday'],
        ];
    }

    public function test_to_persisted_array_from_valid_string_round_trip(): void
    {
        $json = FixedShiftsJson::toPrettyJsonString(null);
        $arr = FixedShiftsJson::toPersistedArray($json);

        $this->assertIsArray($arr);
        $this->assertCount(7, $arr);
        $this->assertNull($arr['monday']['lunch']);
    }

    public function test_to_persisted_array_invalid_json_returns_null(): void
    {
        $this->assertNull(FixedShiftsJson::toPersistedArray('{not json'));
    }

    public function test_to_persisted_array_empty_string_returns_full_week(): void
    {
        $arr = FixedShiftsJson::toPersistedArray('');
        $this->assertIsArray($arr);
        $this->assertCount(7, $arr);
    }

    public function test_try_pretty_print_keeps_invalid_raw(): void
    {
        $bad = '{broken';
        $this->assertSame($bad, FixedShiftsJson::tryPrettyPrint($bad));
    }

    public function test_try_pretty_print_formats_valid_minified(): void
    {
        $min = '{"monday":{"lunch":null,"dinner":null},"tuesday":{"lunch":null,"dinner":null},"wednesday":{"lunch":null,"dinner":null},"thursday":{"lunch":null,"dinner":null},"friday":{"lunch":null,"dinner":null},"saturday":{"lunch":null,"dinner":null},"sunday":{"lunch":null,"dinner":null}}';
        $out = FixedShiftsJson::tryPrettyPrint($min);

        $this->assertStringContainsString("\n", $out);
        $this->assertJson($out);
    }
}
