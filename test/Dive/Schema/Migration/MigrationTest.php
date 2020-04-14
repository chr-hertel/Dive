<?php
/*
 * This file is part of the Dive ORM framework.
 * (c) Steffen Zeidler <sigma_z@sigma-scripts.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Dive\Test\Schema\Migration;

use Dive\Connection\Connection;
use Dive\Connection\Driver\DriverInterface;
use Dive\Exception;
use Dive\Schema\Migration\Migration;
use Dive\Platform\PlatformInterface;
use Dive\Schema\Migration\MigrationException;
use Dive\Schema\Migration\MigrationInterface;
use Dive\Schema\SchemaException;
use Dive\TestSuite\TestCase;
use ReflectionClass;
use ReflectionException;

/**
 * @author Steffen Zeidler <sigma_z@sigma-scripts.de>
 * Date: 24.11.12
 */
class MigrationTest extends TestCase
{

    /**
     * @var Migration
     */
    private $migration;
    /**
     * @var Connection[]
     */
    private $revertTableForDbSchemas = array();


    protected function setUp()
    {
        parent::setUp();

        /** @var DriverInterface $driver */
        $driver = $this->getMockForAbstractClass(DriverInterface::class);
        $conn = new Connection($driver, 'sqlite:');
        $this->migration = $this->getMockForAbstractClass(Migration::class, [$conn, 'user']);
    }


    protected function tearDown()
    {
        parent::tearDown();

        foreach ($this->revertTableForDbSchemas as $table => $conn) {
            $driver = $conn->getDriver();
            $dropMigration = $driver->createSchemaMigration($conn, $table, Migration::DROP_TABLE);
            $dropMigration->execute();
            $createMigration = $driver->createSchemaMigration($conn, $table, Migration::CREATE_TABLE);
            $createMigration->importFromSchema(self::getSchema());
            $createMigration->execute();
        }
    }


    /**
     * @dataProvider provideFluentInterface
     * @param string $methodName
     * @param array  $arguments
     * @throws ReflectionException
     */
    public function testFluentInterface($methodName, array $arguments)
    {
        $reflectionClass = new ReflectionClass($this->migration);
        $reflectionProp = $reflectionClass->getProperty('indexes');
        $reflectionProp->setAccessible(true);
        $indexes = array(
            'UNIQUE' => array('unique' => true, 'fields' => array('username'))
        );
        $reflectionProp->setValue($this->migration, $indexes);

        $method = $reflectionClass->getMethod($methodName);
        $migration = $method->invokeArgs($this->migration, $arguments);

        $this->assertEquals($this->migration, $migration);
    }


    /**
     * @return array[]
     */
    public function provideFluentInterface()
    {
        $testCases = array(
            // column methods
            array(
                'addColumn',
                array('name', array())
            ),
            array(
                'dropColumn',
                array('name')
            ),
            array(
                'changeColumn',
                array('username', array(), 'newName')
            ),

            // index methods
            array(
                'addIndex',
                array('name', array())
            ),
            array(
                'dropIndex',
                array('name')
            ),
            array(
                'renameIndex',
                array('UNIQUE', 'UQ_username')
            ),

            // foreign key methods
            array(
                'addForeignKey',
                array('owningField', 'refTable', 'refTable')
            ),
            array(
                'dropForeignKey',
                array('owningField')
            )
        );
        return $testCases;
    }


    /**
     * @dataProvider provideMethodsNotSupportedForDropTableMode
     * @param string $mode
     * @param string $methodName
     * @param array  $arguments
     * @throws ReflectionException
     */
    public function testMethodsNotSupportedForDropTableMode($mode, $methodName, array $arguments)
    {
        $this->expectException(MigrationException::class);
        $reflectionClass = new ReflectionClass($this->migration);

        $modeProperty = $reflectionClass->getProperty('mode');
        $modeProperty->setAccessible(true);
        $modeProperty->setValue($this->migration, $mode);

        $method = $reflectionClass->getMethod($methodName);
        $method->invokeArgs($this->migration, $arguments);
    }


