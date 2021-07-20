<?php /** @noinspection SqlResolve */

namespace functional\Kiboko\Component\Flow\SQL;

use Kiboko\Component\Flow\SQL\Loader;
use Kiboko\Component\PHPUnitExtension\Assert\LoaderAssertTrait;
use PHPUnit\Framework\TestCase;

class LoaderTest extends TestCase
{
    use LoaderAssertTrait;

    private const DATABASE_PATH = __DIR__.'/dbtest2.sqlite';

    protected function setUp(): void
    {
        parent::setUp();
        copy(__DIR__.'/dbtest.sqlite',self::DATABASE_PATH);
    }

    public static function tearDownAfterClass(): void
    {
        $db = new \PDO('sqlite:' . self::DATABASE_PATH);
        $db->exec('DROP TABLE IF EXISTS foo');
    }

    public function testBasicLoader(): void
    {
        $loader = new Loader(
            connection: new \PDO('sqlite:' . self::DATABASE_PATH),
            query: 'INSERT INTO user VALUES (?,"Julien")'
        );
        $this->assertLoaderLoadsLike(
            [
                [
                    'id' => '1',
                    'name' => 'JulienLePokemon',
                ],
            ],[
                [
                    'id' => '1',
                    'name' => 'JulienLePokemon',
                ],
            ],
            $loader,
        );
    }

//    public function testExtractorWithBeforeQueries(): void
//    {
//        $extractor = new Loader(
//            connection: new \PDO('sqlite:' . self::DATABASE_PATH),
//            query: 'SELECT * FROM foo',
//            beforeQueries: [
//                'CREATE TABLE IF NOT EXISTS foo (id INTEGER NOT NULL, value VARCHAR(255) NOT NULL)',
//                'INSERT INTO foo (id, value) VALUES (1, "Lorem ipsum dolor")',
//                'INSERT INTO foo (id, value) VALUES (2, "Sit amet consecutir")',
//            ]
//        );
//        $this->assertExtractorExtractsExactly(
//            [
//                [
//                    'id' => '1',
//                    'value' => 'Lorem ipsum dolor',
//                ],
//                [
//                    'id' => '2',
//                    'value' => 'Sit amet consecutir',
//                ]
//            ],
//            $extractor,
//        );
//    }
//
//    public function testExtractorWithAfterQueries(): void
//    {
//        $connection = new \PDO('sqlite:' . self::DATABASE_PATH);
//        $extractor = new Loader(
//            connection: $connection,
//            query: 'SELECT * FROM foo',
//            beforeQueries: [
//                'CREATE TABLE IF NOT EXISTS foo (id INTEGER NOT NULL, value VARCHAR(255) NOT NULL)',
//            ],
//            afterQueries: [
//                'DROP TABLE foo'
//            ]
//        );
//        $this->assertExtractorExtractsExactly(
//            new \EmptyIterator(),
//            $extractor
//        );
//        $query = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='foo'");
//        $result = $query->fetch(\PDO::FETCH_NAMED);
//        $this->assertFalse($result);
//    }
//
//    public function testExtractorQueryWithNamedParameters(): void
//    {
//        $extractor = new Loader(
//            connection: new \PDO('sqlite:' . self::DATABASE_PATH),
//            query: 'SELECT * FROM foo WHERE id = :id',
//            parameters: [
//                [
//                    'key' => 'id',
//                    'value' => 1
//                ]
//            ],
//            beforeQueries: [
//                'CREATE TABLE IF NOT EXISTS foo (id INTEGER NOT NULL, value VARCHAR(255) NOT NULL)',
//                'INSERT INTO foo (id, value) VALUES (1, "Lorem ipsum dolor")',
//                'INSERT INTO foo (id, value) VALUES (2, "Sit amet consecutir")',
//            ],
//        );
//        $this->assertExtractorExtractsExactly(
//            [
//                [
//                    'id' => '1',
//                    'value' => 'Lorem ipsum dolor',
//                ]
//            ],
//            $extractor
//        );
//    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unlink(self::DATABASE_PATH);
    }
}
