<?php

namespace functional\Kiboko\Component\Flow\SQL;

use Kiboko\Component\Flow\SQL\Extractor;
use Kiboko\Component\PHPUnitExtension\Assert\ExtractorAssertTrait;
use PHPUnit\Framework\TestCase;

class ExtractorTest extends TestCase
{
    use ExtractorAssertTrait;

    private const DATABASE_PATH = __DIR__ . '/dbtest2.sqlite';
    private \PDO $connection;

    protected function setUp(): void
    {
        parent::setUp();
        copy(__DIR__ . '/fixtures/dbtest.sqlite', self::DATABASE_PATH);
        $this->connection = new \PDO('sqlite:' . self::DATABASE_PATH);
    }

    public function testExtract(): void
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

    public function testExtractWithBeforeQueriesAndNamedParameters(): void
    {
        $extractor = new Extractor(
            connection: $this->connection,
            query: 'SELECT * FROM foo WHERE id = :id',
            parameters: [
                [
                    'key' => 'id',
                    'value' => 1
                ]
            ],
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

    public function testExtractWithBeforeQueriesAndUnameddParameters(): void
    {
        $extractor = new Extractor(
            connection: $this->connection,
            query: 'SELECT * FROM foo WHERE id = ?',
            parameters: [
                [
                    'key' => 1,
                    'value' => 1
                ]
            ],
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
