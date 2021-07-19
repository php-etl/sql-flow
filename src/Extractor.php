<?php

namespace Kiboko\Component\Flow\SQL;

use Kiboko\Component\Bucket\AcceptanceResultBucket;
use Kiboko\Contract\Pipeline\ExtractorInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Extractor implements ExtractorInterface
{
    private LoggerInterface $logger;

    public function __construct(
        private \PDO $connection,
        private string $query,
        private array $parameters = [],
        private array $beforeQueries = [],
        private array $afterQueries = [],
        ?LoggerInterface $logger = null
    )
    {
        $this->logger = $logger ?? new NullLogger();
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
            $stmt = $this->connection->prepare($this->query);

            if ($this->parameters) {
                foreach ($this->parameters as $parameter) {
                    $stmt->bindParam(":".$parameter["key"], $parameter["value"]);
                }
            }

            $stmt->execute();

            foreach ($stmt->fetchAll(\PDO::FETCH_NAMED) as $item) {
                yield new AcceptanceResultBucket($item);
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