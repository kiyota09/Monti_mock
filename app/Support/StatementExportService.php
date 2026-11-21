<?php

namespace App\Support;

use App\Models\Hrm\BillingStatement;

/**
 * Billing statement downloads shared by the HR and agency-portal sides.
 *
 * Dependency-free:
 *  - pdf():   a print-ready HTML statement; the browser's Print dialog
 *             "Save as PDF" produces the PDF (no dompdf required).
 *  - excel(): an Excel-compatible XML workbook with explicit black text.
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
            $xml = static fn ($value) => htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
            $row = static function (array $cells, string $style = '') use ($xml): string {
                $styleAttr = $style !== '' ? ' ss:StyleID="' . $style . '"' : '';
                $result = '<Row>';
                foreach ($cells as $cell) {
                    $result .= '<Cell' . $styleAttr . '><Data ss:Type="String">' . $xml($cell) . '</Data></Cell>';
                }
                return $result . '</Row>';
            };

            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<?mso-application progid="Excel.Sheet"?>';
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
            echo '<Styles><Style ss:ID="Default" ss:Name="Normal"><Font ss:Color="#000000"/></Style><Style ss:ID="Header"><Font ss:Color="#000000" ss:Bold="1"/><Interior ss:Color="#D9EAF7" ss:Pattern="Solid"/></Style></Styles>';
            echo '<Worksheet ss:Name="Statement"><Table>';
            echo $row(['Statement', $s->code]);
            echo $row(['Agency', $data['agency']?->name]);
            echo $row(['Period', $data['period']]);
            echo $row(['Status', $s->status]);
            echo $row([]);
            echo $row(['Worker', 'Request', 'Department', 'Days', 'Hours', 'OT hours', 'Rate', 'Labor'], 'Header');
            foreach ($data['calc']['lines'] as $l) {
                echo $row([$l['worker_name'], $l['request_code'], $l['department'], $l['days'], $l['hours'], $l['overtime_hours'], $l['rate'], $l['labor']]);
            }
            echo $row([]);
            $days = array_sum(array_column($data['calc']['lines'], 'days'));
            $hours = array_sum(array_column($data['calc']['lines'], 'hours'));
            echo $row(['Records', $data['calc']['count']]);
            echo $row(['Days', $days]);
            echo $row(['Hours', $hours]);
            echo $row([]);
            echo $row(['Labor cost', '', '', '', '', '', '', $s->labor_cost]);
            echo $row(['Service fee', '', '', '', '', '', '', $s->service_fee]);
            echo $row(['Total', '', '', '', '', '', '', $s->total]);
            echo '</Table></Worksheet></Workbook>';
        }, $s->code . '.xls', ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8']);
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
