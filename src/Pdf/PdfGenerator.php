<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Pdf;

use Kodr\SecureReferralArchive\Archive\ArchiveData;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders an ArchiveData value object as a plain, robust PDF referral
 * document using TCPDF. Contains only the organisation name, form title,
 * reference, submission date and question/answer pairs — never an entry
 * admin URL, S3 URL, or server file path.
 */
final class PdfGenerator
{
    public function generate(ArchiveData $data, string $organisationName): string
    {
        $pdf = new ReferralPdfDocument('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Kodr Secure Referral Archive');
        $pdf->SetAuthor($organisationName);
        $pdf->SetTitle('Referral ' . $data->reference());
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, $organisationName, 0, 1);

        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 8, $data->formTitle(), 0, 1);
        $pdf->Cell(0, 6, 'Referral reference: ' . $data->reference(), 0, 1);
        $pdf->Cell(0, 6, 'Submitted: ' . $data->submittedAt()->format('j F Y, H:i') . ' UTC', 0, 1);
        $pdf->Ln(4);

        foreach ($data->fields() as $field) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->MultiCell(0, 6, $field->label(), 0, 'L');

            $pdf->SetFont('helvetica', '', 10);
            $value = $field->isEmpty() ? 'Not provided' : $field->value();
            $pdf->MultiCell(0, 6, $value, 0, 'L');
            $pdf->Ln(2);
        }

        $output = $pdf->Output('', 'S');

        return is_string($output) ? $output : '';
    }
}