    /**
     * @return array[]
     */
    public function provideMethodsNotSupportedForDropTableMode()
    {
        $testCases = array(
            // addColumn is not supported in DROP TABLE mode
            array(
                Migration::DROP_TABLE,
                'addColumn',
                array('name', array())
            ),

            // dropColumn is not supported in DROP TABLE and CREATE TABLE mode
            array(
                Migration::DROP_TABLE,
                'dropColumn',
                array('name')
            ),
            array(
                Migration::CREATE_TABLE,
                'dropColumn',
                array('name')
            ),

            // changeColumn is not supported in DROP TABLE and CREATE TABLE mode
            array(
                Migration::DROP_TABLE,
                'changeColumn',
                array('name')
            ),
            array(
                Migration::CREATE_TABLE,
                'changeColumn',
                array('name')
            ),

            // addIndex is not supported in DROP TABLE mode
            array(
                Migration::DROP_TABLE,
                'addIndex',
                array('name', array())
            ),

            // dropIndex is not supported in DROP TABLE and CREATE TABLE mode
            array(
                Migration::DROP_TABLE,
                'dropIndex',
                array('name')
            ),
            array(
                Migration::CREATE_TABLE,
                'dropIndex',
                array('name')
            ),

            // renameIndex is not supported in DROP TABLE and CREATE TABLE mode
            array(
                Migration::DROP_TABLE,
                'renameIndex',
                array('name', 'new_name')
            ),
            array(
                Migration::CREATE_TABLE,
                'renameIndex',
                array('UNIQUE', 'UQ_username')
            ),

            // addForeignKey is not supported in DROP TABLE mode
            array(
                Migration::DROP_TABLE,
                'addForeignKey',
                array('owningField', 'refTable', 'refTable')
            ),
        );

        return $testCases;
    }


    /**
     * @dataProvider \Dive\TestSuite\TestCase::provideDatabaseAwareTestCases
     * @param array $database
     * @throws SchemaException
     */
    public function testImportFromDb(array $database)
    {
        $conn = $this->createDatabaseConnectionOrMarkTestSkipped($database);

        $driver = $conn->getDriver();
        $importer = $driver->getSchemaImporter($conn);
        /** @var Migration $migration */
        $migration = $driver->createSchemaMigration($conn, 'user');
        $migration->importFromDb($importer);

        $schema = self::getSchema();
        $expectedFields = array_keys($schema->getTableFields('user'));
        $actualFields = array_keys($migration->getColumns());
        $this->assertEquals($expectedFields, $actualFields);
    }


    /**
     * @dataProvider provideCreateTableMigration
     * @param array  $database
     * @param string $tableName
     * @param array  $expectedArray
     * @throws SchemaException
     */
    public function testCreateTableMigration(array $database, $tableName, array $expectedArray)
    {
        $expected = $this->getExpectedOrMarkTestIncomplete($expectedArray, $database);
        $conn = $this->createDatabaseConnectionOrMarkTestSkipped($database);

        $driver = $conn->getDriver();
        $migration = $driver->createSchemaMigration($conn, $tableName, MigrationInterface::CREATE_TABLE);
        $schema = self::getSchema();

        foreach ($schema->getTableFields($tableName) as $name => $definition) {
            $migration->addColumn($name, $definition);
        }

        foreach ($schema->getTableIndexes($tableName) as $name => $definition) {
            $migration->addIndex($name, $definition['fields'], $definition['type']);
        }

        $relations = $schema->getTableRelations($tableName);
        foreach ($relations['owning'] as $relation) {
            $migration->addForeignKey(
                $relation['owningField'],
                $relation['refTable'],
                $relation['refField'],
                $relation['onDelete'],
                $relation['onUpdate']
            );
        }

        $actual = $migration->getSqlStatements();
        $this->assertEquals($expected, $actual);
    }


