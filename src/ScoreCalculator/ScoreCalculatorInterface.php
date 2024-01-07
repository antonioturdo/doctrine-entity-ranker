<?php

namespace AntonioTurdo\DoctrineEntityRanker\ScoreCalculator;

interface ScoreCalculatorInterface
{
    /**
     * @return array<string,int> the key of the returned array is the class name, the value is the score
     */
    public function calculateScores(): array;
}
