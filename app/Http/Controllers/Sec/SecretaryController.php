<?php

namespace App\Http\Controllers\Sec;

use App\Http\Controllers\Controller;

abstract class SecretaryController extends Controller
{
    /**
     * Generate a sequential reference number: PREFIX-YYYYMM-00001.
     */
    protected function refNo(string $prefix, string $model, string $column = 'ref_no'): string
    {
        $stamp = now()->format('Ym');
        $last = $model::where($column, 'like', "{$prefix}-{$stamp}-%")
            ->orderBy($column, 'desc')
            ->value($column);

        $next = $last ? ((int) substr(strrchr($last, '-'), 1) + 1) : 1;

        return sprintf('%s-%s-%05d', $prefix, $stamp, $next);
    }
}
