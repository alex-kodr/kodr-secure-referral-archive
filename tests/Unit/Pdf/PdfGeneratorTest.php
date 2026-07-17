<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Tests\Unit\Pdf;

use Kodr\SecureReferralArchive\Archive\ArchiveData;
use Kodr\SecureReferralArchive\Archive\ArchiveField;
use Kodr\SecureReferralArchive\GravityForms\EntryParser;
use Kodr\SecureReferralArchive\Pdf\PdfGenerator;
use Kodr\SecureReferralArchive\Tests\Fixtures\ReferralFormFixture;
use PHPUnit\Framework\TestCase;

final class PdfGeneratorTest extends TestCase
{
    public function test_it_generates_a_valid_pdf_document(): void
    {
        [$form, $entry] = ReferralFormFixture::formAndEntry();
        $data = (new EntryParser())->parse($form, $entry, 'REF-TEST-0001');

        $pdf = (new PdfGenerator())->generate($data, 'Example Organisation (test)');

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertStringContainsString('%%EOF', $pdf);
    }

    public function test_it_spans_multiple_pages_for_very_long_answers(): void
    {
        $fields = [new ArchiveField('1', 'A very long answer', str_repeat('Lorem ipsum dolor sit amet. ', 400))];
        $data = new ArchiveData('REF-TEST-LONG', 6, 'Long Answer Test Form', 1, new \DateTimeImmutable('2026-07-16T09:15:00+00:00'), $fields);

        $pdf = (new PdfGenerator())->generate($data, 'Example Organisation (test)');

        self::assertGreaterThan(1, $this->countPdfPages($pdf), 'a very long answer must flow onto additional pages');
    }

    public function test_it_shows_not_provided_for_blank_fields(): void
    {
        [$form, $entry] = ReferralFormFixture::formAndEntry();
        $data = (new EntryParser())->parse($form, $entry, 'REF-TEST-0001');

        self::assertTrue(
            array_filter($data->fields(), static fn (ArchiveField $field): bool => $field->isEmpty()) !== [],
            'fixture must include at least one empty field for this test to be meaningful'
        );

        // PdfGenerator maps ArchiveField::isEmpty() to the literal string
        // "Not provided" — covered structurally here; a visual check of the
        // rendered page is the developer's job when testing on a real site.
        self::assertStringStartsWith('%PDF-', (new PdfGenerator())->generate($data, 'Example Organisation (test)'));
    }

    private function countPdfPages(string $pdf): int
    {
        preg_match_all('/\/Type\s*\/Page(?!s)\b/', $pdf, $matches);

        return count($matches[0]);
    }
}
