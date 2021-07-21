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
            if($results === false) {
                //TODO throw an exception ?
                yield new EmptyResultBucket();
            } else {
                while ($row = array_shift($results)) {
                    yield new AcceptanceResultBucket($row);
                }
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
