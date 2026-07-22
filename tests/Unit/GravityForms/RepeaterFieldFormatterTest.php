<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Tests\Unit\GravityForms;

use Kodr\SecureReferralArchive\GravityForms\RepeaterFieldFormatter;
use PHPUnit\Framework\TestCase;

final class RepeaterFieldFormatterTest extends TestCase
{
    private const LABELS = [
        201 => 'Make:',
        202 => 'Model:',
        203 => 'Year:',
    ];

    public function test_it_expands_a_single_repeated_row(): void
    {
        $value = serialize([
            501 => [
                'input_201__501' => 'Toyota',
                'input_202__501' => 'Corolla',
                'input_203__501' => '2020',
            ],
        ]);

        $result = (new RepeaterFieldFormatter())->format($value, self::LABELS);

        self::assertSame("Make: Toyota\nModel: Corolla\nYear: 2020", $result);
    }

    public function test_it_labels_multiple_repeated_rows_separately(): void
    {
        $value = serialize([
            501 => ['input_201__501' => 'Toyota', 'input_202__501' => 'Corolla'],
            502 => ['input_201__502' => 'Honda', 'input_202__502' => 'Civic'],
        ]);

        $result = (new RepeaterFieldFormatter())->format($value, self::LABELS);

        self::assertSame(
            "Entry 1:\nMake: Toyota\nModel: Corolla\n\nEntry 2:\nMake: Honda\nModel: Civic",
            $result
        );
    }

    public function test_it_falls_back_to_a_generic_label_for_unknown_field_ids(): void
    {
        $value = serialize([501 => ['input_999__501' => 'Mystery value']]);

        $result = (new RepeaterFieldFormatter())->format($value, self::LABELS);

        self::assertSame('Field 999 Mystery value', $result);
    }

    public function test_it_returns_null_for_ordinary_non_serialized_text(): void
    {
        $result = (new RepeaterFieldFormatter())->format('Just a normal answer', self::LABELS);

        self::assertNull($result);
    }

    public function test_it_never_instantiates_objects_from_untrusted_input(): void
    {
        // A serialized *object* (not array) must never be instantiated, and
        // must safely fall through to null rather than being expanded.
        $malicious = 'O:8:"stdClass":1:{s:3:"foo";s:3:"bar";}';

        $result = (new RepeaterFieldFormatter())->format($malicious, self::LABELS);

        self::assertNull($result);
    }
}
