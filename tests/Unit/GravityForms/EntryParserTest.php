<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Tests\Unit\GravityForms;

use Kodr\SecureReferralArchive\Archive\ArchiveField;
use Kodr\SecureReferralArchive\GravityForms\EntryParser;
use Kodr\SecureReferralArchive\Tests\Fixtures\ReferralFormFixture;
use PHPUnit\Framework\TestCase;

final class EntryParserTest extends TestCase
{
    public function test_it_excludes_display_only_fields(): void
    {
        [$form, $entry] = ReferralFormFixture::formAndEntry();

        $data = (new EntryParser())->parse($form, $entry, 'REF-TEST-0001');

        $ids = array_map(static fn (ArchiveField $field): string => $field->fieldId(), $data->fields());

        self::assertNotContains('1', $ids, 'page break must be excluded');
        self::assertNotContains('2', $ids, 'html field must be excluded');
        self::assertNotContains('3', $ids, 'section break must be excluded');
    }

    public function test_it_preserves_field_order(): void
    {
        [$form, $entry] = ReferralFormFixture::formAndEntry();

        $data = (new EntryParser())->parse($form, $entry, 'REF-TEST-0001');

        $ids = array_map(static fn (ArchiveField $field): string => $field->fieldId(), $data->fields());

        self::assertSame(['4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14', '15'], $ids);
    }

    public function test_it_includes_empty_fields_instead_of_skipping_them(): void
    {
        [$form, $entry] = ReferralFormFixture::formAndEntry();

        $data = (new EntryParser())->parse($form, $entry, 'REF-TEST-0001');

        $conditional = $this->fieldById($data->fields(), '14');
        $optional = $this->fieldById($data->fields(), '15');

        self::assertNotNull($conditional, 'a conditional field that was never submitted must still appear');
        self::assertTrue($conditional->isEmpty());
        self::assertNotNull($optional);
        self::assertTrue($optional->isEmpty());
    }

    public function test_it_combines_multi_input_name_field(): void
    {
        [$form, $entry] = ReferralFormFixture::formAndEntry();

        $data = (new EntryParser())->parse($form, $entry, 'REF-TEST-0001');

        $name = $this->fieldById($data->fields(), '5');

        self::assertNotNull($name);
        self::assertSame("Zoë O'Connor-Núñez", $name->value(), 'accents and apostrophes must be preserved exactly');
    }

    public function test_it_combines_checkbox_group_into_a_single_field(): void
    {
        [$form, $entry] = ReferralFormFixture::formAndEntry();

        $data = (new EntryParser())->parse($form, $entry, 'REF-TEST-0001');

        $checkboxes = $this->fieldById($data->fields(), '9');

        self::assertNotNull($checkboxes);
        self::assertSame('Housing, Legal advice', $checkboxes->value(), 'unchecked options must be excluded, checked ones joined');
    }

    public function test_it_preserves_long_textarea_values_in_full(): void
    {
        [$form, $entry] = ReferralFormFixture::formAndEntry();

        $data = (new EntryParser())->parse($form, $entry, 'REF-TEST-0001');

        $details = $this->fieldById($data->fields(), '6');

        self::assertNotNull($details);
        self::assertSame(trim($entry['6']), $details->value(), 'content must be preserved verbatim, only outer whitespace trimmed');
        self::assertGreaterThan(1000, strlen($details->value()));
    }

    public function test_it_preserves_special_characters_in_email(): void
    {
        [$form, $entry] = ReferralFormFixture::formAndEntry();

        $data = (new EntryParser())->parse($form, $entry, 'REF-TEST-0001');

        $email = $this->fieldById($data->fields(), '12');

        self::assertNotNull($email);
        self::assertSame("réferrer+test'@example.com", $email->value());
    }

    public function test_it_keeps_fields_with_identical_labels_distinguishable_by_id(): void
    {
        [$form, $entry] = ReferralFormFixture::formAndEntry();

        $data = (new EntryParser())->parse($form, $entry, 'REF-TEST-0001');

        $first = $this->fieldById($data->fields(), '7');
        $second = $this->fieldById($data->fields(), '8');

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame('Date', $first->label());
        self::assertSame('Date', $second->label());
        self::assertSame('16/07/1990', $first->value());
        self::assertSame('16/07/2026', $second->value());
    }

    public function test_it_captures_form_and_entry_metadata(): void
    {
        [$form, $entry] = ReferralFormFixture::formAndEntry();

        $data = (new EntryParser())->parse($form, $entry, 'REF-TEST-0001');

        self::assertSame('REF-TEST-0001', $data->reference());
        self::assertSame(6, $data->formId());
        self::assertSame('Example Referral Form (test fixture)', $data->formTitle());
        self::assertSame(999, $data->entryId());
        self::assertSame('2026-07-16 09:15:00', $data->submittedAt()->format('Y-m-d H:i:s'));
    }

    /** @param ArchiveField[] $fields */
    private function fieldById(array $fields, string $fieldId): ?ArchiveField
    {
        foreach ($fields as $field) {
            if ($field->fieldId() === $fieldId) {
                return $field;
            }
        }

        return null;
    }
}
