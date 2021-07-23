<?php

namespace functional\Kiboko\Component\Flow\SQL;

use Kiboko\Component\Flow\SQL\Lookup;
use Kiboko\Component\PHPUnitExtension\Assert\TransformerAssertTrait;
use Kiboko\Contract\Mapping\CompiledMapperInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class LookupTest extends TestCase
{
    use TransformerAssertTrait;

    private const DATABASE_PATH = __DIR__ . '/dbtest.sqlite';
    private \PDO $connection;

    protected function setUp(): void
    {
        parent::setUp();
        copy(__DIR__ . '/fixtures/dbtest.sqlite', self::DATABASE_PATH);
        $this->connection = new \PDO('sqlite:' . self::DATABASE_PATH);
    }

    /**
     * @dataProvider mapperProvider
     */
    public function testLookupWithMapper($mapper): void
    {
        $lookup = new Lookup($this->connection, $mapper);

        $this->assertTransformerTransformsExactly(
            [
                [
                    'id' => 1,
                    'firstname' => 'Jean Pierre',
                    'lastname' => 'Martin',
                ],
                [
                    'id' => 2,
                    'firstname' => 'John',
                    'lastname' => 'Doe',
                ],
                [
                    'id' => 3,
                    'firstname' => 'Frank',
                    'lastname' => 'O\'hara',
                ],
            ],
            [
                [
                    'id' => 1,
                    'firstname' => 'Jean Pierre',
                    'lastname' => 'Martin',
                    'nationality' => 'France'
                ],
                [
                    'id' => 2,
                    'firstname' => 'John',
                    'lastname' => 'Doe',
                    'nationality' => 'English'
                ],
                [
                    'id' => 3,
                    'firstname' => 'Frank',
                    'lastname' => 'O\'hara',
                    'nationality' => 'American'
                ],
            ],
            $lookup
        );
    }

    public function mapperProvider(): array
    {
        return [
            [
                new class implements \Kiboko\Contract\Mapping\CompiledMapperInterface {
                    private LoggerInterface $logger;

                    public function __construct(?LoggerInterface $logger = null)
                    {
                        $this->logger = $logger ?? new NullLogger();
                    }

                    public function __invoke($input, $output = null)
                    {
                        $output = $input;
                        $output = (function ($input) use ($output) {
                            $lookup = (function ($input) use ($output) {
                                try {
                                    $dbh = new \PDO('sqlite:'.__DIR__.'/dbtest.sqlite');
                                    $stmt = $dbh->prepare('SELECT nationality FROM user WHERE id = ?');
                                    $stmt->bindParam(1, $input["id"]);
                                    $stmt->execute();
                                    $data = $stmt->fetch(\PDO::FETCH_NAMED);
                                } catch (\PDOException $exception) {
                                    $this->logger->critical($exception->getMessage(), ['exception' => $exception]);
                                }
                                return $data;
                            })($input);
                            $output = (function () use ($lookup, $output) {
                                $output = (function () use ($lookup, $output) {
                                    $output['nationality'] = $lookup["nationality"];
                                    return $output;
                                })();
                                return $output;
                            })();
                            return $output;
                        })($input);
                        return $output;
                    }
                }
            ]
        ];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        chmod(self::DATABASE_PATH, 0644);
        unlink(self::DATABASE_PATH);
    }
}
