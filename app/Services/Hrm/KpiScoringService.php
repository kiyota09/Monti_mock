<?php

namespace App\Services\Hrm;

use Illuminate\Support\Collection;
use InvalidArgumentException;

class KpiScoringService
{
    public function validateWeights(array $criteria): void
    {
        if ($criteria === []) {
            throw new InvalidArgumentException('Add at least one KPI criterion.');
        }
        foreach ($criteria as $criterion) {
            if (empty($criterion['name']) || ! is_numeric($criterion['weight'] ?? null) || (float) $criterion['weight'] <= 0) {
                throw new InvalidArgumentException('Every criterion needs a name and a positive weight.');
            }
        }
        if (abs(array_sum(array_map(fn ($criterion) => (float) $criterion['weight'], $criteria)) - 100) > 0.001) {
            throw new InvalidArgumentException('KPI criterion weights must total exactly 100%.');
        }
    }

    public function calculate(array $ratings, array $criteria): array
    {
        $weights = [];
        foreach ($criteria as $criterion) {
            $key = (string) ($criterion['criterion_id'] ?? $criterion['id'] ?? '');
            if ($key === '' || ! isset($ratings[$key]) || ! is_numeric($ratings[$key]) || (int) $ratings[$key] < 1 || (int) $ratings[$key] > 5) {
                throw new InvalidArgumentException('Rate every KPI criterion from 1 to 5 before submitting.');
            }
            $weights[$key] = (float) $criterion['weight'];
        }
        if (abs(array_sum($weights) - 100) > 0.001) {
            throw new InvalidArgumentException('The assigned KPI template does not total 100%.');
        }
        $weighted = [];
        foreach ($weights as $key => $weight) {
            $weighted[$key] = round(((int) $ratings[$key] / 5) * $weight, 2);
        }
        return ['weighted_scores' => $weighted, 'overall_score' => round(array_sum($weighted), 2)];
    }

    public function classification(float $score, Collection|array $rules): string
    {
        foreach ($rules as $index => $rule) {
            $maximum = (float) data_get($rule, 'maximum_score');
            if ($score >= (float) data_get($rule, 'minimum_score') && ($score < $maximum || ($index === count($rules) - 1 && $score <= $maximum))) {
                return (string) data_get($rule, 'label');
            }
        }
        throw new InvalidArgumentException('No configured classification covers this score.');
    }

    public function validateClassifications(array $rules): void
    {
        usort($rules, fn ($a, $b) => (float) $a['minimum_score'] <=> (float) $b['minimum_score']);
        if ($rules === [] || abs((float) $rules[0]['minimum_score']) > 0.001 || abs((float) end($rules)['maximum_score'] - 100) > 0.001) {
            throw new InvalidArgumentException('Classification ranges must cover scores from 0 to 100.');
        }
        for ($i = 0; $i < count($rules); $i++) {
            if ((float) $rules[$i]['minimum_score'] > (float) $rules[$i]['maximum_score']) {
                throw new InvalidArgumentException('A classification range has an invalid minimum and maximum.');
            }
            if ($i > 0 && abs((float) $rules[$i - 1]['maximum_score'] - (float) $rules[$i]['minimum_score']) > 0.001) {
                throw new InvalidArgumentException('Classification ranges must have no gaps or overlaps.');
            }
        }
    }
}