    /**
     * @return array[]
     * @throws Exception
     */
    public function provideCreateTableMigration()
    {
        $testCases = array(
            // test case #0
            array(
                'tableName' => 'user',
                'expectedArray' => array(
                    'sqlite' => array(
                        "CREATE TABLE IF NOT EXISTS \"user\" (\n"
                            . "\"id\" integer PRIMARY KEY AUTOINCREMENT NOT NULL,\n"
                            . "\"username\" varchar(64) NOT NULL,\n"
                            . "\"password\" varchar(32) NOT NULL\n"
                            . ")",
                        'CREATE UNIQUE INDEX "user_UNIQUE" ON "user" ("username")'
                    ),
                    'mysql' => array(
                        "CREATE TABLE IF NOT EXISTS `user` (\n"
                            . "`id` int(10) UNSIGNED AUTO_INCREMENT NOT NULL,\n"
                            . "`username` varchar(64) NOT NULL,\n"
                            . "`password` varchar(32) NOT NULL,\n"
                            . "PRIMARY KEY(`id`),\n"
                            . "UNIQUE INDEX `UNIQUE` (`username`)\n"
                            . ")"
                    )
                )
            ),

            // test case #1
            array(
                'tableName' => 'author',
                'expectedArray' => array(
                    'sqlite' => array(
                        "CREATE TABLE IF NOT EXISTS \"author\" (\n"
                            . "\"id\" integer PRIMARY KEY AUTOINCREMENT NOT NULL,\n"
                            . "\"firstname\" varchar(64),\n"
                            . "\"lastname\" varchar(64) NOT NULL,\n"
                            . "\"email\" varchar(255) NOT NULL,\n"
                            . "\"user_id\" unsigned integer(10) NOT NULL,\n"
                            . "\"editor_id\" unsigned integer(10),\n"
                            . "CONSTRAINT \"author_fk_user_id\" FOREIGN KEY (\"user_id\") REFERENCES \"user\" (\"id\") ON DELETE CASCADE ON UPDATE CASCADE,\n"
                            . "CONSTRAINT \"author_fk_editor_id\" FOREIGN KEY (\"editor_id\") REFERENCES \"author\" (\"id\") ON DELETE SET NULL ON UPDATE CASCADE\n"
                            . ")",
                        'CREATE UNIQUE INDEX "author_UNIQUE" ON "author" ("firstname", "lastname")',
                        'CREATE UNIQUE INDEX "author_UQ_user_id" ON "author" ("user_id")'
                    ),
                    'mysql' => array(
                        "CREATE TABLE IF NOT EXISTS `author` (\n"
                            . "`id` int(10) UNSIGNED AUTO_INCREMENT NOT NULL,\n"
                            . "`firstname` varchar(64),\n"
                            . "`lastname` varchar(64) NOT NULL,\n"
                            . "`email` varchar(255) NOT NULL,\n"
                            . "`user_id` int(10) UNSIGNED NOT NULL,\n"
                            . "`editor_id` int(10) UNSIGNED,\n"
                            . "PRIMARY KEY(`id`),\n"
                            . "UNIQUE INDEX `UNIQUE` (`firstname`, `lastname`),\n"
                            . "UNIQUE INDEX `UQ_user_id` (`user_id`),\n"
                            . "CONSTRAINT `author_fk_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,\n"
                            . "CONSTRAINT `author_fk_editor_id` FOREIGN KEY (`editor_id`) REFERENCES `author` (`id`) ON DELETE SET NULL ON UPDATE CASCADE\n"
                            . ")"
                    )
                )
            )
        );

        return self::getDatabaseAwareTestCases($testCases);
    }


    /**
     * @dataProvider provideAlterTableMigration
     * @param array  $database
     * @param string $tableName
     * @param array  $operations
     * @param array  $expectedArray
     */
    public function testAlterTableMigration($database, $tableName, array $operations, array $expectedArray)
    {
        $expected = $this->getExpectedOrMarkTestIncomplete($expectedArray, $database);
        $conn = $this->createDatabaseConnectionOrMarkTestSkipped($database);

        $driver = $conn->getDriver();
        $migration = $driver->createSchemaMigration($conn, $tableName, Migration::ALTER_TABLE);
        $migration->importFromSchema(self::getSchema());

        foreach ($operations as $operation) {
            $method = $operation['method'];
            call_user_func_array(array($migration, $method), $operation['args']);
        }

        $actual = $migration->getSqlStatements();
        $this->assertEquals($expected, $actual);
    }


