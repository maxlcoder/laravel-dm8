<?php

namespace LaravelDm8\Dm8\Schema\Grammars;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use LaravelDm8\Dm8\Dm8ReservedWords;

class DmGrammar extends Grammar
{
    use Dm8ReservedWords;

    /**
     * The keyword identifier wrapper format.
     *
     * @var string
     */
    protected $wrapper = '%s';

    /**
     * The possible column modifiers.
     *
     * @var array
     */
    protected $modifiers = ['Increment', 'Nullable', 'Default'];

    /**
     * The possible column serials.
     *
     * @var array
     */
    protected $serials = ['bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger'];

    /**
     * @var string
     */
    protected $schema_prefix = '';

    /**
     * Whether to use 'char' suffix for string length definitions.
     *
     * @var bool
     */
    protected $length_in_char = false;

    /**
     * Whether to use strict mode for column default values.
     *
     * @var bool
     */
    protected $strict_mode = false;

    /**
     * If this Grammar supports schema changes wrapped in a transaction.
     *
     * @var bool
     */
    protected $transactions = true;

    /**
     * Get the columns for a table creation or modification command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @return array
     */
    protected function getColumns(Blueprint $blueprint)
    {
        if (!$this->strict_mode) {
             // Process columns to set default values for NOT NULL non-auto-increment columns
            $columns = $blueprint->getColumns();

            // Collect primary key columns defined at the blueprint level
            $primaryColumns = [];
            $primaryCommand = $this->getCommandByName($blueprint, 'primary');
            if ($primaryCommand && isset($primaryCommand->columns)) {
                $primaryColumns = array_map('strtolower', (array) $primaryCommand->columns);
            }
            
            foreach ($columns as $column) {
                // Ensure column is a Fluent object
                if (! ($column instanceof Fluent)) {
                    continue;
                }
                
                // Set default value for NOT NULL columns that are not auto-increment and have no default
                // Check if column is NOT NULL, not auto-increment, and has no explicit default
                if (! $column->nullable &&
                    ! ($column->autoIncrement ?? false) &&
                    ! ($column->primary ?? false) &&
                    ! in_array(strtolower($column->name), $primaryColumns, true) &&
                    is_null($column->default)) {
                    
                    // Get the actual database type (after transformation via typeXxx methods)
                    $defaultValue = $this->getDefaultValueForType($this->getType($column));
                    if ($defaultValue !== null) {
                        $column->default = $defaultValue;
                    }
                }
            }
        }
        
        // Call parent method to compile columns
        return parent::getColumns($blueprint);
    }

    /**
     * Get default value based on column type.
     *
     * @param  string  $type
     * @return mixed
     */
    protected function getDefaultValueForType($type)
    {
        // Normalize type name (remove any parameters like decimal(10,2) -> decimal)
        $normalizedType = strtolower(trim(explode('(', $type)[0]));
        
        // String types - default to empty string
        $stringTypes = [
            'string', 'text', 'longvarchar',
            'char', 'varchar', 'varchar2', 'nvarchar2', 'nvarchar', 
        ];
        if (in_array($normalizedType, $stringTypes)) {
            return '';
        }

        // JSON types - default to empty JSON object
        $jsonTypes = [
            'json', 'jsonb'
        ];
        if (in_array($normalizedType, $jsonTypes)) {
            return '{}';
        }
        
        // Integer types - default to 0
        $integerTypes = [
            // int
            'integer', 'int', 'bigint','smallint', 'tinyint',
            // float
            'float', 'double', 'decimal', 'numeric', 'dec', 'number', 'real', 'double precision',
            // boolean
            'boolean', 
            // byte
            'bit', 'byte'
        ];
        if (in_array($normalizedType, $integerTypes)) {
            return 0;
        }
        
        // Date/time types - return null to use database default
        if (in_array($normalizedType, ['datetime', 'timestamp', 'timestamp time zone', 'timestamp with timezone', 'timestamp with local time zone'])) {
            return '1970-01-01 00:00:00';
        } else if ($normalizedType == 'date') {
            return '1970-01-01';
        } else if ($normalizedType == 'time') {
            return '00:00:00';
        } else if ($normalizedType == 'year') {
            return '1970';
        } else if (strpos($normalizedType, 'time') !== false || strpos($normalizedType, 'date') !== false) {
            return '1970-01-01 00:00:00';
        }
        
        // Binary types - return null
        $binaryTypes = [
            'binary', 'varbinary',
            'blob', 'clob',
            'raw'
        ];
        if (in_array($normalizedType, $binaryTypes)) {
            return null;
        }
        
        return '';
    }

