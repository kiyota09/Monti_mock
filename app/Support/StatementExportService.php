<?php

namespace App\Support;

use App\Models\Hrm\BillingStatement;

/**
 * Billing statement downloads shared by the HR and agency-portal sides.
 *
 * Dependency-free:
 *  - pdf():   a print-ready HTML statement; the browser's Print dialog
 *             "Save as PDF" produces the PDF (no dompdf required).
 *  - excel(): a UTF-8 CSV spreadsheet that opens directly in Excel.
 */
class StatementExportService
{
    public static function pdf(BillingStatement $statement)
    {
        return response()
            ->view('exports.agency-statement', self::data($statement))
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public static function excel(BillingStatement $statement)
    {
        $data = self::data($statement);
        $s = $data['statement'];

        return response()->streamDownload(function () use ($data, $s) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel reads accented names correctly.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Statement', $s->code]);
            fputcsv($out, ['Agency', $data['agency']?->name]);
            fputcsv($out, ['Period', $data['period']]);
            fputcsv($out, ['Status', $s->status]);
            fputcsv($out, []);

            fputcsv($out, ['Worker', 'Worker ID', 'Request', 'Department', 'Days', 'Hours', 'OT hours', 'Rate', 'Labor']);
            foreach ($data['calc']['lines'] as $l) {
                fputcsv($out, [
                    $l['worker_name'],
                    $l['worker_code'],
                    $l['request_code'],
                    $l['department'],
                    $l['days'],
                    $l['hours'],
                    $l['overtime_hours'],
                    $l['rate'],
                    $l['labor'],
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, ['Labor cost', '', '', '', '', '', '', '', $s->labor_cost]);
            fputcsv($out, ['Service fee', '', '', '', '', '', '', '', $s->service_fee]);
            fputcsv($out, ['Total', '', '', '', '', '', '', '', $s->total]);

            fclose($out);
        }, $s->code . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{statement:BillingStatement,agency:mixed,calc:array,period:string}
     */
    protected static function data(BillingStatement $statement): array
    {
        $statement->loadMissing('agency');
        $calc = BillingCalc::forStatement($statement);

        return [
            'statement' => $statement,
            'agency' => $statement->agency,
            'calc' => $calc,
            'period' => BillingCalc::periodLabel(
                $statement->period_start->toDateString(),
                $statement->period_end->toDateString()
            ),
        ];
    }
}
