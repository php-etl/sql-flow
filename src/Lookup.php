<?php

namespace Kiboko\Component\Flow\SQL;

use Kiboko\Component\Bucket\AcceptanceResultBucket;
use Kiboko\Component\Bucket\EmptyResultBucket;
use Kiboko\Contract\Bucket\ResultBucketInterface;
use Kiboko\Contract\Mapping\CompiledMapperInterface;
use Kiboko\Contract\Pipeline\FlushableInterface;
use Kiboko\Contract\Pipeline\TransformerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @template Type
 * @implements FlushableInterface<Type>
 */
class Lookup implements TransformerInterface, FlushableInterface
{
    private LoggerInterface $logger;

    /**
     * @param \PDO $connection
     * @param CompiledMapperInterface<mixed,mixed,mixed> $mapper
     * @param array<int,string> $beforeQueries
     * @param array<int,string> $afterQueries
     * @param \Psr\Log\LoggerInterface|null $logger
     */
    public function __construct(
        private \PDO $connection,
        private CompiledMapperInterface $mapper,
        private array $beforeQueries = [],
        private array $afterQueries = [],
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @return \Generator<mixed>
     */
    public function transform(): \Generator
    {
        try {
            foreach ($this->beforeQueries as $beforeQuery) {
                $this->connection->exec($beforeQuery);
            }
        } catch (\PDOException $exception) {
            $this->logger->critical($exception->getMessage(), ['exception' => $exception]);
            return;
        }

        try {
            $line = yield;

            do {
                $line = ($this->mapper)($line);
            } while ($line = (yield new AcceptanceResultBucket($line)));
        } catch (\PDOException $exception) {
            $this->logger->critical($exception->getMessage(), ['exception' => $exception]);
        }
    }

    /**
     * @return ResultBucketInterface
     */
    public function flush(): ResultBucketInterface
    {
        try {
            foreach ($this->afterQueries as $afterQuery) {
                $this->connection->exec($afterQuery);
            }
        } catch (\PDOException $exception) {
            $this->logger->critical($exception->getMessage(), ['exception' => $exception]);
        }

        return new EmptyResultBucket();
    }
}
