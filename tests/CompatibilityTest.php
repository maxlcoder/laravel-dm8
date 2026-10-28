<?php

namespace LaravelDm8\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use LaravelDm8\Dm8\Dm8Connection;
use LaravelDm8\Dm8\Eloquent\DmEloquent;
use LaravelDm8\Dm8\Schema\DmBlueprint;
use LaravelDm8\Dm8\Schema\Sequence;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

class CompatibilityTest extends TestCase
{
    private function connection(?PDO $pdo = null): Dm8Connection
    {
        return new Dm8Connection($pdo ?? $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock(), '', 'app_', [
            'driver' => 'dm', 'prefix_schema' => 'APP',
        ]);
    }

    public function test_select_keeps_schema_table_prefix_and_bindings(): void
    {
        $query = $this->connection()->table('users')->selectRaw('count(*) as total')->where('active', 1);
        $this->assertStringContainsString('"APP"."app_users"', $query->toSql());
        $this->assertStringContainsString('count(*) as total', $query->toSql());
        $this->assertSame([1], $query->getBindings());
    }

    public function test_wrap_table_keeps_aliases_and_raw_expression(): void
    {
        $grammar = $this->connection()->getQueryGrammar();
        $this->assertSame('DUAL', $grammar->wrapTable(new Expression('DUAL')));
        $this->assertSame('"APP"."app_users" app_u', $grammar->wrapTable('users as u'));
    }

    public function test_pagination_still_compiles_dm_rownum(): void
    {
        $sql = $this->connection()->table('users')->limit(10)->offset(5)->toSql();
        $this->assertStringContainsString('rownum', strtolower($sql));
    }

    public function test_schema_blueprint_uses_connection_and_grammar(): void
    {
        $connection = $this->connection();
        $connection->useDefaultSchemaGrammar();
        $blueprint = new DmBlueprint('probe');
        $blueprint->create();
        $blueprint->string('title');
        $sql = $blueprint->toSql($connection, $connection->getSchemaGrammar())[0];
        $this->assertStringContainsString('create table', strtolower($sql));
        $this->assertStringContainsString('title', $sql);
        $this->assertSame('APP.app_probe', $connection->getSchemaGrammar()->wrapTable('probe'));
    }

    public function test_schema_builder_create_and_rename_compile_without_a_database(): void
    {
        $connection = $this->connection();
        $queries = $connection->pretend(function () use ($connection) {
            $schema = $connection->getSchemaBuilder();
            $schema->create('probe', function ($table) { $table->string('title'); });
            $schema->table('probe', function ($table) { $table->renameColumn('title', 'label'); });
        });
        $this->assertCount(2, $queries);
        $this->assertStringContainsString('create table', strtolower($queries[0]['query']));
        $this->assertStringContainsString('rename column title to label', $queries[1]['query']);
    }

    private function insertConnection(int $id, string $key = 'log_id', ?array $expectedValues = null): Dm8Connection
    {
        $statement = $this->getMockBuilder(PDOStatement::class)->disableOriginalConstructor()->getMock();
        $inputs = [];
        $statement->method('bindParam')->willReturnCallback(
            function ($position, &$value, $type, $length = 0) use ($id, &$inputs) {
                if ($type & PDO::PARAM_INPUT_OUTPUT) {
                    $this->assertSame(PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, $type);
                    $this->assertSame(PHP_INT_SIZE, $length);
                    $value = $id;
                } else {
                    $this->assertGreaterThanOrEqual(0, $length);
                    $inputs[] = $value;
                }
                return true;
            }
        );
        $statement->expects($this->once())->method('execute')->willReturnCallback(
            function () use (&$inputs, $expectedValues) {
                if ($expectedValues !== null) {
                    $this->assertSame($expectedValues, $inputs);
                }
                return true;
            }
        );
        $pdo = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock();
        $pdo->expects($this->once())->method('prepare')
            ->with($this->stringContains('"'.$key.'" into ?'))->willReturn($statement);
        return $this->connection($pdo);
    }

