<?php

namespace DarkPanda\Laravel\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Mockery as m;

class PostgreSQLCursorsTestModel extends Model
{
    use PostgreSQLCursors;

    protected $table = 'cursor_models';
    protected $fillable = ['name'];
}

class PostgreSQLCursorsTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->connection = (new PostgreSQLCursorsTestModel)->getConnection();
        $this->connection->enableQueryLog();
        $this->connection->beginTransaction();
    }

    public function tearDown()
    {
        parent::tearDown();

        $this->connection->rollback();
        $this->connection->flushQueryLog();
    }

    public function testCursorEach()
    {
        $mockFoo = PostgreSQLCursorsTestModel::create(['name' => 'Foo']);
        $mockBar = PostgreSQLCursorsTestModel::create(['name' => 'Bar']);

        $scope = PostgreSQLCursorsTestModel::where('name', 'Foo');
        $cursor = $scope->cursor();
        $cursorName = $cursor->getCursorName();
        $objects = [];

        $cursor->each(function ($object) use (&$objects) {
            $objects[] = $object;
        });

        $queryLog = $this->connection->getQueryLog();

        $this->assertQuery(
            $queryLog[2],
            "declare {$cursorName} cursor for select * from \"cursor_models\" where \"name\" = ?",
            ['Foo']
        );

        $this->assertQuery($queryLog[3], "fetch forward 1 from {$cursorName}");
        $this->assertQuery($queryLog[4], "fetch forward 1 from {$cursorName}");
        $this->assertQuery($queryLog[5], "close {$cursorName}");

        $this->assertRecordsEqual([$mockFoo], $objects);
    }

    public function testCursorForEach()
    {
        $mockFoo = PostgreSQLCursorsTestModel::create(['name' => 'Foo']);
        $mockBar = PostgreSQLCursorsTestModel::create(['name' => 'Bar']);

        $scope = PostgreSQLCursorsTestModel::where('name', 'Foo');
        $cursor = $scope->cursor();
        $cursorName = $cursor->getCursorName();
        $objects = [];

        foreach ($cursor as $object) {
            $objects[] = $object;
        }

        $queryLog = $this->connection->getQueryLog();

        $this->assertQuery(
            $queryLog[2],
            "declare {$cursorName} cursor for select * from \"cursor_models\" where \"name\" = ?",
            ['Foo']
        );

        $this->assertQuery($queryLog[3], "fetch forward 1 from {$cursorName}");
        $this->assertQuery($queryLog[4], "fetch forward 1 from {$cursorName}");
        $this->assertQuery($queryLog[5], "close {$cursorName}");

        $this->assertRecordsEqual([$mockFoo], $objects);
    }

    public function testNestedCursors()
    {
        $mockFoo = PostgreSQLCursorsTestModel::create(['name' => 'Foo']);
        $mockBar = PostgreSQLCursorsTestModel::create(['name' => 'Bar']);

        $scopeOuter = PostgreSQLCursorsTestModel::where('name', 'Foo');
        $cursorOuter = $scopeOuter->cursor();
        $cursorNameOuter = $cursorOuter->getCursorName();

        $scopeInner = PostgreSQLCursorsTestModel::where('name', 'Bar');
        $cursorInner = $scopeInner->cursor();
        $cursorNameInner = $cursorInner->getCursorName();

        $objects = [];

        $cursorOuter->each(function ($object) use (&$objects, $cursorInner) {
            $objects[] = $object;

            $cursorInner->each(function ($object) use (&$objects) {
                $objects[] = $object;
            });
        });

        $queryLog = $this->connection->getQueryLog();

        $this->assertQuery(
            $queryLog[2],
            "declare {$cursorNameOuter} cursor for select * from \"cursor_models\" where \"name\" = ?",
            ['Foo']
        );

        $this->assertQuery($queryLog[3], "fetch forward 1 from {$cursorNameOuter}");

        $this->assertQuery(
            $queryLog[4],
            "declare {$cursorNameInner} cursor for select * from \"cursor_models\" where \"name\" = ?",
            ['Bar']
        );

        $this->assertQuery($queryLog[5], "fetch forward 1 from {$cursorNameInner}");
        $this->assertQuery($queryLog[6], "fetch forward 1 from {$cursorNameInner}");
        $this->assertQuery($queryLog[7], "close {$cursorNameInner}");
        $this->assertQuery($queryLog[8], "fetch forward 1 from {$cursorNameOuter}");
        $this->assertQuery($queryLog[9], "close {$cursorNameOuter}");

        $this->assertRecordsEqual([$mockFoo, $mockBar], $objects);
    }

    public function testExceptions()
    {
        $mockFoo = PostgreSQLCursorsTestModel::create(['name' => 'Foo']);
        $mockBar = PostgreSQLCursorsTestModel::create(['name' => 'Bar']);

        $scope = PostgreSQLCursorsTestModel::orderBy('created_at');
        $cursor = $scope->cursor();
        $cursorName = $cursor->getCursorName();
        $transactionLevel = 0;
        $exceptionCaught = false;
        $objects = [];

        try {
            foreach ($cursor as $object) {
                $transactionLevel = $this->connection->transactionLevel();
                $objects[] = $object;
                throw new \ErrorException('foo');
            }
        } catch (\ErrorException $e) {
            $exceptionCaught = true;
        }

        $this->assertEquals(2, $transactionLevel);
        $this->assertEquals(1, $this->connection->transactionLevel());
        $this->assertTrue($exceptionCaught);

        $queryLog = $this->connection->getQueryLog();

        $this->assertQuery(
            $queryLog[2],
            "declare {$cursorName} cursor for select * from \"cursor_models\" order by \"created_at\" asc"
        );

        $this->assertQuery($queryLog[3], "fetch forward 1 from {$cursorName}");

        $this->assertRecordsEqual([$mockFoo], $objects);
    }

    public function testCursorEachMultiple()
    {
        $mocks = array_map(function ($i) {
            return PostgreSQLCursorsTestModel::create(['name' => "Record {$i}"]);
        }, range(1, 10));

        $scope = PostgreSQLCursorsTestModel::query();
        $cursor = $scope->cursor();
        $cursorName = $cursor->getCursorName();
        $chunks = [];

        $cursor->each(3, function ($chunk) use (&$chunks) {
            $chunks[] = $chunk;
        });

        $queryLog = $this->connection->getQueryLog();

        $this->assertQuery(
            $queryLog[10],
            "declare {$cursorName} cursor for select * from \"cursor_models\""
        );

        $this->assertQuery($queryLog[11], "fetch forward 3 from {$cursorName}");
        $this->assertQuery($queryLog[12], "fetch forward 3 from {$cursorName}");
        $this->assertQuery($queryLog[13], "fetch forward 3 from {$cursorName}");
        $this->assertQuery($queryLog[14], "fetch forward 3 from {$cursorName}");
        $this->assertQuery($queryLog[15], "fetch forward 3 from {$cursorName}");
        $this->assertQuery($queryLog[16], "close {$cursorName}");

        $expectedChunks = array_chunk($mocks, 3);
        $this->assertEquals(4, count($expectedChunks));
        $this->assertRecordsEqual($expectedChunks[0], $chunks[0]);
        $this->assertRecordsEqual($expectedChunks[1], $chunks[1]);
        $this->assertRecordsEqual($expectedChunks[2], $chunks[2]);
        $this->assertRecordsEqual($expectedChunks[3], $chunks[3]);
    }

    public function testCursorEachMultipleWithBadValue()
    {
        $scope = PostgreSQLCursorsTestModel::query();
        $cursor = $scope->cursor();

        $this->setExpectedException(\InvalidArgumentException::class, '$count should be > 0.');

        $cursor->each(-1, 'callback');
    }

    private function assertQuery($query, $expectedSql, $expectedBindings = [])
    {
        $this->assertEquals($expectedSql, $query['query']);
        $this->assertEquals($expectedBindings, $query['bindings']);
    }

    private function assertRecordsEqual($expected, $actual)
    {
        $this->assertEquals(
            array_map(function ($model) {
                return $model->id;
            }, $expected),

            array_map(function ($model) {
                return $model->id;
            }, $actual)
        );
    }
}
