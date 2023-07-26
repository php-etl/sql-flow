<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\SQL;

use Kiboko\Component\Bucket\AcceptanceResultBucket;
use Kiboko\Component\Bucket\EmptyResultBucket;
use Kiboko\Contract\Mapping\CompiledMapperInterface;
use Kiboko\Contract\Pipeline\FlushableInterface;
use Kiboko\Contract\Pipeline\TransformerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @template Type
 *
 * @implements FlushableInterface<Type>
 */
class Lookup implements TransformerInterface, FlushableInterface
{
    /**
     * @param CompiledMapperInterface<mixed,mixed,mixed> $mapper
     * @param array<int,string>                          $beforeQueries
     * @param array<int,string>                          $afterQueries
     */
    public function __construct(private readonly \PDO $connection, private readonly CompiledMapperInterface $mapper, private readonly array $beforeQueries = [], private readonly array $afterQueries = [], private readonly LoggerInterface $logger = new NullLogger())
    {
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
            $this->logger->critical($exception->getMessage(), ['exception' => $exception, 'item' => $line]);
        }
    }

    public function flush(): EmptyResultBucket
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
