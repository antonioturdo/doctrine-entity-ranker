<?php

namespace AntonioTurdo\DoctrineEntityRanker\ScoreCalculator;

class CompoundScoreCalculator implements ScoreCalculatorInterface
{
    private array $weights;
    /** @var array<ScoreCalculatorInterface> */
    private array $calculators;

    public function addScoreCalculator(ScoreCalculatorInterface $scoreCalculator, int $weight): void
    {
        $this->calculators[]                    = $scoreCalculator;
        $this->weights[$scoreCalculator::class] = $weight;
    }

    public function calculateScores(): array
    {
        $scoresByCalculator     = [];
        $totalScoreByCalculator = [];
        $scores                 = [];

        foreach ($this->calculators as $calculator) {
            $scoresByCalculator[$calculator::class]     = $calculator->calculateScores();
            $totalScoreByCalculator[$calculator::class] = \array_sum($scoresByCalculator[$calculator::class]);
        }

        $maxTotalScore = \max($totalScoreByCalculator);

        foreach ($scoresByCalculator as $calculatorName => &$calculatorScores) {
            $weight = $this->weights[$calculatorName];

            \array_walk($calculatorScores, function (&$value, $key) use ($maxTotalScore, $totalScoreByCalculator, $calculatorName, $weight) {
                $value = (int) ($value * $maxTotalScore / $totalScoreByCalculator[$calculatorName] * $weight);
            });

            foreach ($calculatorScores as $className => $value) {
                $scores[$className] = ($scores[$className] ?? 0) + $value;
            }
        }

        return $scores;
    }
}