    /**
     * Compile a create table command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileCreate(Blueprint $blueprint, Fluent $command)
    {
        $columns = implode(', ', $this->getColumns($blueprint));

        $sql = 'create table '.$this->wrapTable($blueprint)." ( $columns";

        /*
         * To be able to name the primary/foreign keys when the table is
         * initially created we will need to check for a primary/foreign
         * key commands and add the columns to the table's declaration
         * here so they can be created on the tables.
         */
        $sql .= (string) $this->addForeignKeys($blueprint);

        $sql .= (string) $this->addPrimaryKeys($blueprint);

        $sql .= ' )';

        return $sql;
    }

    /**
     * Wrap a table in keyword identifiers.
     *
     * @param  mixed  $table
     * @return string
     */
    public function wrapTable($table)
    {
        return $this->getSchemaPrefix().parent::wrapTable($table);
    }

    /**
     * Get the schema prefix.
     *
     * @return string
     */
    public function getSchemaPrefix()
    {
        return ! empty($this->schema_prefix) ? $this->schema_prefix.'.' : '';
    }

    /**
     * Set the schema prefix.
     *
     * @param  string  $prefix
     */
    public function setSchemaPrefix($prefix)
    {
        $this->schema_prefix = $prefix;
    }

    /**
     * Get the length in char setting.
     *
     * @return bool
     */
    public function getLengthInChar()
    {
        return $this->length_in_char;
    }

    /**
     * Set the length in char setting.
     *
     * @param  bool  $lengthInChar
     */
    public function setLengthInChar($lengthInChar)
    {
        $this->length_in_char = (bool) $lengthInChar;
    }

    /**
     * Get the strict mode setting.
     *
     * @return bool
     */
    public function getStrictMode()
    {
        return $this->strict_mode;
    }

    /**
     * Set the strict mode setting.
     *
     * @param  bool  $strictMode
     */
    public function setStrictMode($strictMode)
    {
        $this->strict_mode = (bool) $strictMode;
    }

    /**
     * Get the foreign key syntax for a table creation statement.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @return string
     */
    protected function addForeignKeys(Blueprint $blueprint)
    {
        $sql = '';

        $foreigns = $this->getCommandsByName($blueprint, 'foreign');

        // Once we have all the foreign key commands for the table creation statement
        // we'll loop through each of them and add them to the create table SQL we
        // are building
        foreach ($foreigns as $foreign) {
            $on = $this->wrapTable($foreign->on);

            $columns = $this->columnize($foreign->columns);

            $onColumns = $this->columnize((array) $foreign->references);

            $sql .= ", constraint {$foreign->index} foreign key ( {$columns} ) references {$on} ( {$onColumns} )";

            // Once we have the basic foreign key creation statement constructed we can
            // build out the syntax for what should happen on an update or delete of
            // the affected columns, which will get something like "cascade", etc.
            if (! is_null($foreign->onDelete)) {
                $sql .= " on delete {$foreign->onDelete}";
            }
        }

        return $sql;
    }

    /**
     * Get the primary key syntax for a table creation statement.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @return string|null
     */
    protected function addPrimaryKeys(Blueprint $blueprint)
    {
        $primary = $this->getCommandByName($blueprint, 'primary');

        if (! is_null($primary)) {
            $columns = $this->columnize($primary->columns);

            return ", constraint {$primary->index} primary key ( {$columns} )";
        }

        return '';
    }

        /**
     * Get the primary key constraint syntax for ALTER TABLE statement.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @return string|null
     */
    protected function getPrimaryKeyConstraint(Blueprint $blueprint)
    {
        $primary = $this->getCommandByName($blueprint, 'primary');

        if (! is_null($primary)) {
            $columns = $this->columnize($primary->columns);

            return " constraint {$primary->index} primary key ( {$columns} )";
        }

        return '';
    }

    protected function isSinglePrimaryWithAutoIncrement(Blueprint $blueprint)
    {
        // 单主键+auto_increment，可以在一条alter语句中使用
        $primary = $this->getCommandByName($blueprint, 'primary');
        if (!is_null($primary) && count($primary->columns) == 1) {
            $primayColumn = $primary->columns[0];
            foreach ($blueprint->getColumns() as $column) {
                if ($column->name == $primayColumn && $column->autoIncrement) {
                    return $primary;
                }
            }
        }
        return null;
    }

    /**
     * Compile the query to determine if a table exists.
     *
     * @return string
     */
    public function compileTableExists()
    {
        return 'select * from all_tables where upper(owner) = upper(?) and upper(table_name) = upper(?)';
    }

    /**
     * Compile the query to determine the list of columns.
     *
     * @param  string  $database
     * @param  string  $table
     * @return string
     */
    public function compileColumnExists($database, $table)
    {
        return "select column_name from all_tab_cols where upper(owner) = upper('{$database}') and upper(table_name) = upper('{$table}')";
    }

    /**
     * Compile an add column command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileAdd(Blueprint $blueprint, Fluent $command)
    {
        $table = $this->wrapTable($blueprint);
        $columns = $this->getColumns($blueprint);
        $primayWithAutoIncrementColumn = $this->isSinglePrimaryWithAutoIncrement($blueprint);
        
        $statements = [];
        foreach ($columns as $column) {
            $sql = '';
            $column_name = explode(' ', $column)[0] ?? null;
            if (!is_null($primayWithAutoIncrementColumn) && $column_name == $primayWithAutoIncrementColumn->columns[0]) { // 处理单主键+auto_increment的情况
                $sql = "alter table {$table} add {$column} constraint {$primayWithAutoIncrementColumn->index} primary key";
            } else {
                $sql = "alter table {$table} add {$column}";
            }
            $statements[] = $sql;
        }

        return $statements;
    }

    /**
     * Compile a change column command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return array
     */
    public function compileChange(Blueprint $blueprint, Fluent $command, Connection $connection)
    {
        $table = $this->wrapTable($blueprint);
        $columns = [];

        foreach ($blueprint->getChangedColumns() as $column) {
            // Get the column definition (type + modifiers)
            $sql = $this->getType($column);

            // Add modifiers (nullable, default, etc.)
            foreach ($this->modifiers as $modifier) {
                if (method_exists($this, $method = "modify{$modifier}")) {
                    $sql .= $this->{$method}($blueprint, $column);
                }
            }

            // DM8 syntax: ALTER TABLE table_name MODIFY column_name definition
            $columns[] = 'alter table '.$table.' modify '.$this->wrap($column).' '.$sql;
        }

        return $columns;
    }

    /**
     * Compile a primary key command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compilePrimary(Blueprint $blueprint, Fluent $command)
    {
        $create = $this->getCommandByName($blueprint, 'create');

        if (is_null($create)) {
            $columns = $this->columnize($command->columns);

            $table = $this->wrapTable($blueprint);

            return "alter table {$table} add constraint {$command->index} primary key ({$columns})";
        }
    }

    /**
     * Compile a foreign key command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string|void
     */
    public function compileForeign(Blueprint $blueprint, Fluent $command)
    {
        $create = $this->getCommandByName($blueprint, 'create');

        if (is_null($create)) {
            $table = $this->wrapTable($blueprint);

            $on = $this->wrapTable($command->on);

            // We need to prepare several of the elements of the foreign key definition
            // before we can create the SQL, such as wrapping the tables and convert
            // an array of columns to comma-delimited strings for the SQL queries.
            $columns = $this->columnize($command->columns);

            $onColumns = $this->columnize((array) $command->references);

            $sql = "alter table {$table} add constraint {$command->index} ";

            $sql .= "foreign key ( {$columns} ) references {$on} ( {$onColumns} )";

            // Once we have the basic foreign key creation statement constructed we can
            // build out the syntax for what should happen on an update or delete of
            // the affected columns, which will get something like "cascade", etc.
            if (! is_null($command->onDelete)) {
                $sql .= " on delete {$command->onDelete}";
            }

            return $sql;
        }
    }

    /**
     * Compile a unique key command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileUnique(Blueprint $blueprint, Fluent $command)
    {
        return "create unique index {$command->index} on ".$this->wrapTable($blueprint).' ( '.$this->columnize($command->columns).' )';
    }

    /**
     * Compile a plain index key command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileIndex(Blueprint $blueprint, Fluent $command)
    {
        return "create index {$command->index} on ".$this->wrapTable($blueprint).' ( '.$this->columnize($command->columns).' )';
    }

    /**
     * Compile a drop table command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileDrop(Blueprint $blueprint, Fluent $command)
    {
        return 'drop table '.$this->wrapTable($blueprint);
    }

    /**
     * Compile the SQL needed to drop all tables.
     *
     * @return string
     */
    public function compileDropAllTables()
    {
        return 'BEGIN
            FOR c IN (SELECT table_name FROM user_tables) LOOP
            EXECUTE IMMEDIATE (\'DROP TABLE "\' || c.table_name || \'" CASCADE CONSTRAINTS\');
            END LOOP;

            FOR s IN (SELECT sequence_name FROM user_sequences) LOOP
            EXECUTE IMMEDIATE (\'DROP SEQUENCE \' || s.sequence_name);
            END LOOP;

            END;';
    }

    /**
     * Compile a drop table (if exists) command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileDropIfExists(Blueprint $blueprint, Fluent $command)
    {
        $table = $this->wrapTable($blueprint);

        return "declare c int;
            begin
               select count(*) into c from user_tables where table_name = upper('$table');
               if c = 1 then
                  execute immediate 'drop table $table';
               end if;
            end;";
    }

    /**
     * Compile a drop column command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileDropColumn(Blueprint $blueprint, Fluent $command)
    {
        $columns = $command->columns;

        $table = $this->wrapTable($blueprint);

        $statements = [];
        foreach ($columns as $column) {
            $statements[] = 'alter table '.$table.' drop column '.$this->wrap($column);
        }

        return $statements;
    }

    /**
     * Compile a drop primary key command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileDropPrimary(Blueprint $blueprint, Fluent $command)
    {
        return $this->dropConstraint($blueprint, $command, 'primary');
    }

    /**
     * @param  Blueprint  $blueprint
     * @param  Fluent  $command
     * @param  string  $type
     * @return string
     */
    private function dropConstraint(Blueprint $blueprint, Fluent $command, $type)
    {
        $table = $this->wrapTable($blueprint);
        $index = $command->index;

        if ($type === 'index') {
            return "drop index if exists {$index}";
        }

        return "alter table {$table} drop constraint {$index}";
    }

    /**
     * Compile a drop unique key command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileDropUnique(Blueprint $blueprint, Fluent $command)
    {
        return $this->dropConstraint($blueprint, $command, 'index');
    }

    /**
     * Compile a drop index command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileDropIndex(Blueprint $blueprint, Fluent $command)
    {
        return $this->dropConstraint($blueprint, $command, 'index');
    }

    /**
     * Compile a drop foreign key command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileDropForeign(Blueprint $blueprint, Fluent $command)
    {
        return $this->dropConstraint($blueprint, $command, 'foreign');
    }

    /**
     * Compile a rename table command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileRename(Blueprint $blueprint, Fluent $command)
    {
        $from = $this->wrapTable($blueprint);

        return "alter table {$from} rename to ".$this->wrapTable($command->to);
    }

    /**
     * Compile a rename column command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @param  \Illuminate\Database\Connection  $connection
     * @return array
     */
    public function compileRenameColumn(Blueprint $blueprint, Fluent $command, Connection $connection)
    {
        $table = $this->wrapTable($blueprint);

        $rs = [];
        $rs[0] = 'alter table '.$table.' rename column '.$command->from.' to '.$command->to;

        return (array) $rs;
    }


    /**
     * Compile a table comment command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileTableComment(Blueprint $blueprint, Fluent $command)
    {
        return sprintf('COMMENT ON TABLE %s is %s',
            $this->wrapTable($blueprint),
            "'".str_replace("'", "''", $command->comment)."'"
        );
    }

    /**
     * Compile an update enum check constraint command.
     * 
     * DM8 does not support altering check constraints directly,
     * so we need to drop the old constraint and create a new one.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return array
     */
    public function compileUpdateEnum(Blueprint $blueprint, Fluent $command)
    {
        $table = $this->wrapTable($blueprint);
        $column = $this->wrapTable($command->column);
        $constraintName = $this->getConstraintName($table, $column);
        
        $sql = [];
        
        // Drop the old check constraint
        $sql[] = "alter table {$table} drop constraint {$constraintName}";
        
        // Add the new check constraint with updated values
        $values = implode("', '", $command->allowed);
        $sql[] = "alter table {$table} add constraint {$constraintName} check ({$column} in ('{$values}'))";
        
        return $sql;
    }

    /**
     * Get the check enum constraint name for a column.
     *
     * @param  string  $table
     * @param  string  $column
     * @return string
     */
    private function getConstraintName(string $table, string $column)
    {
        $table = str_replace(['"', "'", '`'], '', strtolower($table));
        $column = str_replace(['"', "'", '`'], '', strtolower($column));
        return $table.'_'.$column.'_enum';
    }

    /**
     * Create the column definition for a char type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeChar(Fluent $column)
    {
        return $this->wrapCharType('varchar2', $column->length);
    }

    /**
     * Create the column definition for a string type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeString(Fluent $column)
    {
        return $this->wrapCharType('varchar2', $column->length);
    }

    /**
     * Create column definition for a nvarchar type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeNvarchar2(Fluent $column)
    {
        return $this->wrapCharType('nvarchar2', $column->length);
    }

    /**
     * Create the column definition for a text type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeText(Fluent $column)
    {
        return 'text';
    }

    /**
     * Create the column definition for a medium text type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeMediumText(Fluent $column)
    {
        return 'text';
    }

    /**
     * Create the column definition for a long text type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeLongText(Fluent $column)
    {
        return 'text';
    }

    /**
     * Create the column definition for a integer type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeInteger(Fluent $column)
    {
        return 'int';
    }

    /**
     * Create the column definition for a integer type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeBigInteger(Fluent $column)
    {
        return 'bigint';
    }

    /**
     * Create the column definition for a medium integer type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeMediumInteger(Fluent $column)
    {
        return 'int';
    }

    /**
     * Create the column definition for a small integer type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeSmallInteger(Fluent $column)
    {
        return 'smallint';
    }

    /**
     * Create the column definition for a tiny integer type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeTinyInteger(Fluent $column)
    {
        return 'tinyint';
    }

    /**
     * Create the column definition for a float type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeFloat(Fluent $column)
    {
        // float is not supprot totol or places, so we use numeric instead
        if ($column->total) {
            $return_type = "numeric({$column->total}";
            if ($column->places) {
                $return_type .= ", {$column->places})";
            } else {
                $return_type .= ")";
            }
            return $return_type;
        }
        return "float";
    }

    /**
     * Create the column definition for a double type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeDouble(Fluent $column)
    {
        // double is not supprot total or places, so we use numeric instead
        if ($column->total) {
            $return_type = "numeric({$column->total}";
            if ($column->places) {
                $return_type .= ", {$column->places})";
            } else {
                $return_type .= ")";
            }
            return $return_type;
        }
        return "double";
    }

    /**
     * Create the column definition for a decimal type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeDecimal(Fluent $column)
    {
        return "decimal({$column->total}, {$column->places})";
    }

    /**
     * Create the column definition for a boolean type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeBoolean(Fluent $column)
    {
        return 'tinyint';
    }

    /**
     * Create the column definition for a enum type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeEnum(Fluent $column)
    {
        return $this->wrapCharType('varchar2', ($column->length) ? $column->length : 255);
    }

    /**
     * Create the column definition for a date type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeDate(Fluent $column)
    {
        return 'date';
    }

    /**
     * Create the column definition for a date-time type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeDateTime(Fluent $column)
    {
        return 'date';
    }

    /**
     * Create the column definition for a time type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeTime(Fluent $column)
    {
        return 'date';
    }

    /**
     * Create the column definition for a timestamp type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeTimestamp(Fluent $column)
    {
        return 'timestamp';
    }

    /**
     * Create the column definition for a timestamp type with timezone.
     *
     * @param  Fluent  $column
     * @return string
     */
    protected function typeTimestampTz(Fluent $column)
    {
        return 'timestamp with time zone';
    }

    /**
     * Create the column definition for a binary type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeBinary(Fluent $column)
    {
        return 'blob';
    }

    /**
     * Create the column definition for a uuid type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeUuid(Fluent $column)
    {
        return $this->wrapCharType('varchar2', '36');
    }

    /**
     * Create the column definition for an IP address type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeIpAddress(Fluent $column)
    {
        return $this->wrapCharType('varchar2', '45');
    }

    /**
     * Create the column definition for a MAC address type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeMacAddress(Fluent $column)
    {
        return $this->wrapCharType('varchar2', '17');
    }

    /**
     * Create the column definition for a json type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeJson(Fluent $column)
    {
        return 'json';
    }

    /**
     * Create the column definition for a jsonb type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeJsonb(Fluent $column)
    {
        return 'jsonb';
    }

    /**
     * Get the SQL for a nullable column modifier.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function modifyNullable(Blueprint $blueprint, Fluent $column)
    {
        // check if field is declared as enum
        $enum = '';
        if (count((array) $column->allowed)) {
            $columnName = $this->wrapValue($column->name);
            $constraintName = $this->getConstraintName($this->wrapTable($blueprint), $columnName);
            $enum = " constraint {$constraintName} check ({$columnName} in ('".implode("', '", $column->allowed)."'))";
        }

        $null = $column->nullable ? ' null' : ' not null';
        $null .= $enum;

        if (! is_null($column->default)) {
            return ' default '.$this->getDefaultValue($column->default).$null;
        }

        return $null;
    }

    /**
     * Get the SQL for a default column modifier.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function modifyDefault(Blueprint $blueprint, Fluent $column)
    {
        // implemented @modifyNullable
        return '';
    }

    /**
     * Get the SQL for an auto-increment column modifier.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $column
     * @return string|null
     */
    protected function modifyIncrement(Blueprint $blueprint, Fluent $column)
    {
        if (in_array($column->type, $this->serials) && $column->autoIncrement) {
            $blueprint->primary($column->name);
            return ' auto_increment';
        }
    }

    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param  string  $value
     * @return string
     */
    protected function wrapValue($value)
    {
        if ($this->isReserved($value)) {
            return Str::upper(parent::wrapValue($value));
        }

        return $value !== '*' ? sprintf($this->wrapper, $value) : $value;
    }

    /**
     * Wrap a char type with length and char option.
     *
     * @param  string  $type
     * @param  string  $length
     * @return string
     */
    protected function wrapCharType($type, $length)
    {
        return $this->length_in_char ? $type.'('.$length.' char)' : $type.'('.$length.')';
    }
}
