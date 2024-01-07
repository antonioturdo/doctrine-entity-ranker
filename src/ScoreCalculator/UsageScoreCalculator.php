<?php

namespace AntonioTurdo\DoctrineEntityRanker\ScoreCalculator;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

class UsageScoreCalculator implements ScoreCalculatorInterface
{
    private array $paths;

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager, array $paths)
    {
        $this->entityManager = $entityManager;

        $this->paths = $paths;
    }

    public function calculateScores(): array
    {
        $entities = \array_map(fn (ClassMetadata $classMetadata) => $classMetadata->getName(), $this->entityManager->getMetadataFactory()->getAllMetadata());

        $parser = (new ParserFactory())->create(ParserFactory::ONLY_PHP7);

        $files = [];

        foreach ($this->paths as $path) {
            if (\is_dir($path)) {
                $files = \array_merge($this->findPHPFilesRecursively($path), $files);
            } else {
                $files[] = $path;
            }
        }

        $visitor = new class($entities) extends NameResolver {
            private array $entities;
            private array $scores;

            public function __construct(array $entities)
            {
                parent::__construct();

                $this->entities = $entities;
                $this->scores   = [];
            }

            public function leaveNode(Node $node)
            {
                if ($node instanceof Node\Name) {
                    if (!\in_array($node->toString(), $this->entities, true)) {
                        return;
                    }

                    if (!isset($this->scores[$node->toString()])) {
                        $this->scores[$node->toString()] = 1;
                    } else {
                        ++$this->scores[$node->toString()];
                    }
                }
            }

            public function getScores(): array
            {
                return $this->scores;
            }
        };

        foreach ($files as $path) {
            $code = \file_get_contents($path);

            $ast = $parser->parse($code);

            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);

            $traverser->traverse($ast);
        }

        return $visitor->getScores();
    }

    private function findPHPFilesRecursively(string $path): array
    {
        if (!\is_dir($path)) {
            return [];
        }

        $files = \glob($path . \DIRECTORY_SEPARATOR . '*.php');

        foreach (\glob($path . \DIRECTORY_SEPARATOR . '*', \GLOB_ONLYDIR) as $subdirectory) {
            $files = \array_merge($files, $this->findPHPFilesRecursively($subdirectory));
        }

        return $files;
    }
}
