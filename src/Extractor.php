<?php

namespace Kiboko\Component\Flow\SQL;

use Kiboko\Component\Bucket\AcceptanceResultBucket;
use Kiboko\Component\Bucket\EmptyResultBucket;
use Kiboko\Contract\Pipeline\ExtractorInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Extractor implements ExtractorInterface
{
    private LoggerInterface $logger;
    /** @var callable|null */
    private $parametersBinder;

    /**
     * @param \PDO $connection
     * @param string $query
     * @param callable|null $parametersBinder
     * @param array<int,string> $beforeQueries
     * @param array<int,string> $afterQueries
     * @param \Psr\Log\LoggerInterface|null $logger
     */
    public function __construct(
        private \PDO $connection,
        private string $query,
        callable $parametersBinder = null,
        private array $beforeQueries = [],
        private array $afterQueries = [],
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->parametersBinder = $parametersBinder;
    }

    public function extract(): iterable
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
            $statement = $this->connection->prepare($this->query);

            if ($this->parametersBinder !== null) {
                ($this->parametersBinder)($statement);
            }

            $statement->execute();

            while ($row = $statement->fetch(\PDO::FETCH_NAMED)) {
                yield new AcceptanceResultBucket($row);
            }
        } catch (\PDOException $exception) {
            $this->logger->critical($exception->getMessage(), ['exception' => $exception]);
        }

        try {
            foreach ($this->afterQueries as $afterQuery) {
                $this->connection->exec($afterQuery);
            }
        } catch (\PDOException $exception) {
            $this->logger->critical($exception->getMessage(), ['exception' => $exception]);
        }
    }
}
