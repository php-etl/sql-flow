<?php

declare(strict_types=1);

namespace functional\Kiboko\Component\Flow\SQL;

use Kiboko\Component\Flow\SQL\Lookup;
use Kiboko\Component\PHPUnitExtension\Assert\TransformerAssertTrait;
use Kiboko\Contract\Pipeline\PipelineRunnerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @internal
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
/**
 * @internal
 *
 * @coversNothing
 */
class LookupTest extends TestCase
{
    use TransformerAssertTrait;

    private const DATABASE_PATH = __DIR__.'/dbtest.sqlite';
    private \PDO $connection;

    protected function setUp(): void
    {
        parent::setUp();
        copy(__DIR__.'/fixtures/dbtest.sqlite', self::DATABASE_PATH);
        $this->connection = new \PDO('sqlite:'.self::DATABASE_PATH);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('mapperProvider')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function lookupWithMapper(mixed $mapper): void
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
                    'nationality' => 'France',
                ],
                [
                    'id' => 2,
                    'firstname' => 'John',
                    'lastname' => 'Doe',
                    'nationality' => 'English',
                ],
                [
                    'id' => 3,
                    'firstname' => 'Frank',
                    'lastname' => 'O\'hara',
                    'nationality' => 'American',
                ],
            ],
            $lookup
        );
    }

    public static function mapperProvider(): array
    {
        return [
            [
                new class() implements \Kiboko\Contract\Mapping\CompiledMapperInterface {
                    public function __construct(private readonly LoggerInterface $logger = new NullLogger())
                    {
                    }

                    public function __invoke($input, $output = null)
                    {
                        $output = $input;

                        return (function ($input) use ($output) {
                            $lookup = (function ($input) {
                                $data = null;
                                try {
                                    $dbh = new \PDO('sqlite:'.__DIR__.'/dbtest.sqlite');
                                    $stmt = $dbh->prepare('SELECT nationality FROM user WHERE id = ?');
                                    $stmt->bindParam(1, $input['id']);
                                    $stmt->execute();
                                    $data = $stmt->fetch(\PDO::FETCH_NAMED);
                                } catch (\PDOException $exception) {
                                    $this->logger->critical($exception->getMessage(), ['exception' => $exception]);
                                }

                                return $data;
                            })($input);

                            return (fn () => (function () use ($lookup, $output) {
                                $output['nationality'] = $lookup['nationality'];

                                return $output;
                            })())();
                        })($input);
                    }
                },
            ],
        ];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        chmod(self::DATABASE_PATH, 0o644);
        unlink(self::DATABASE_PATH);
    }

    public function pipelineRunner(): PipelineRunnerInterface
    {
        return new PipelineRunner();
    }
}
