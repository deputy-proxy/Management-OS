<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class EmployeeEvaluationScoringService
{
    /**
     * Validate template builder blocks. Only rated and Yes/No criteria carry weights.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     */
    public function validateTemplate(array $blocks): void
    {
        $criteria = [];

        foreach ($blocks as $block) {
            $type = $block['type'] ?? null;
            $data = $block['data'] ?? [];

            if (! in_array($type, ['rated_criterion', 'yes_no', 'text_feedback'], true) || ! is_array($data)) {
                throw ValidationException::withMessages(['criteria' => 'The evaluation template contains an unsupported criterion.']);
            }

            if ($type !== 'text_feedback') {
                $weight = $data['weight'] ?? null;

                if (! is_numeric($weight) || (float) $weight <= 0 || (float) $weight > 100) {
                    throw ValidationException::withMessages(['criteria' => 'Every rated and Yes/No criterion must have a weight greater than 0 and at most 100.']);
                }

                $criteria[] = ['type' => $type, 'weight' => (float) $weight];
            }
        }

        $this->assertWeightsTotalOneHundred($criteria);
    }

    /**
     * Convert template builder blocks into an immutable response snapshot.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    public function snapshotTemplate(array $blocks): array
    {
        $this->validateTemplate($blocks);

        return collect($blocks)->map(function (array $block): array {
            $data = $block['data'] ?? [];

            return [
                'type' => $block['type'],
                'label' => $data['label'] ?? $data['question'] ?? null,
                'description' => $data['description'] ?? null,
                'weight' => $data['weight'] ?? null,
                'score' => null,
                'answer' => null,
                'notes' => null,
            ];
        })->all();
    }

    /**
     * Calculate the normalized weighted percentage for a saved response snapshot.
     *
     * @param  array<int, array<string, mixed>>  $criteria
     */
    public function calculate(array $criteria): float
    {
        $weightedCriteria = [];
        $weightedSum = 0.0;

        foreach ($criteria as $criterion) {
            $type = $criterion['type'] ?? null;

            if ($type === 'text_feedback') {
                if (blank($criterion['notes'] ?? null)) {
                    throw ValidationException::withMessages(['criteria' => 'Complete all text feedback criteria before submitting.']);
                }

                continue;
            }

            $weight = $criterion['weight'] ?? null;

            if (! is_numeric($weight) || (float) $weight <= 0 || (float) $weight > 100) {
                throw ValidationException::withMessages(['criteria' => 'Every rated and Yes/No criterion must have a valid weight.']);
            }

            if ($type === 'rated_criterion') {
                $score = filter_var($criterion['score'] ?? null, FILTER_VALIDATE_INT);

                if ($score === false || $score < 1 || $score > 5) {
                    throw ValidationException::withMessages(['criteria' => 'Select a score from 1 to 5 for every rated criterion.']);
                }

                $normalizedScore = (($score - 1) / 4) * 100;
            } elseif ($type === 'yes_no') {
                $answer = $criterion['answer'] ?? null;

                if (! in_array($answer, ['yes', 'no'], true)) {
                    throw ValidationException::withMessages(['criteria' => 'Answer every Yes/No criterion before submitting.']);
                }

                $normalizedScore = $answer === 'yes' ? 100.0 : 0.0;
            } else {
                throw ValidationException::withMessages(['criteria' => 'The evaluation contains an unsupported criterion type.']);
            }

            $weight = (float) $weight;
            $weightedCriteria[] = ['type' => $type, 'weight' => $weight];
            $weightedSum += $normalizedScore * $weight;
        }

        $this->assertWeightsTotalOneHundred($weightedCriteria);

        return round($weightedSum / 100, 2);
    }

    /**
     * @param  array<int, array{type: string, weight: float}>  $criteria
     */
    private function assertWeightsTotalOneHundred(array $criteria): void
    {
        if ($criteria === []) {
            throw ValidationException::withMessages(['criteria' => 'Add at least one weighted rated or Yes/No criterion.']);
        }

        $total = array_sum(array_column($criteria, 'weight'));

        // Four decimal places are supported to avoid binary floating-point noise.
        if (number_format(round($total, 4), 4, '.', '') !== '100.0000') {
            throw ValidationException::withMessages(['criteria' => 'The weights of all rated and Yes/No criteria must total exactly 100%.']);
        }
    }
}
