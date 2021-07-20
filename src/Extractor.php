<?php

namespace Kiboko\Component\Flow\SQL;

use Kiboko\Component\Bucket\AcceptanceResultBucket;
use Kiboko\Component\Bucket\EmptyResultBucket;
use Kiboko\Contract\Pipeline\ExtractorInterface;
use Kiboko\Contract\Pipeline\FlushableInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Extractor implements ExtractorInterface, FlushableInterface
{
    private LoggerInterface $logger;

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
                    $stmt->bindParam(is_string($parameter["key"]) ? ":".$parameter["key"] : $parameter["key"], $parameter["value"]);
                }
            }

            $stmt->execute();

            $results = $stmt->fetchAll(\PDO::FETCH_NAMED);
            while ($row = array_shift($results)) {
                yield new AcceptanceResultBucket($row);
            }
        } catch (\PDOException $exception) {
            $this->logger->critical($exception->getMessage(), ['exception' => $exception]);
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
