<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\SQL;

use Kiboko\Component\Bucket\AcceptanceResultBucket;
use Kiboko\Component\Bucket\EmptyResultBucket;
use Kiboko\Contract\Pipeline\FlushableInterface;
use Kiboko\Contract\Pipeline\LoaderInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @template Type
 *
 * @implements FlushableInterface<Type>
 */
class Loader implements LoaderInterface, FlushableInterface
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

        $statement = $this->connection->prepare($this->query);

        $input = yield;

        do {
            try {
                if (null !== $this->parametersBinder) {
                    ($this->parametersBinder)($statement, $input);
                }

                $statement->execute();
            } catch (\PDOException $exception) {
                $this->logger->critical($exception->getMessage(), ['exception' => $exception]);
            }
        } while ($input = yield new AcceptanceResultBucket($input));
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
