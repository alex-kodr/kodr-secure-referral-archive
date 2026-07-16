<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Tests\Unit\Archive;

use Kodr\SecureReferralArchive\Archive\ArchiveData;
use Kodr\SecureReferralArchive\Archive\ObjectKeyFactory;
use PHPUnit\Framework\TestCase;

final class ObjectKeyFactoryTest extends TestCase
{
    public function test_it_builds_the_expected_key_structure(): void
    {
        $data = $this->makeData(6, 'Online Referral Form - Survivor', '2026-07-16T09:15:00+00:00', 'REF-20260716-A82F19');
        $factory = new ObjectKeyFactory();

        self::assertSame(
            'form-6-online-referral-form-survivor/2026/07/REF-20260716-A82F19/referral.json',
            $factory->jsonKey($data)
        );
        self::assertSame(
            'form-6-online-referral-form-survivor/2026/07/REF-20260716-A82F19/referral.pdf',
            $factory->pdfKey($data)
        );
    }

    public function test_it_is_deterministic_for_the_same_data(): void
    {
        $data = $this->makeData(6, 'Online Referral Form', '2026-07-16T09:15:00+00:00', 'REF-20260716-A82F19');
        $factory = new ObjectKeyFactory();

        self::assertSame($factory->jsonKey($data), $factory->jsonKey($data));
        self::assertSame($factory->pdfKey($data), (new ObjectKeyFactory())->pdfKey($data));
    }

    public function test_it_neutralises_path_traversal_attempts_in_the_form_title(): void
    {
        $data = $this->makeData(6, '../../etc/passwd', '2026-07-16T09:15:00+00:00', 'REF-1');
        $key = (new ObjectKeyFactory())->jsonKey($data);

        self::assertStringNotContainsString('..', $key);
        self::assertStringNotContainsString('/etc/', $key);
    }

    public function test_it_strips_unsafe_characters_from_the_reference(): void
    {
        $data = $this->makeData(6, 'Form', '2026-07-16T09:15:00+00:00', 'REF/../1; DROP TABLE');
        $key = (new ObjectKeyFactory())->jsonKey($data);

        self::assertStringNotContainsString('..', $key);
        self::assertStringNotContainsString(' ', $key);
        self::assertStringNotContainsString(';', $key);
    }

    public function test_it_never_includes_personal_data(): void
    {
        // The key must be derivable purely from form id/title, submission
        // month, and the (non-identifying) reference — never from field
        // values such as a survivor's name or email.
        $data = $this->makeData(6, 'Online Referral Form', '2026-07-16T09:15:00+00:00', 'REF-20260716-A82F19');
        $key = (new ObjectKeyFactory())->jsonKey($data);

        self::assertStringNotContainsString('@', $key);
    }

    private function makeData(int $formId, string $title, string $submittedAt, string $reference): ArchiveData
    {
        return new ArchiveData($reference, $formId, $title, 1, new \DateTimeImmutable($submittedAt), []);
    }
}
