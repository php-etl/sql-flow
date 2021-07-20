<?php

namespace functional\Kiboko\Component\Flow\SQL;

use Kiboko\Component\Flow\SQL\Extractor;
use Kiboko\Component\PHPUnitExtension\Assert\ExtractorAssertTrait;
use PHPUnit\Framework\TestCase;

class ExtractorTest extends TestCase
{
    use ExtractorAssertTrait;

    public static function tearDownAfterClass(): void
    {
        $db = new \PDO('sqlite:'.__DIR__.'/dbtest.sqlite');
        $db->exec('DROP TABLE IF EXISTS foo');
    }

    public function testBasicExtractor(): void
    {
        $extractor = new Extractor(
            connection: new \PDO('sqlite:'.__DIR__.'/dbtest.sqlite'),
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

    public function testExtractorWithBeforeQueries(): void
    {
        $extractor = new Extractor(
            connection: new \PDO('sqlite:'.__DIR__.'/dbtest.sqlite'),
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

    public function testExtractorWithAfterQueries(): void
    {
        $connection = new \PDO('sqlite:'.__DIR__.'/dbtest.sqlite');

        $extractor = new Extractor(
            connection: $connection,
            query: 'SELECT * FROM foo',
            beforeQueries: [
                'CREATE TABLE IF NOT EXISTS foo (id INTEGER NOT NULL, value VARCHAR(255) NOT NULL)',
            ],
            afterQueries: [
               'DROP TABLE foo'
            ]
        );

        $this->assertExtractorExtractsExactly(
            new \EmptyIterator(),
            $extractor
        );

        $query = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='foo'");
        $result = $query->fetch(\PDO::FETCH_NAMED);

        $this->assertFalse($result);
    }

    public function testExtractorQueryWithNamedParameters(): void
    {
        $extractor = new Extractor(
            connection: new \PDO('sqlite:'.__DIR__.'/dbtest.sqlite'),
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

    public function testExtractorQueryWithUnnamedParameters(): void
    {
        $extractor = new Extractor(
            connection: new \PDO('sqlite:'.__DIR__.'/dbtest.sqlite'),
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
}
