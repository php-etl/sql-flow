<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\SQL;

use Kiboko\Component\Bucket\AcceptanceResultBucket;
use Kiboko\Contract\Pipeline\ExtractorInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Extractor implements ExtractorInterface
{
    /** @var callable|null */
    private $parametersBinder;

    /**
     * @param array<int,string> $beforeQueries
     * @param array<int,string> $afterQueries
     */
    public function __construct(
        private readonly \PDO $connection,
        private readonly string $query,
        callable $parametersBinder = null,
        private readonly array $beforeQueries = [],
        private readonly array $afterQueries = [],
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
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

            if (null !== $this->parametersBinder) {
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
