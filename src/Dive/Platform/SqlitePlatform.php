<?php
/*
 * This file is part of the Dive ORM framework.
 * (c) Steffen Zeidler <sigma_z@sigma-scripts.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Dive\Platform;

/**
 * @author Steffen Zeidler <sigma_z@sigma-scripts.de>
 * Date: 20.12.12
 */
class SqlitePlatform extends Platform
{

    /**
     * @var array
     */
    protected $supportedEncodings = array(
        self::ENC_UTF8 => 'utf-8'
    );


    /**
     * get create table as sql
     *
     * @param string $tableName
     * @param array  $columns
     * @param array  $indexes
     * @param array  $foreignKeys
     * @param array  $tableOptions
     * @return string
     * @throws PlatformException
     */
    public function getCreateTableSql(
        $tableName,
        array $columns,
        array $indexes = array(),
        array $foreignKeys = array(),
        array $tableOptions = array()
    ) {
        $sql = 'CREATE TABLE ';
        if ($this->preventErrors) {
            $sql .= 'IF NOT EXISTS ';
        }
        $sql .= $this->quoteIdentifier($tableName) . " (\n";

        $autoIncrement = false;
        $primaryKey = array();

        // columns
        foreach ($columns as $name => $definition) {
            $sql .= $this->quoteIdentifier($name) . ' ';
            $sql .= $this->getColumnDefinitionSql($definition) . ",\n";
            if (isset($definition['autoIncrement']) && $definition['autoIncrement'] === true) {
                $autoIncrement = true;
            }
            if (!$autoIncrement && isset($definition['primary']) && $definition['primary']) {
                $primaryKey[] = $this->quoteIdentifier($name);
            }
        }

        // non auto increment primary key
        if (!$autoIncrement && !empty($primaryKey)) {
            $sql .= 'PRIMARY KEY(' . implode(', ', $primaryKey) . "),\n";
        }

        // foreign keys
        foreach ($foreignKeys as $owningField => $definition) {
            $definition = $this->generateConstraintNameIfNotDefined($definition, $tableName, $owningField);
            $sql .= $this->getForeignKeyDefinitionSql($owningField, $definition) . ",\n";
        }

        // removing last comma
        $sql = substr($sql, 0, -2) . "\n)";

        return $sql;
    }


    /**
     * gets column definition as sql
     *
     * @param   array   $definition
     * @return  string
     */
    public function getColumnDefinitionSql(array $definition)
    {
        $primary = isset($definition['primary']) ? $definition['primary'] : false;
        if (isset($definition['dbType'])) {
            $dbType = $definition['dbType'];
        }
        else {
            $autoIncrement  = isset($definition['autoIncrement']) ? $definition['autoIncrement'] : false;
            $dbType = $this->getDataType($definition);
            $isPrimaryAutoIncrement = $primary && $autoIncrement;
            if ($isPrimaryAutoIncrement) {
                $dbType = 'integer PRIMARY KEY AUTOINCREMENT';
            }
            else {
                $unsigned = isset($definition['unsigned']) ? $definition['unsigned'] : false;
                if ($unsigned) {
                    $dbType = 'unsigned ' . $dbType;
                }
                $length = $this->getColumnLength($definition);
                if ($length) {
                    if (isset($definition['scale'])) {
                        $precision = $length - 1;
                        $scale = $definition['scale'];
                        $length = "$precision,$scale";
                    }
                    $dbType .= '(' . $length . ')';
                }
            }
        }

        $notNull    = !isset($definition['nullable']) || $definition['nullable'] !== true ? ' NOT NULL' : '';
        $charset    = isset($definition['charset'])     ? ' CHARACTER SET ' . $definition['charset']    : '';
        $collation  = isset($definition['collation'])   ? ' COLLATE '  . $definition['collation']       : '';
        $default = isset($definition['default'])
            ? ' DEFAULT ' . $this->quote($definition['default'], $definition['type'])
            : '';

        return $dbType . $charset  . $collation . $default .  $notNull;
    }


    /**
     * gets column length
     *
     * @param   array   $definition
     * @return  string
     */
    protected function getColumnLength(array $definition)
    {
        if ($definition['type'] === 'time') {
            return 8;
        }
        return parent::getColumnLength($definition);
    }


    /**
     * gets drop column as sql
     *
     * @param  string $tableName
     * @param  string $columnName
     * @return string|void
     * @throws PlatformException
     */
    public function getDropColumnSql($tableName, $columnName)
    {
        throw new PlatformException('DROP COLUMN is not supported for SQLITE!');
    }


    /**
     * gets change column as sql
     *
     * @param  string $tableName
     * @param  string $columnName
     * @param  array  $definition
     * @param  string $newColumnName
     * @throws PlatformException
     * @return string
     */
    public function getChangeColumnSql($tableName, $columnName, array $definition, $newColumnName = null)
    {
        throw new PlatformException('CHANGE COLUMN is not supported for SQLITE!');
    }


    /**
     * gets add index as sql
     *
     * @param  string       $tableName
     * @param  string       $indexName
     * @param  array|string $fieldNames
     * @param  string       $indexType
     * @return string
     */
    public function getCreateIndexSql($tableName, $indexName, $fieldNames, $indexType = null)
    {
        if (0 !== strpos($indexName, $tableName . '_')) {
            $indexName = $tableName . '_' . $indexName;
        }
        return parent::getCreateIndexSql($tableName, $indexName, $fieldNames, $indexType);
    }


    /**
     * gets drop index as sql
     *
     * @param  string $tableName
     * @param  string $indexName
     * @return string
     */
    public function getDropIndexSql($tableName, $indexName)
    {
        if (0 !== strpos($indexName, $tableName . '_')) {
            $indexName = $tableName . '_' . $indexName;
        }
        return parent::getDropIndexSql($tableName, $indexName);
    }


    /**
     * gets disable foreign key checks as sql
     *
     * @return string
     */
    public function getDisableForeignKeyChecksSql()
    {
        return 'PRAGMA foreign_keys = OFF';
    }


    /**
     * gets enable foreign key checks as sql
     *
     * @return string
     */
    public function getEnableForeignKeyChecksSql()
    {
        return 'PRAGMA foreign_keys = ON';
    }


    /**
     * Gets set connection encoding sql statement
     *
     * @param  string $encoding
     * @return string
     */
    public function getSetConnectionEncodingSql($encoding)
    {
        $encoding = $this->getEncodingSqlName($encoding);
        return "PRAGMA encoding = \"$encoding\"";
    }


    /**
     * gets add foreign key as sql
     *
     * @param  string $tableName
     * @param  string $owningField
     * @param  array  $definition
     * @throws PlatformException
     * @return string
     */
    public function getAddForeignKeySql($tableName, $owningField, array $definition)
    {
        throw new PlatformException('ADD FOREIGN KEY is not supported for SQLITE!');
    }


    /**
     * gets drop foreign key as sql
     *
     * @param  string $tableName
     * @param  string $constraintName
     * @throws PlatformException
     * @return string
     */
    public function getDropForeignKeySql($tableName, $constraintName)
    {
        throw new PlatformException('DROP FOREIGN KEY is not supported for SQLITE!');
    }


    /**
     * gets version sql
     *
     * @return string
     */
    public function getVersionSql()
    {
        return 'SELECT sqlite_version()';
    }
}
