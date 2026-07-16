<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Pdf;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * TCPDF with a plain footer showing only a page number — no logo, no links,
 * nothing that could leak an admin/S3 URL or a server file path.
 */
final class ReferralPdfDocument extends \TCPDF
{
    public function Footer(): void // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}