    public function test_static_query_builder_insert_returns_custom_key_without_backtrace(): void
    {
        $connection = $this->insertConnection(123);
        $this->assertSame(123, self::insertLog($connection));
    }

    private static function insertLog(Dm8Connection $connection): int
    {
        return $connection->table('log')->insertGetId(['title' => 'probe'], 'log_id');
    }

    public function test_omitted_return_column_uses_pdo_identity(): void
    {
        $this->assertPdoIdentityReturn(null, true);
    }

    public function test_null_return_column_uses_pdo_identity(): void
    {
        $this->assertPdoIdentityReturn(null, false);
    }

    public function test_empty_return_column_uses_pdo_identity(): void
    {
        $this->assertPdoIdentityReturn('', false);
    }

    private function assertPdoIdentityReturn(?string $column, bool $omit): void
    {
        $statement = $this->getMockBuilder(PDOStatement::class)->disableOriginalConstructor()->getMock();
        $statement->expects($this->once())->method('bindValue')->with(1, 'probe')->willReturn(true);
        $statement->expects($this->never())->method('bindParam');
        $statement->expects($this->once())->method('execute')->willReturn(true);
        $pdo = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock();
        $connection = $this->connection($pdo);
        $query = $connection->table('log');
        $sql = $connection->getQueryGrammar()->compileInsert($query, ['title' => 'probe']);
        $pdo->expects($this->once())->method('prepare')->with($sql)->willReturn($statement);
        $pdo->expects($this->once())->method('lastInsertId')->with(null)->willReturn('654');
        $readPdo = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock();
        $readPdo->expects($this->never())->method('prepare');
        $readPdo->expects($this->never())->method('lastInsertId');
        $connection->setReadPdo($readPdo);

        $id = $omit ? $query->insertGetId(['title' => 'probe'])
            : $query->insertGetId(['title' => 'probe'], $column);
        $this->assertSame(654, $id);
        $modified = new \ReflectionProperty(\Illuminate\Database\Connection::class, 'recordsModified');
        $modified->setAccessible(true);
        $this->assertTrue($modified->getValue($connection));
    }

    public function test_plain_eloquent_model_returns_its_custom_primary_key(): void
    {
        $connection = $this->insertConnection(321);
        $model = new class extends Model {
            protected $table = 'log';
            protected $primaryKey = 'log_id';
            protected $guarded = [];
            public $timestamps = false;
        };
        $resolver = new \Illuminate\Database\ConnectionResolver(['test' => $connection]);
        $resolver->setDefaultConnection('test');
        Model::setConnectionResolver($resolver);
        try {
            $this->assertTrue($model->fill(['title' => 'probe'])->save());
            $this->assertSame(321, $model->getKey());
        } finally {
            Model::unsetConnectionResolver();
        }
    }

    public function test_dm_model_allocates_custom_sequence_before_insert(): void
    {
        $sequence = $this->createMock(Sequence::class);
        $sequence->expects($this->once())->method('nextValue')->with('logs_seq')->willReturn(456);
        $connection = $this->insertConnection(456, 'log_id', ['probe', 456]);
        $connection->setSequence($sequence);
        $model = new class extends DmEloquent {
            protected $table = 'log';
            protected $primaryKey = 'log_id';
            protected $guarded = [];
            public $sequence = 'logs_seq';
            public $timestamps = false;
        };
        $resolver = new \Illuminate\Database\ConnectionResolver(['test' => $connection]);
        $resolver->setDefaultConnection('test');
        Model::setConnectionResolver($resolver);
        try {
            $this->assertTrue($model->fill(['title' => 'probe'])->save());
            $this->assertSame(456, $model->getKey());
        } finally {
            Model::unsetConnectionResolver();
        }
    }

    public function test_lob_insert_binds_valid_output_length(): void
    {
        $connection = $this->insertConnection(789);
        $this->assertSame(789, $connection->table('log')->insertLob(['title' => 'probe'], ['payload' => 'binary'], 'log_id'));
    }
}
