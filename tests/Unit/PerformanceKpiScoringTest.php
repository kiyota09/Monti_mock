<?php

namespace Tests\Unit;

use App\Http\Controllers\Hrm\Workforce\ProbationEvaluationController;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PerformanceKpiScoringTest extends TestCase
{
    public function test_it_calculates_the_weighted_score(): void
    {
        $score = ProbationEvaluationController::calculateWeightedScore(
            ['productivity' => 5, 'quality' => 4, 'safety' => 3],
            ['productivity' => 50, 'quality' => 30, 'safety' => 20],
        );

        self::assertSame(86.0, $score);
        self::assertSame('Exceeds Expectations', ProbationEvaluationController::classifyScore($score));
    }

    public function test_it_rejects_weights_that_do_not_total_one_hundred(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ProbationEvaluationController::calculateWeightedScore(['a' => 3], ['a' => 80]);
    }

    public function test_it_rejects_missing_or_invalid_ratings(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ProbationEvaluationController::calculateWeightedScore(['a' => 6], ['a' => 100]);
    }

    public function test_classification_thresholds_are_consistent(): void
    {
        self::assertSame('Outstanding', ProbationEvaluationController::classifyScore(90));
        self::assertSame('Meets Expectations', ProbationEvaluationController::classifyScore(70));
        self::assertSame('Needs Improvement', ProbationEvaluationController::classifyScore(60));
        self::assertSame('Unsatisfactory', ProbationEvaluationController::classifyScore(59.99));
    }
}
