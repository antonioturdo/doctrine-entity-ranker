<?php

namespace AntonioTurdo\DoctrineEntityRanker\ScoreCalculator;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Entity;

class DefinitionScoreCalculator implements ScoreCalculatorInterface
{
    public int $fieldScore            = 1;
    public int $associationScore      = 3;
    public int $methodScore           = 5;
    public int $repositoryScore       = 15;
    public int $repositoryMethodScore = 5;

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function calculateScores(): array
    {
        $scores = [];

        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $meta) {
            $scores[$meta->getName()] = $this->calculateEntityScore($meta->getName());
        }

        return $scores;
    }

    private function calculateEntityScore(string $className): int
    {
        $entityMetadata = $this->entityManager->getClassMetadata($className);

        $entityScore = 0;

        foreach ($entityMetadata->getFieldNames() as $fieldName) {
            $entityScore += $this->fieldScore;
        }

        foreach ($entityMetadata->getAssociationNames() as $associationName) {
            $entityScore += $this->associationScore;
        }

        $entityScore += $this->getClassMethodsCount($className, ['/^get/', '/^set/', '/^is/', '/^has/']) * $this->methodScore;

        $entityRepository = $this->getEntityRepository($className);

        if (null !== $entityRepository) {
            $entityScore += $this->repositoryScore;

            $entityScore += $this->getClassMethodsCount($entityRepository) * $this->repositoryMethodScore;
        }

        return $entityScore;
    }

    private function getEntityRepository(string $className): ?string
    {
        $classReflection = new \ReflectionClass($className);

        $entityAttributes = $classReflection->getAttributes(Entity::class);

        if (empty($entityAttributes)) {
            return null;
        }

        return $entityAttributes[0]->getArguments()['repositoryClass'] ?? null;
    }

    private function getClassMethodsCount(string $className, array $regexToExclude = []): int
    {
        $classReflection = new \ReflectionClass($className);

        $classMethods = $classReflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        $filteredMethods = \array_filter($classMethods, fn (\ReflectionMethod $method) => $method->getDeclaringClass()->getName() === $className);

        foreach ($regexToExclude as $regex) {
            $filteredMethods = \array_filter($filteredMethods, fn (\ReflectionMethod $method) => !\preg_match($regex, $method->getName()));
        }

        return \count($filteredMethods);
    }
}
