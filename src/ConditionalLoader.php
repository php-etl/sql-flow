<?php

namespace Kiboko\Component\Flow\SQL;

use Kiboko\Component\Bucket\AcceptanceResultBucket;
use Kiboko\Component\Bucket\EmptyResultBucket;
use Kiboko\Contract\Bucket\ResultBucketInterface;
use Kiboko\Contract\Mapping\CompiledMapperInterface;
use Kiboko\Contract\Pipeline\FlushableInterface;
use Kiboko\Contract\Pipeline\LoaderInterface;
use Kiboko\Contract\Pipeline\TransformerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @template Type
 * @implements FlushableInterface<Type>
 */
class ConditionalLoader implements LoaderInterface, FlushableInterface
{
    private LoggerInterface $logger;
    /** @var callable|null */
    private $alternatives;

    /**
     * @param \PDO $connection
     * @param callable|null $alternatives
     * @param array<int,string> $beforeQueries
     * @param array<int,string> $afterQueries
     * @param \Psr\Log\LoggerInterface|null $logger
     */
    public function __construct(
        private \PDO $connection,
        callable $alternatives = null,
        private array $beforeQueries = [],
        private array $afterQueries = [],
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->alternatives = $alternatives;
    }

    /**
     * @return \Generator<mixed>
     */
    public function load(): \Generator
    {
        try {
            foreach ($this->beforeQueries as $beforeQuery) {
                $this->connection->exec($beforeQuery);
            }
        } catch (\PDOException $exception) {
            $this->logger->critical($exception->getMessage(), ['exception' => $exception]);
            return;
        }

        $input = yield;

        do {
            ($this->alternatives)($input, $this->connection);
        } while ($input = yield new AcceptanceResultBucket($input));
    }

    /**
     * @return ResultBucketInterface<Type>
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
