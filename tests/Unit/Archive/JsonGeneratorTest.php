<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Tests\Unit\Archive;

use Kodr\SecureReferralArchive\Archive\JsonGenerator;
use Kodr\SecureReferralArchive\GravityForms\EntryParser;
use Kodr\SecureReferralArchive\Tests\Fixtures\ReferralFormFixture;
use PHPUnit\Framework\TestCase;

final class JsonGeneratorTest extends TestCase
{
    public function test_it_produces_valid_utf8_json_with_expected_structure(): void
    {
        [$form, $entry] = ReferralFormFixture::formAndEntry();
        $data = (new EntryParser())->parse($form, $entry, 'REF-TEST-0001');

        $json = (new JsonGenerator())->generate($data);
        $decoded = json_decode($json, true);

        self::assertNotNull($decoded, 'generated JSON must be valid');
        self::assertSame('1.0', $decoded['schema_version']);
        self::assertSame('REF-TEST-0001', $decoded['reference']);
        self::assertSame(6, $decoded['form']['id']);
        self::assertSame(999, $decoded['entry']['id']);
        self::assertCount(count($data->fields()), $decoded['fields']);
    }

    public function test_it_preserves_field_order_and_empty_fields(): void
    {
        [$form, $entry] = ReferralFormFixture::formAndEntry();
        $data = (new EntryParser())->parse($form, $entry, 'REF-TEST-0001');

        $decoded = json_decode((new JsonGenerator())->generate($data), true);

        $ids = array_column($decoded['fields'], 'field_id');
        self::assertSame(['4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14', '15'], $ids);

        $optional = $decoded['fields'][array_search('15', $ids, true)];
        self::assertSame('', $optional['value'], 'empty fields must be present with an empty value, not omitted');
    }

    public function test_it_does_not_escape_unicode_or_slashes(): void
    {
        [$form, $entry] = ReferralFormFixture::formAndEntry();
        $data = (new EntryParser())->parse($form, $entry, 'REF-TEST-0001');

        $json = (new JsonGenerator())->generate($data);

        self::assertStringContainsString('Zoë', $json);
        self::assertStringNotContainsString('\\u00eb', $json);
    }
}
