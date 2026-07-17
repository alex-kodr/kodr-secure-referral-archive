<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Tests\Fixtures;

/**
 * Invented Gravity Forms form/entry fixtures for parser tests. Every value
 * here is fictional — none of it is, or is derived from, a real referral.
 *
 * Mirrors the field-storage shapes real Gravity Forms forms use: page/html/
 * section breaks (display-only, no entry value), simple single-value fields,
 * multi-input fields (name, address-like), checkbox groups, conditional
 * fields that were never submitted, and fields sharing an identical label.
 */
final class ReferralFormFixture
{
    /** @return array{0: array<string,mixed>, 1: array<string,mixed>} */
    public static function formAndEntry(): array
    {
        $form = [
            'id'     => 6,
            'title'  => 'Example Referral Form (test fixture)',
            'fields' => [
                ['id' => 1, 'type' => 'page', 'label' => 'Page 1'],
                ['id' => 2, 'type' => 'html', 'label' => 'Instructions'],
                ['id' => 3, 'type' => 'section', 'label' => 'About the referral'],
                ['id' => 4, 'type' => 'text', 'label' => 'Referral reference'],
                [
                    'id'     => 5,
                    'type'   => 'name',
                    'label'  => 'Applicant name',
                    'inputs' => [
                        ['id' => '5.3', 'label' => 'First'],
                        ['id' => '5.6', 'label' => 'Last'],
                    ],
                ],
                ['id' => 6, 'type' => 'textarea', 'label' => 'Details'],
                ['id' => 7, 'type' => 'date', 'label' => 'Date'],
                ['id' => 8, 'type' => 'date', 'label' => 'Date'],
                [
                    'id'     => 9,
                    'type'   => 'checkbox',
                    'label'  => 'Support needs',
                    'inputs' => [
                        ['id' => '9.1', 'label' => 'Housing'],
                        ['id' => '9.2', 'label' => 'Counselling'],
                        ['id' => '9.3', 'label' => 'Legal advice'],
                    ],
                ],
                ['id' => 10, 'type' => 'radio', 'label' => 'Preferred contact method'],
                ['id' => 11, 'type' => 'select', 'label' => 'Region'],
                ['id' => 12, 'type' => 'email', 'label' => "Referrer's email"],
                ['id' => 13, 'type' => 'phone', 'label' => 'Contact number'],
                ['id' => 14, 'type' => 'text', 'label' => 'Conditional follow-up question'],
                ['id' => 15, 'type' => 'text', 'label' => 'Empty optional field'],
            ],
        ];

        $entry = [
            'id'           => 999,
            'date_created' => '2026-07-16 09:15:00',
            '4'            => 'REF-TEST-0001',
            '5.3'          => 'Zoë',
            '5.6'          => "O'Connor-Núñez",
            '6'            => str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 40),
            '7'            => '16/07/1990',
            '8'            => '16/07/2026',
            '9.1'          => 'Housing',
            '9.2'          => '',
            '9.3'          => 'Legal advice',
            '10'           => 'Email',
            '11'           => 'North West',
            '12'           => "réferrer+test'@example.com",
            '13'           => '+44 7700 900123',
            // Field 14 (conditional) was hidden and never submitted.
            // Field 15 (optional) was left blank.
        ];

        return [$form, $entry];
    }
}
