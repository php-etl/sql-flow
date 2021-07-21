<?php

namespace Kiboko\Component\Flow\SQL;

use Kiboko\Component\Bucket\EmptyResultBucket;
use Kiboko\Contract\Bucket\ResultBucketInterface;
use Kiboko\Contract\Pipeline\FlushableInterface;
use Kiboko\Contract\Pipeline\LoaderInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @template Type
 * @implements FlushableInterface<Type>
 */
class Loader implements LoaderInterface, FlushableInterface
{
    private LoggerInterface $logger;

    /**
     * @param \PDO $connection
     * @param string $query
     * @param array<int,array> $parameters
     * @param array<int,string> $beforeQueries
     * @param array<int,string> $afterQueries
     * @param \Psr\Log\LoggerInterface|null $logger
     */
    public function __construct(
        private \PDO $connection,
        private string $query,
        private array $parameters = [],
        private array $beforeQueries = [],
        private array $afterQueries = [],
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
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
        try {
            do {
                $stmt = $this->connection->prepare($this->query);

                if ($this->parameters) {
                    foreach ($this->parameters as $parameter) {
                        $stmt->bindParam(is_string($parameter["key"]) ? ":".$parameter["key"] : $parameter["key"], $parameter["value"]);
                    }
                }

                $stmt->execute();
            } while ($input = yield new \Kiboko\Component\Bucket\AcceptanceResultBucket($input));
        } catch (\PDOException $exception) {
            $this->logger->critical($exception->getMessage(), ['exception' => $exception]);
        }
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
