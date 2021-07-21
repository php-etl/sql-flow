<?php

namespace functional\Kiboko\Component\Flow\SQL;

use Kiboko\Component\Flow\SQL\Loader;
use Kiboko\Component\PHPUnitExtension\Assert\LoaderAssertTrait;
use PHPUnit\Framework\TestCase;

class LoaderTest extends TestCase
{
    use LoaderAssertTrait;

    private const DATABASE_PATH = __DIR__ . '/dbtest2.sqlite';
    private \PDO $connection;

    protected function setUp(): void
    {
        parent::setUp();
        copy(__DIR__ . '/fixtures/dbtest.sqlite', self::DATABASE_PATH);
        $this->connection = new \PDO('sqlite:' . self::DATABASE_PATH);
    }

    public function testLoadWithNamedParameters(): void
    {
        $loader = new Loader(
            connection: $this->connection,
            query: 'INSERT INTO user (firstname,lastname,nationality) VALUES (:firstname,:lastname,:nationality)'
        );
        $this->assertLoaderLoadsExactly(
            [
                [
                    'firstname' => 'jul',
                    'lastname' => 'marseille',
                    'nationality' => 'french',
                ],
            ],
            [
                [
                    'firstname' => 'jul',
                    'lastname' => 'marseille',
                    'nationality' => 'french',
                ],
            ],
            $loader,
        );
    }

    public function testLoadWithUnamedParameters(): void
    {
        $loader = new Loader(
            connection: $this->connection,
            query: 'INSERT INTO user(firstname, lastname, nationality) VALUES ("jul","marseille","french")'
        );
        $this->assertLoaderLoadsExactly(
            [
                [
                    'firstname' => 'jul',
                    'lastname' => 'marseille',
                    'nationality' => 'french',
                ],
            ],
            [
                [
                    'firstname' => 'jul',
                    'lastname' => 'marseille',
                    'nationality' => 'french',
                ],
            ],
            $loader,
        );
    }

    public function loadProvider(): array
    {
        return [
            [
                'coca',2
            ],
            [
                'zero',0
            ]
        ];
    }

    /**
     * @dataProvider loadProvider
     */
    public function testLoadWithBeforeQueries($name,$price): void
    {
        $loader = new Loader(
            connection: $this->connection,
            query: 'INSERT INTO product (name,price) VALUES (:name,:price)',
            parameters: [
                [
                    'key' => 'name',
                    'value' => $name
                ],
                [
                    'key' => 'price',
                    'value' => $price
                ],
            ],
            beforeQueries: [
                'CREATE TABLE IF NOT EXISTS product (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(255) NOT NULL,
                    price INTEGER NOT NULL
                )',
            ],
        );
        $this->assertLoaderLoadsExactly(
            [
                [
                    'name' => $name,
                    'price' => $price,
                ],
            ],
            [
                [
                    'name' => $name,
                    'price' => $price,
                ],
            ],
            $loader,
        );

        $query = $this->connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='product'");
        $result = $query->fetch(\PDO::FETCH_NAMED);

        $this->assertSame($result['name'],'product');
    }

    /**
     * @dataProvider loadProvider
     */
    public function testLoadWithAfterQueries($name,$price): void
    {
        $loader = new Loader(
            connection: $this->connection,
            query: 'INSERT INTO product (name,price) VALUES (:name,:price)',
            parameters: [
                [
                    'key' => 'name',
                    'value' => $name
                ],
                [
                    'key' => 'price',
                    'value' => $price
                ],
            ],
            beforeQueries: [
                'CREATE TABLE IF NOT EXISTS product (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(255) NOT NULL,
                    price INTEGER NOT NULL
                )',
            ],
            afterQueries: [
                'DROP TABLE product',
            ],
        );
        $this->assertLoaderLoadsExactly(
            [
                [
                    'name' => $name,
                    'price' => $price,
                ],
            ],
            [
                [
                    'name' => $name,
                    'price' => $price,
                ],
            ],
            $loader,
        );

        $query = $this->connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='product'");
        $result = $query->fetch(\PDO::FETCH_NAMED);

        $this->assertFalse($result);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        chmod(self::DATABASE_PATH, 0644);
        unlink(self::DATABASE_PATH);
    }
}
