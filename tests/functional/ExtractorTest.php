<?php

namespace functional\Kiboko\Component\Flow\SQL;

use Kiboko\Component\Flow\SQL\Extractor;
use Kiboko\Component\PHPUnitExtension\Assert\ExtractorAssertTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ExtractorTest extends TestCase
{
    use ExtractorAssertTrait;

    private const DATABASE_PATH = __DIR__ . '/dbtest.sqlite';
    private \PDO $connection;

    private mixed $logger;

    protected function setUp(): void
    {
        parent::setUp();
        copy(__DIR__ . '/fixtures/dbtest.sqlite', self::DATABASE_PATH);
        $this->connection = new \PDO('sqlite:' . self::DATABASE_PATH);

        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testBasicExtract(): void
    {
        $extractor = new Extractor(
            connection: $this->connection,
            query: 'SELECT * FROM user'
        );

        $this->assertExtractorExtractsExactly(
            [
                [
                    'id' => '1',
                    'firstname' => 'Jean Pierre',
                    'lastname' => 'Martin',
                    'nationality' => 'France'
                ],
                [
                    'id' => '2',
                    'firstname' => 'John',
                    'lastname' => 'Doe',
                    'nationality' => 'English'
                ],
                [
                    'id' => '3',
                    'firstname' => 'Frank',
                    'lastname' => 'O\'hara',
                    'nationality' => 'American'
                ],
                [
                    'id' => '4',
                    'firstname' => 'Barry',
                    'lastname' => 'Tatum',
                    'nationality' => 'Swiss'
                ]
            ],
            $extractor,
        );
    }

    public function testLogOnExceptionOnExtract(): void
    {
        $extractor = new Extractor(
            connection: $this->connection,
            query: 'SELECT * FROM missingtable',
            logger: $this->logger
        );

        $this->logger->expects($this->once())
            ->method('critical');

        $this->assertExtractorExtractsExactly(
            [
            ],
            $extractor,
        );
    }


    public function testExtractWithBeforeQueries(): void
    {
        $extractor = new Extractor(
            connection: $this->connection,
            query: 'SELECT * FROM foo',
            beforeQueries: [
                'CREATE TABLE IF NOT EXISTS foo (id INTEGER NOT NULL, value VARCHAR(255) NOT NULL)',
                'INSERT INTO foo (id, value) VALUES (1, "Lorem ipsum dolor")',
                'INSERT INTO foo (id, value) VALUES (2, "Sit amet consecutir")',
            ]
        );

        $this->assertExtractorExtractsExactly(
            [
                [
                    'id' => '1',
                    'value' => 'Lorem ipsum dolor',
                ],
                [
                    'id' => '2',
                    'value' => 'Sit amet consecutir',
                ]
            ],
            $extractor,
        );
    }

    public function testExtractLogOnExceptionWithBeforeQueries(): void
    {
        $extractor = new Extractor(
            connection: $this->connection,
            query: 'SELECT * FROM foo',
            beforeQueries: [
                'WRONGSQL',
            ],
            logger: $this->logger
        );

        $this->logger->expects($this->once())
            ->method('critical');

        $this->assertExtractorExtractsExactly(
            [
            ],
            $extractor,
        );
    }

    public function testExtractWithAfterQueries(): void
    {
        $extractor = new Extractor(
            connection: $this->connection,
            query: 'SELECT * FROM foo',
            beforeQueries: [
                'CREATE TABLE IF NOT EXISTS foo (id INTEGER NOT NULL, value VARCHAR(255) NOT NULL)',
                'INSERT INTO foo (id,value) VALUES (1,"test")',
            ],
            afterQueries: [
               'DROP TABLE foo'
            ]
        );

        $this->assertExtractorExtractsExactly(
            [
                [
                    'id' => '1',
                    'value' => 'test'
                ]
            ],
            $extractor
        );

        $query = $this->connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='foo'");
        $result = $query->fetch(\PDO::FETCH_NAMED);

        $this->assertFalse($result);
    }

    public function testExtractLogOnExceptionWithAfterQueries(): void
    {
        $extractor = new Extractor(
            connection: $this->connection,
            query: 'SELECT * FROM foo',
            beforeQueries: [
                'CREATE TABLE IF NOT EXISTS foo (id INTEGER NOT NULL, value VARCHAR(255) NOT NULL)',
                'INSERT INTO foo (id,value) VALUES (1,"test")',
            ],
            afterQueries: [
                'WRONGSQL'
            ],
            logger: $this->logger
        );

        $this->logger->expects($this->once())
            ->method('critical');

        $this->assertExtractorExtractsExactly(
            [
                [
                    'id' => '1',
                    'value' => 'test'
                ]
            ],
            $extractor
        );
    }

    public function testExtractWithBeforeQueriesAndNamedParameters(): void
    {
        $extractor = new Extractor(
            connection: $this->connection,
            query: 'SELECT * FROM foo WHERE id = :id',
            parametersBinder: function (\PDOStatement $statement) {
                $var = 1;
                $statement->bindParam('id', $var);
            },
            beforeQueries: [
                'CREATE TABLE IF NOT EXISTS foo (id INTEGER NOT NULL, value VARCHAR(255) NOT NULL)',
                'INSERT INTO foo (id, value) VALUES (1, "Lorem ipsum dolor")',
                'INSERT INTO foo (id, value) VALUES (2, "Sit amet consecutir")',
            ],
        );

        $this->assertExtractorExtractsExactly(
            [
                [
                    'id' => '1',
                    'value' => 'Lorem ipsum dolor',
                ]
            ],
            $extractor
        );
    }

    public function testExtractWithBeforeQueriesAndUnnamedParameters(): void
    {
        $extractor = new Extractor(
            connection: $this->connection,
            query: 'SELECT * FROM foo WHERE id = ?',
            parametersBinder: function (\PDOStatement $statement) {
                $var = 1;
                $statement->bindParam(1, $var);
            },
            beforeQueries: [
                'CREATE TABLE IF NOT EXISTS foo (id INTEGER NOT NULL, value VARCHAR(255) NOT NULL)',
                'INSERT INTO foo (id, value) VALUES (1, "Lorem ipsum dolor")',
                'INSERT INTO foo (id, value) VALUES (2, "Sit amet consecutir")',
            ],
        );

        $this->assertExtractorExtractsExactly(
            [
                [
                    'id' => '1',
                    'value' => 'Lorem ipsum dolor',
                ]
            ],
            $extractor
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        chmod(self::DATABASE_PATH, 0644);
        unlink(self::DATABASE_PATH);
    }
}
