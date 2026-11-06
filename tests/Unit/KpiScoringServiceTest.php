<?php

namespace Tests\Unit;

use App\Services\Hrm\KpiScoringService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class KpiScoringServiceTest extends TestCase
{
    private KpiScoringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new KpiScoringService();
    }

    public function test_it_calculates_criterion_weighted_scores_and_overall_score(): void
    {
        $result = $this->service->calculate(
            ['1' => 4, '2' => 5, '3' => 3],
            [
                ['criterion_id' => '1', 'weight' => 25],
                ['criterion_id' => '2', 'weight' => 50],
                ['criterion_id' => '3', 'weight' => 25],
            ],
        );

        self::assertSame(['1' => 20.0, '2' => 50.0, '3' => 15.0], $result['weighted_scores']);
        self::assertSame(85.0, $result['overall_score']);
    }

    public function test_it_rejects_template_weights_that_do_not_equal_one_hundred(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->validateWeights([['name' => 'Quality', 'weight' => 99.99]]);
    }

    public function test_it_rejects_missing_or_out_of_range_ratings(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->calculate(['1' => 6], [['criterion_id' => '1', 'weight' => 100]]);
    }

    public function test_it_classifies_scores_at_each_boundary(): void
    {
        $rules = [
            ['minimum_score' => 0, 'maximum_score' => 60, 'label' => 'Unsatisfactory'],
            ['minimum_score' => 60, 'maximum_score' => 70, 'label' => 'Needs Improvement'],
            ['minimum_score' => 70, 'maximum_score' => 80, 'label' => 'Meets Expectations'],
            ['minimum_score' => 80, 'maximum_score' => 90, 'label' => 'Exceeds Expectations'],
            ['minimum_score' => 90, 'maximum_score' => 100, 'label' => 'Outstanding'],
        ];

        self::assertSame('Unsatisfactory', $this->service->classification(59.99, $rules));
        self::assertSame('Needs Improvement', $this->service->classification(60, $rules));
        self::assertSame('Meets Expectations', $this->service->classification(70, $rules));
        self::assertSame('Exceeds Expectations', $this->service->classification(80, $rules));
        self::assertSame('Outstanding', $this->service->classification(90, $rules));
        self::assertSame('Outstanding', $this->service->classification(100, $rules));
    }

    public function test_it_rejects_classification_ranges_with_gaps_or_overlaps(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->validateClassifications([
            ['minimum_score' => 0, 'maximum_score' => 50, 'label' => 'Low'],
            ['minimum_score' => 60, 'maximum_score' => 100, 'label' => 'High'],
        ]);
    }

    public function test_it_accepts_contiguous_classification_ranges_covering_zero_to_one_hundred(): void
    {
        $this->service->validateClassifications([
            ['minimum_score' => 50, 'maximum_score' => 100, 'label' => 'High'],
            ['minimum_score' => 0, 'maximum_score' => 50, 'label' => 'Low'],
        ]);

        self::assertTrue(true);
    }
}
