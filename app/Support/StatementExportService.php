<?php

namespace App\Support;

use App\Models\Hrm\BillingStatement;
use App\Exports\StatementExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Billing statement downloads shared by the HR and agency-portal sides.
 */
class StatementExportService
{
    public static function pdf(BillingStatement $statement)
    {
        $statement->loadMissing('agency');
        $calc = BillingCalc::forStatement($statement);

        return Pdf::loadView('pdfs.agency-statement', [
            'statement' => $statement,
            'agency' => $statement->agency,
            'calc' => $calc,
            'period' => BillingCalc::periodLabel(
                $statement->period_start->toDateString(),
                $statement->period_end->toDateString()
            ),
        ])->download("{$statement->code}.pdf");
    }

    public static function excel(BillingStatement $statement)
    {
        $statement->loadMissing('agency');
        $calc = BillingCalc::forStatement($statement);

        return Excel::download(new StatementExport($calc, $statement->code), "{$statement->code}.xlsx");
    }
}
