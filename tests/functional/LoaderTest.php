<?php

declare(strict_types=1);

namespace functional\Kiboko\Component\Flow\SQL;

use Kiboko\Component\Flow\SQL\Loader;
use Kiboko\Component\PHPUnitExtension\Assert\LoaderAssertTrait;
use Kiboko\Contract\Pipeline\PipelineRunnerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
/**
 * @internal
 *
 * @coversNothing
 */
class LoaderTest extends TestCase
{
    use LoaderAssertTrait;

    private const DATABASE_PATH = __DIR__.'/dbtest.sqlite';
    private \PDO $connection;

    protected function setUp(): void
    {
        parent::setUp();
        copy(__DIR__.'/fixtures/dbtest.sqlite', self::DATABASE_PATH);
        $this->connection = new \PDO('sqlite:'.self::DATABASE_PATH);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function loadWithNamedParameters(): void
    {
        $data = [
            'firstname' => 'Lorem',
            'lastname' => 'Ipsum',
            'nationality' => 'French',
        ];

        $loader = new Loader(
            connection: $this->connection,
            query: 'INSERT INTO user (firstname,lastname,nationality) VALUES (:firstname,:lastname,:nationality)',
            parametersBinder: function (\PDOStatement $statement, $input): void {
                $statement->bindParam('firstname', $input['firstname']);
                $statement->bindParam('lastname', $input['lastname']);
                $statement->bindParam('nationality', $input['nationality']);
            },
        );
        $this->assertLoaderLoadsExactly(
            [
                $data,
            ],
            [
                $data,
            ],
            $loader,
        );

        $statement = $this->connection->query(
            'SELECT firstname,lastname,nationality FROM user WHERE firstname = "Lorem"'
        );

        $result = $statement->fetch(\PDO::FETCH_ASSOC);
        $diff = array_diff_assoc($data, $result);
        $this->assertEmpty($diff);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function loadWithoutParameters(): void
    {
        $data = [
            'firstname' => 'Lorem',
            'lastname' => 'Ipsum',
            'nationality' => 'French',
        ];

        $loader = new Loader(
            connection: $this->connection,
            query: 'INSERT INTO user(firstname, lastname, nationality) VALUES ("Lorem","Ipsum","French")'
        );

        $this->assertLoaderLoadsExactly(
            [
                $data,
            ],
            [
                $data,
            ],
            $loader,
        );

        $statement = $this->connection->query(
            'SELECT firstname,lastname,nationality FROM user WHERE firstname = "Lorem"'
        );

        $result = $statement->fetch(\PDO::FETCH_ASSOC);
        $diff = array_diff_assoc($data, $result);
        $this->assertEmpty($diff);
    }

    public static function loadProvider(): array
    {
        return [
            [
                'coca',
                2,
            ],
            [
                'zero',
                0,
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('loadProvider')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function loadWithBeforeQueries(mixed $name, mixed $price): void
    {
        $loader = new Loader(
            connection: $this->connection,
            query: 'INSERT INTO product (name, price) VALUES (:name, :price)',
            parametersBinder: function (\PDOStatement $statement, $input): void {
                $statement->bindParam('name', $input['name']);
                $statement->bindParam('price', $input['price']);
            },
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

        $this->assertSame($result['name'], 'product');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('loadProvider')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function loadWithAfterQueries(mixed $name, mixed $price): void
    {
        $loader = new Loader(
            connection: $this->connection,
            query: 'INSERT INTO product (name, price) VALUES (:name, :price)',
            parametersBinder: function (\PDOStatement $statement, $input): void {
                $statement->bindParam('name', $input['name']);
                $statement->bindParam('price', $input['price']);
            },
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

        /**
         * Check if the table is dropped.
         */
        $query = $this->connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='product'");
        $result = $query->fetch(\PDO::FETCH_NAMED);

        $this->assertFalse($result);
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