    /**
     * @return array[]
     * @throws Exception
     */
    public function provideAlterTableMigration()
    {
        $testCases = array();

        $testCases['add column'] = array(
            'tableName' => 'user',
            'operations' => array(
                array(
                    'method' => Migration::ADD_COLUMN,
                    'args' => array(
                        'password_phrase',
                        array('type' => 'string', 'length' => 100, 'nullable' => false),
                        'username'
                    )
                )
            ),
            'expectedArray' => array(
                'sqlite' => array(
                    'ALTER TABLE "user" ADD COLUMN "password_phrase" varchar(100) NOT NULL'
                ),
                'mysql' => array(
                    'ALTER TABLE `user` ADD COLUMN `password_phrase` varchar(100) NOT NULL AFTER `username`'
                )
            )
        );

        $testCases['change column'] = array(
            'tableName' => 'user',
            'operations' => array(
                array(
                    'method' => Migration::CHANGE_COLUMN,
                    'args' => array(
                        'username',
                        array(),
                        'login'
                    )
                )
            ),
            'expectedArray' => array(
                'sqlite' => array(
                    'ALTER TABLE "user" RENAME TO "user_backup"',
                    'CREATE TABLE IF NOT EXISTS "user" (' . "\n"
                        . '"id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,' . "\n"
                        . '"password" varchar(32) NOT NULL,' . "\n"
                        . '"login" varchar(64) NOT NULL' . "\n)",
                    'CREATE UNIQUE INDEX "user_UNIQUE" ON "user" ("login")',
                    'INSERT INTO "user" ("login", "id", "password") SELECT "username", "id", "password" FROM "user_backup"',
                    'DROP TABLE "user_backup"'
                ),
                'mysql' => array(
                    'DROP INDEX `UNIQUE` ON `user`',
                    'ALTER TABLE `user` CHANGE COLUMN `username` `login` varchar(64) NOT NULL',
                    'CREATE UNIQUE INDEX `UNIQUE` ON `user` (`login`)',
                )
            )
        );

        $testCases['drop column'] = array(
            'tableName' => 'user',
            'operations' => array(
                array(
                    'method' => Migration::DROP_COLUMN,
                    'args' => array('password')
                )
            ),
            'expectedArray' => array(
                'sqlite' => array(
                    'ALTER TABLE "user" RENAME TO "user_backup"',
                    'CREATE TABLE IF NOT EXISTS "user" (' . "\n"
                        . '"id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,' . "\n"
                        . '"username" varchar(64) NOT NULL' . "\n)",
                    'CREATE UNIQUE INDEX "user_UNIQUE" ON "user" ("username")',
                    'INSERT INTO "user" ("id", "username") SELECT "id", "username" FROM "user_backup"',
                    'DROP TABLE "user_backup"'
                ),
                'mysql' => array('ALTER TABLE `user` DROP COLUMN `password`')
            )
        );

        $testCases['add column and index'] = array(
            'tableName' => 'user',
            'operations' => array(
                array(
                    'method' => Migration::ADD_COLUMN,
                    'args' => array(
                        'is_active',
                        array('type' => 'boolean', 'nullable' => false, 'default' => true)
                    )
                ),
                array(
                    'method' => Migration::ADD_INDEX,
                    'args' => array(
                        'IX_is_active',
                        'is_active',
                        PlatformInterface::INDEX
                    )
                )
            ),
            'expectedArray' => array(
                'sqlite' => array(
                    'ALTER TABLE "user" ADD COLUMN "is_active" boolean DEFAULT TRUE NOT NULL',
                    'CREATE INDEX "user_IX_is_active" ON "user" ("is_active")'
                ),
                'mysql' => array(
                    'ALTER TABLE `user` ADD COLUMN `is_active` tinyint DEFAULT TRUE NOT NULL',
                    'CREATE INDEX `IX_is_active` ON `user` (`is_active`)'
                )
            )
        );

        $testCases['add column and index and foreign key'] = array(
            'tableName' => 'user',
            'operations' => array(
                array(
                    'method' => Migration::ADD_COLUMN,
                    'args' => array(
                        'manager_id',
                        array('type' => 'integer', 'length' => 10, 'nullable' => true)
                    )
                ),
                array(
                    'method' => Migration::ADD_INDEX,
                    'args' => array('FK_manager_id', 'manager_id', PlatformInterface::INDEX)
                ),
                array(
                    'method' => Migration::ADD_FOREIGN_KEY,
                    'args' => array('manager_id', 'user', 'id', PlatformInterface::SET_NULL, PlatformInterface::CASCADE)
                )
            ),
            'expectedArray' => array(
                // sqlite does not support add foreign, therefore it uses the fallback to create a new table
                'sqlite' => array(
                    'ALTER TABLE "user" RENAME TO "user_backup"',
                    'CREATE TABLE IF NOT EXISTS "user" (' . "\n"
                        . '"id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,' . "\n"
                        . '"username" varchar(64) NOT NULL,' . "\n"
                        . '"password" varchar(32) NOT NULL,' . "\n"
                        . '"manager_id" integer(10),' . "\n"
                        . 'CONSTRAINT "user_fk_manager_id" FOREIGN KEY ("manager_id") REFERENCES "user" ("id") ON DELETE SET NULL ON UPDATE CASCADE'
                        . "\n)",
                    'CREATE UNIQUE INDEX "user_UNIQUE" ON "user" ("username")',
                    'CREATE INDEX "user_FK_manager_id" ON "user" ("manager_id")',
                    'INSERT INTO "user" ("id", "username", "password") SELECT "id", "username", "password" FROM "user_backup"',
                    'DROP TABLE "user_backup"'
                ),
                'mysql' => array(
                    'ALTER TABLE `user` ADD COLUMN `manager_id` int(10)',
                    'CREATE INDEX `FK_manager_id` ON `user` (`manager_id`)',
                    'ALTER TABLE `user` ADD CONSTRAINT `user_fk_manager_id` FOREIGN KEY (`manager_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE'
                )
            )
        );

        $testCases['add composite unique index'] = array(
            'tableName' => 'user',
            'operations' => array(
                array(
                    'method' => Migration::ADD_INDEX,
                    'args' => array('UNIQUE', array('username', 'password'), PlatformInterface::UNIQUE)
                )
            ),
            'expectedArray' => array(
                'sqlite' => array(
                    'DROP INDEX "user_UNIQUE"',
                    'CREATE UNIQUE INDEX "user_UNIQUE" ON "user" ("username", "password")'
                ),
                'mysql' => array(
                    'DROP INDEX `UNIQUE` ON `user`',
                    'CREATE UNIQUE INDEX `UNIQUE` ON `user` (`username`, `password`)'
                )
            )
        );

        $testCases['add index (that becomes a change index, because it exists already)'] = array(
            'tableName' => 'user',
            'operations' => array(
                array(
                    'method' => Migration::ADD_INDEX,
                    'args' => array('UNIQUE', array('username', 'password'), PlatformInterface::UNIQUE)
                )
            ),
            'expectedArray' => array(
                'sqlite' => array(
                    'DROP INDEX "user_UNIQUE"',
                    'CREATE UNIQUE INDEX "user_UNIQUE" ON "user" ("username", "password")'
                ),
                'mysql' => array(
                    'DROP INDEX `UNIQUE` ON `user`',
                    'CREATE UNIQUE INDEX `UNIQUE` ON `user` (`username`, `password`)'
                )
            )
        );

        $testCases['drop foreign'] = array(
            'tableName' => 'author',
            'operations' => array(
                array(
                    'method' => Migration::DROP_FOREIGN_KEY,
                    'args' => array('user_id')
                )
            ),
            'expectedArray' => array(
                // sqlite does not support add foreign, therefore it uses the fallback to create a new table
                'sqlite' => array(
                    'ALTER TABLE "author" RENAME TO "author_backup"',
                    'CREATE TABLE IF NOT EXISTS "author" (' . "\n"
                        . '"id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,' . "\n"
                        . '"firstname" varchar(64),' . "\n"
                        . '"lastname" varchar(64) NOT NULL,' . "\n"
                        . '"email" varchar(255) NOT NULL,' . "\n"
                        . '"user_id" unsigned integer(10) NOT NULL,' . "\n"
                        . '"editor_id" unsigned integer(10),' . "\n"
                        . 'CONSTRAINT "author_fk_editor_id" FOREIGN KEY ("editor_id") REFERENCES "author" ("id") ON DELETE SET NULL ON UPDATE CASCADE' . "\n"
                        . ")",
                    'CREATE UNIQUE INDEX "author_UNIQUE" ON "author" ("firstname", "lastname")',
                    'CREATE UNIQUE INDEX "author_UQ_user_id" ON "author" ("user_id")',
                    'INSERT INTO "author" ("id", "firstname", "lastname", "email", "user_id", "editor_id") SELECT "id", "firstname", "lastname", "email", "user_id", "editor_id" FROM "author_backup"',
                    'DROP TABLE "author_backup"'
                ),
                'mysql' => array(
                    'ALTER TABLE `author` DROP FOREIGN KEY `author_fk_user_id`'
                )
            )
        );

        $testCases['change foreign key'] = array(
            'tableName' => 'author',
            'operations' => array(
                array(
                    'method' => Migration::CHANGE_FOREIGN_KEY,
                    'args' => array('user_id', PlatformInterface::RESTRICT, PlatformInterface::RESTRICT)
                )
            ),
            'expectedArray' => array(
                // sqlite does not support add foreign, therefore it uses the fallback to create a new table
                'sqlite' => array(
                    'ALTER TABLE "author" RENAME TO "author_backup"',
                    'CREATE TABLE IF NOT EXISTS "author" (' . "\n"
                        . '"id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,' . "\n"
                        . '"firstname" varchar(64),' . "\n"
                        . '"lastname" varchar(64) NOT NULL,' . "\n"
                        . '"email" varchar(255) NOT NULL,' . "\n"
                        . '"user_id" unsigned integer(10) NOT NULL,' . "\n"
                        . '"editor_id" unsigned integer(10),' . "\n"
                        . 'CONSTRAINT "author_fk_user_id" FOREIGN KEY ("user_id") REFERENCES "user" ("id") ON DELETE RESTRICT ON UPDATE RESTRICT,' . "\n"
                        . 'CONSTRAINT "author_fk_editor_id" FOREIGN KEY ("editor_id") REFERENCES "author" ("id") ON DELETE SET NULL ON UPDATE CASCADE' . "\n"
                        . ")",
                    'CREATE UNIQUE INDEX "author_UNIQUE" ON "author" ("firstname", "lastname")',
                    'CREATE UNIQUE INDEX "author_UQ_user_id" ON "author" ("user_id")',
                    'INSERT INTO "author" ("id", "firstname", "lastname", "email", "user_id", "editor_id") SELECT "id", "firstname", "lastname", "email", "user_id", "editor_id" FROM "author_backup"',
                    'DROP TABLE "author_backup"'
                ),
                'mysql' => array(
                    'ALTER TABLE `author` DROP FOREIGN KEY `author_fk_user_id`',
                    'ALTER TABLE `author` ADD CONSTRAINT `author_fk_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT'
                )
            )
        );

        $testCases['drop column having a foreign key on'] = array(
            'tableName' => 'author',
            'operations' => array(
                array(
                    'method' => Migration::DROP_COLUMN,
                    'args' => array('user_id')
                )
            ),
            'expectedArray' => array(
                // sqlite does not support add foreign, therefore it uses the fallback to create a new table
                'sqlite' => array(
                    'ALTER TABLE "author" RENAME TO "author_backup"',
                    'CREATE TABLE IF NOT EXISTS "author" (' . "\n"
                        . '"id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,' . "\n"
                        . '"firstname" varchar(64),' . "\n"
                        . '"lastname" varchar(64) NOT NULL,' . "\n"
                        . '"email" varchar(255) NOT NULL,' . "\n"
                        . '"editor_id" unsigned integer(10),' . "\n"
                        . 'CONSTRAINT "author_fk_editor_id" FOREIGN KEY ("editor_id") REFERENCES "author" ("id") ON DELETE SET NULL ON UPDATE CASCADE' . "\n"
                        . ")",
                    'CREATE UNIQUE INDEX "author_UNIQUE" ON "author" ("firstname", "lastname")',
                    'INSERT INTO "author" ("id", "firstname", "lastname", "email", "editor_id") SELECT "id", "firstname", "lastname", "email", "editor_id" FROM "author_backup"',
                    'DROP TABLE "author_backup"'
                ),
                'mysql' => array(
                    'ALTER TABLE `author` DROP FOREIGN KEY `author_fk_user_id`',
                    'DROP INDEX `UQ_user_id` ON `author`',
                    'ALTER TABLE `author` DROP COLUMN `user_id`'
                )
            )
        );

        $testCases['rename table'] = array(
            'tableName' => 'author',
            'operations' => array(
                array(
                    'method' => Migration::RENAME_TABLE,
                    'args' => array('person')
                )
            ),
            'expectedArray' => array(
                'sqlite' => array('ALTER TABLE "author" RENAME TO "person"'),
                'mysql' => array('ALTER TABLE `author` RENAME TO `person`')
            )
        );

        return self::getDatabaseAwareTestCases($testCases);
    }

}
