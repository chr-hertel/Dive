<?php
/*
 * This file is part of the Dive ORM framework.
 * (c) Steffen Zeidler <sigma_z@sigma-scripts.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Dive;

use Dive\Collection\Collection;
use Dive\Connection\Connection;
use Dive\Hydrator\HydratorException;
use Dive\Query\Query;
use Dive\Table\Repository;
use Dive\Table\TableException;

/**
 * @author Steffen Zeidler <sigma_z@sigma-scripts.de>
 * Date: 24.11.12
 */
class Table
{

    /** @var RecordManager */
    protected $rm;

    /** @var string */
    protected $tableName;

    /** @var string */
    protected $recordClass;

    /** @var array */
    protected $identifierFields = array();

    /**
     * @var array
     * associative (keys: field names, values: array with field structure)
     */
    protected $fields = array();

    /** @var \Dive\Relation\Relation[] */
    protected $relations = array();

    /**
     * @var \Dive\Relation\Relation[]
     * indexed by owning field
     */
    protected $owningRelations = null;

    /**
     * @var \Dive\Relation\Relation[]
     * indexed by relation name
     */
    protected $referencedRelations = null;

    /** @var array */
    protected $indexes = array();

    /** @var Repository */
    private $repository = null;


    /**
     * constructor
     *
     * @use   RecordManager::getTable() instead of constructing this class directly
     * @see   RecordManager::getTable()
     * @param RecordManager    $recordManager
     * @param string           $tableName
     * @param string           $recordClass
     * @param array            $fields
     * @param array            $relations
     * @param array            $indexes
     */
    public function __construct(
        RecordManager $recordManager,
        $tableName,
        $recordClass,
        array $fields,
        array $relations = array(),
        array $indexes = array()
    )
    {
        $this->rm = $recordManager;
        $this->tableName = $tableName;
        $this->recordClass = $recordClass;
        $this->relations = $relations;
        $this->indexes = $indexes;

        $this->setFields($fields);

        $this->repository = new Repository($this);
    }


    /**
     * @param array $fields
     * @throws TableException
     */
    protected function setFields(array $fields)
    {
        $identifier = array();
        foreach ($fields as $fieldName => $definition) {
            if (isset($definition['primary']) && $definition['primary'] === true) {
                $identifier[] = $fieldName;
            }
        }

        if (empty($identifier)) {
            throw new TableException("Table '$this->tableName' has no identifier fields.");
        }
        $this->fields = $fields;
        $this->identifierFields = $identifier;
    }


    /**
     * Gets record manager
     *
     * @return RecordManager
     */
    public function getRecordManager()
    {
        return $this->rm;
    }


    /**
     * Gets table name
     *
     * @return string
     */
    public function getTableName()
    {
        return $this->tableName;
    }


    /**
     * TODO find a better way instead of use instanceof
     * @return bool
     */
    public function isView()
    {
        return ($this instanceof View);
    }


    /**
     * toString
     */
    public function __toString()
    {
        return get_class($this) . ' ' . $this->getTableName();
    }


    /**
     * Creates record
     *
     * @param  array $data
     * @param  bool  $exists
     * @return Record
     */
    public function createRecord(array $data = array(), $exists = false)
    {
        /** @var Record $record */
        $record = new $this->recordClass($this, $data, $exists);
        $this->repository->add($record);
        return $record;
    }


    /**
     * Gets record class
     *
     * @return string
     */
    public function getRecordClass()
    {
        return $this->recordClass;
    }


    /**
     * Gets connection belonging to the table
     *
     * @return Connection
     */
    public function getConnection()
    {
        return $this->rm->getConnection();
    }


    /**
     * @return Event\EventDispatcherInterface
     */
    public function getEventDispatcher()
    {
        return $this->rm->getEventDispatcher();
    }


    /**
     * Gets fields (keys: field names, values: field definition as array)
     *
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }


    /**
     * Gets field names
     *
     * @return array
     */
    public function getFieldNames()
    {
        return array_keys($this->fields);
    }


    /**
     * Gets default value for field
     *
     * @param  string $name field name
     * @return mixed
     */
    public function getFieldDefaultValue($name)
    {
        if (isset($this->fields[$name]['default'])) {
            return $this->fields[$name]['default'];
        }
        return null;
    }


    /**
     * Gets index
     *
     * @param  string $indexName
     * @return array
     *   keys: type<string>, fields<array>
     */
    public function getIndex($indexName)
    {
        return isset($this->indexes[$indexName]) ? $this->indexes[$indexName] : null;
    }


    /**
     * Gets indexes (keys: index names, values: index definition as array)
     *
     * @return array
     */
    public function getIndexes()
    {
        return $this->indexes;
    }


    /**
     * @return array
     */
    public function getUniqueIndexes()
    {
        $uniqueIndexes = array();
        foreach ($this->indexes as $name => $definition) {
            if ($definition['type'] === 'unique') {
                $uniqueIndexes[$name] = $definition;
            }
        }
        return $uniqueIndexes;
    }


    /**
     * @param  string $uniqueName
     * @throws Table\TableException
     * @return bool
     */
    public function isUniqueIndexNullConstrained($uniqueName)
    {
        $indexDefinition = $this->getIndex($uniqueName);
        if ($indexDefinition === null) {
            throw new TableException("Missing unique constraint '$uniqueName' [table '$this->tableName']!");
        }
        if ($indexDefinition['type'] !== 'unique') {
            throw new TableException("Index '$uniqueName' is not an unique constraint [table '$this->tableName']!");
        }
        return isset($indexDefinition['nullConstrained']) && $indexDefinition['nullConstrained'] === true;
    }


    /**
     * Gets table repository
     *
     * @return Repository
     */
    public function getRepository()
    {
        return $this->repository;
    }


    /**
     * Returns true, if repository contains record
     *
     * @param  string $id
     * @return bool
     */
    public function isInRepository($id)
    {
        return $this->repository->hasByInternalId($id);
    }


    /**
     * Gets record from repository
     *
     * @param  string $id
     * @return bool|Record
     */
    public function getFromRepository($id)
    {
        return $this->repository->getByInternalId($id);
    }


    private function clearRelationReferences()
    {
        foreach ($this->relations as $relation) {
            $relation->clearReferences();
        }
    }


    /**
     * Clears repository
     */
    public function clearRepository()
    {
        $this->clearRelationReferences();
        $this->repository->clear();
    }


    /**
     * @return bool
     */
    public function hasCompositePrimaryKey()
    {
        return isset($this->identifierFields[1]);
    }


    /**
     * Gets identifier fields as array
     *
     * @return array
     */
    public function getIdentifierFields()
    {
        return $this->identifierFields;
    }


    /**
     * Returns true, if given field name is (part of) the primary key
     *
     * @param  string $name
     * @return bool
     */
    public function isFieldIdentifier($name)
    {
        return in_array($name, $this->identifierFields, true);
    }


    /**
     * @TODO rename hasInsertTrigger(), but then we should support portable insert trigger, like sequences and so on
     * @return bool
     */
    public function hasAutoIncrementTrigger()
    {
        foreach ($this->identifierFields as $idFieldName) {
            $idField = $this->fields[$idFieldName];
            if (isset($idField['autoIncrement']) && $idField['autoIncrement'] === true) {
                return true;
            }
        }
        return false;
    }

    /**
     * Gets field definition
     *
     * @param  string $fieldName
     * @return array
     * @throws TableException
     */
    public function getField($fieldName)
    {
        $this->throwExceptionIfFieldNotExists($fieldName);
        return $this->fields[$fieldName];
    }


    /**
     * Returns true, if table defines a field for the given name
     *
     * @param string $fieldName
     * @return bool
     */
    public function hasField($fieldName)
    {
        return isset($this->fields[$fieldName]);
    }


    /**
     * Returns true, if field is not nullable
     *
     * @param  string $fieldName
     * @return bool
     * @throws TableException
     */
    public function isFieldRequired($fieldName)
    {
        return !$this->isFieldNullable($fieldName);
    }


    /**
     * Returns true, if field is nullable
     *
     * @param  string $fieldName
     * @return bool
     * @throws TableException
     */
    public function isFieldNullable($fieldName)
    {
        $this->throwExceptionIfFieldNotExists($fieldName);
        return isset($this->fields[$fieldName]['nullable']) && $this->fields[$fieldName]['nullable'] === true;
    }


    /**
     * @param  string $fieldName
     * @return string
     */
    public function getFieldType($fieldName)
    {
        $this->throwExceptionIfFieldNotExists($fieldName);
        return $this->fields[$fieldName]['type'];
    }


    /**
     * Gets table relations
     *
     * @return \Dive\Relation\Relation[]
     */
    public function getRelations()
    {
        return $this->relations;
    }


    /**
     * @return \Dive\Relation\Relation[]
     */
    public function getReferencedRelationsIndexedByOwningField()
    {
        if (null === $this->referencedRelations) {
            $this->referencedRelations = array();
            foreach ($this->relations as $name => $relation) {
                if ($relation->isReferencedSide($name)) {
                    $this->referencedRelations[$relation->getOwningField()] = $relation;
                }
            }
        }
        return $this->referencedRelations;
    }


    /**
     * @return \Dive\Relation\Relation[]
     */
    public function getOwningRelations()
    {
        if (null === $this->owningRelations) {
            $this->owningRelations = array();
            foreach ($this->relations as $name => $relation) {
                if ($relation->isOwningSide($name)) {
                    $this->owningRelations[$name] = $relation;
                }
            }
        }
        return $this->owningRelations;
    }


    /**
     * Returns true, if table has a relation for the given relation name
     *
     * @param  string $name
     * @return bool
     */
    public function hasRelation($name)
    {
        return isset($this->relations[$name]);
    }


    /**
     * Gets relation for given relation name
     *
     * @param  string $name
     * @return \Dive\Relation\Relation
     * @throws TableException
     */
    public function getRelation($name)
    {
        $this->throwExceptionIfRelationNotExists($name);
        return $this->relations[$name];
    }


    /**
     * Gets reference for given record
     *
     * @param  Record $record
     * @param  string $relationName
     * @return null|Record|\Dive\Collection\RecordCollection
     */
    public function getReferenceFor(Record $record, $relationName)
    {
        $relation = $this->getRelation($relationName);
        if ($relation->isOneToOne() || $relation->isReferencedSide($relationName)) {
            $refRecord = $relation->getRelatedRecord($record, $relationName);
            if ($refRecord) {
                return $refRecord;
            }
        }
        return $relation->getReferenceFor($record, $relationName);
    }


    /**
     * Sets reference for given record
     *
     * @param Record                                        $record
     * @param string                                        $relationName
     * @param null|Record|\Dive\Collection\RecordCollection $reference
     */
    public function setReferenceFor(Record $record, $relationName, $reference)
    {
        $relation = $this->getRelation($relationName);
        $relation->setReferenceFor($record, $relationName, $reference);
    }


    /**
     * Creates query with this table in from clause
     *
     * @param  string $alias
     * @return Query
     */
    public function createQuery($alias = 'a')
    {
        $from = $this->getTableName() . ' ' . $alias;
        $queryClass = $this->rm->getQueryClass();
        /** @var Query $query */
        $query = new $queryClass($this->rm);
        $query->from($from);
        return $query;
    }


    /**
     * @return int
     */
    public function count()
    {
        return $this->createQuery()->countByPk();
    }


    /**
     * Finds record by primary key
     *
     * @param  string|array $id
     * @param  string       $fetchMode
     * @return bool|\Dive\Record|array
     */
    public function findByPk($id, $fetchMode = RecordManager::FETCH_RECORD)
    {
        $query = $this->createQuery();
        if (!is_array($id)) {
            $id = array($id);
        }
        $identifier = $this->getIdentifierFields();
        $this->throwExceptionIfIdentifierDoesNotMatchFields($id);

        $params = is_array($id) ? array_values($id) : array($id);
        $query->where(implode(' = ? AND ', $identifier) . ' = ?', $params);
        return $query->execute($fetchMode);
    }


    /**
     * @param  string $queryAlias
     * @return string
     */
    public function getIdentifierQueryExpression($queryAlias = '')
    {
        if ($queryAlias) {
            $queryAlias .= '.';
        }

        $connection = $this->getConnection();
        $identifierFields = $this->getIdentifierFields();
        if ($this->hasCompositePrimaryKey()) {
            foreach ($identifierFields as &$idField) {
                $idField = $connection->quoteIdentifier($queryAlias . $idField);
            }
            return implode(" || '" . Record::COMPOSITE_ID_SEPARATOR . "' || ", $identifierFields);
        }
        return $connection->quoteIdentifier($queryAlias . $identifierFields[0]);
    }


//    public function findAll()
//    {
//        $query = $this->createQuery();
//        return $query->execute();
//    }


    /**
     * Finds record by field values, if given fields matches primary key, if not try to find by unique indexes,
     * if no record could be found, then create one
     *
     * @param  array $fieldValues
     * @return \Dive\Record
     */
    public function findOrCreateRecord(array $fieldValues)
    {
        $identifier = $this->getIdentifierFieldValues($fieldValues);
        if ($identifier) {
            $identifierAsString = implode(Record::COMPOSITE_ID_SEPARATOR, $identifier);
            if ($this->isInRepository($identifierAsString)) {
                $record = $this->getFromRepository($identifierAsString);
            }
            else {
                $record = $this->findByPk($identifier);
            }
        }
        else {
            $record = $this->findByUniqueIndexes($fieldValues);
        }

        if (!$record) {
            $record = $this->createRecord($fieldValues);
        }
        return $record;
    }


    /**
     * @param  array  $fieldValues
     * @param  string $fetchMode
     * @throws Table\TableException
     * @return bool|mixed
     */
    public function findByUniqueIndexes(array $fieldValues, $fetchMode = RecordManager::FETCH_RECORD)
    {
        $uniqueIndexes = $this->getUniqueIndexesForFieldValues($fieldValues);
        if (!$uniqueIndexes) {
            return false;
        }

        $conn = $this->getConnection();
        $conditions = array();
        $queryParams = array();

        foreach ($uniqueIndexes as $uniqueName => $uniqueIndexToCheck) {
            $isNullConstrained = $this->isUniqueIndexNullConstrained($uniqueName);
            $conditionParams = array();
            $condition = '';
            $fieldNames = $uniqueIndexToCheck['fields'];
            foreach ($fieldNames as $fieldName) {
                $fieldNameQuoted = $conn->quoteIdentifier($fieldName);
                $fieldValue = $fieldValues[$fieldName];
                if ($fieldValue !== null) {
                    $condition .= $fieldNameQuoted . ' = ? AND ';
                    $conditionParams[] = $fieldValue;
                }
                else if ($isNullConstrained) {
                    $condition .= $fieldNameQuoted . ' IS NULL AND ';
                }
                // TODO: do we need this else?
                else {
                    continue 2;
                }
            }
            // strip last AND from string
            $condition = substr($condition, 0, -4);
            $conditions[] = $condition;
            $queryParams = array_merge($queryParams, $conditionParams);
        }
        $query = $this->createQuery();
        $query->where(implode(' OR ', $conditions), $queryParams);

        try {
            return $query->execute($fetchMode);
        }
        catch (HydratorException $e) {
            $uniqueNames = implode(array_keys($uniqueIndexes));
            throw new TableException('Found more than one record. Used unique indexes for query: ' . $uniqueNames, 0, $e);
        }
    }


    /**
     * @param array $fieldValues
     * @return array
     */
    private function getUniqueIndexesForFieldValues(array $fieldValues)
    {
        $uniqueIndexes = $this->getUniqueIndexes();
        return $this->removeUnusedIndexes($uniqueIndexes, $fieldValues);
    }



    /**
     * @param  array $fieldValues
     * @return array|null
     */
    public function getIdentifierFieldValues(array $fieldValues)
    {
        $identifier = array();
        foreach ($this->getIdentifierFields() as $fieldName) {
            if (!isset($fieldValues[$fieldName])) {
                return null;
            }
            $identifier[$fieldName] = $fieldValues[$fieldName];
        }
        return $identifier;
    }


    /**
     * @param  array $fieldValues
     * @return string|null
     */
    public function getIdentifierAsString(array $fieldValues)
    {
        $identifier = $this->getIdentifierFieldValues($fieldValues);
        if ($identifier === null) {
            return $identifier;
        }
        return implode(Record::COMPOSITE_ID_SEPARATOR, $identifier);
    }


    /**
     * @param string $uniqueIndexName
     * @param array  $fieldValues
     * @param string $fetchMode
     * @throws TableException
     * @return bool|Record|Collection|array|mixed depending on $fetchMode
     */
    public function findByUniqueIndex($uniqueIndexName, array $fieldValues, $fetchMode = RecordManager::FETCH_RECORD)
    {
        $fieldsOfIndex = $this->getFieldsOfUniqueIndex($uniqueIndexName);
        $fieldValues = $this->filterFieldValuesByFieldList($fieldValues, $fieldsOfIndex);
        return $this->findByFieldValues($fieldValues, $fetchMode);
    }


    /**
     * @param string $indexName
     * @param array  $fieldValues
     * @param string $fetchMode
     * @return bool|Record|Collection|array|mixed depending on $fetchMode
     */
    public function findByIndex($indexName, array $fieldValues, $fetchMode = RecordManager::FETCH_RECORD)
    {
        $fieldsOfIndex = $this->getFieldsOfIndex($indexName);
        $fieldValues = $this->filterFieldValuesByFieldList($fieldValues, $fieldsOfIndex);
        return $this->findByFieldValues($fieldValues, $fetchMode);
    }


    /**
     * @param array $fieldValues
     * @return int
     */
    public function countByFieldValues(array $fieldValues)
    {
        $query = $this->createQueryByFieldValues($fieldValues);
        return $query->countByPk();
    }


    /**
     * @param array $fieldValues
     * @param string $fetchMode
     * @return bool|Record|Collection|array|mixed depending on $fetchMode
     */
    public function findByFieldValues(array $fieldValues, $fetchMode = null)
    {
        $query = $this->createQueryByFieldValues($fieldValues);
        return $query->execute($fetchMode);
    }


//    /**
//     * @param string $field
//     * @param mixed  $value
//     * @param string $fetchMode
//     * @return bool|Record|Collection|array|mixed depending on $fetchMode
//     */
//    public function findByField($field, $value, $fetchMode = null)
//    {
//        return $this->findByFieldValues(array($field => $value), $fetchMode);
//    }
//
//
//    /**
//     * @param string $field
//     * @param mixed  $value
//     * @param string $fetchMode
//     * @throws TableException
//     * @return bool|Record|Collection|array|mixed depending on $fetchMode
//     */
//    public function findOneByField($field, $value, $fetchMode = null)
//    {
//        return $this->findByFieldValues(array($field => $value), $fetchMode);
//    }


    /**
     * checks if field exists and throws an exception if not so
     * @param string $name
     * @throws TableException if field does not exists
     */
    public function throwExceptionIfFieldNotExists($name)
    {
        if (!$this->hasField($name)) {
            throw new TableException("Field '$name' is not defined on table '$this->tableName'!");
        }
    }


    /**
     * checks if relation exists and throws an exception if not so
     * @param string $name
     * @throws TableException if relation does not exists
     */
    public function throwExceptionIfRelationNotExists($name)
    {
        if (!$this->hasRelation($name)) {
            throw new TableException("Relation '$name' is not defined on table '$this->tableName'!");
        }
    }


    /**
     * checks if given name is defined as field or relation on this table and throws an exception if it does not
     *
     * @param $name
     * @return \Dive\Table\TableException
     */
    public function getFieldOrRelationNotExistsException($name)
    {
        return new TableException("'$name' is neither a field nor a relation on table '$this->tableName'!");
    }


    /**
     * @param array $id
     * @throws Table\TableException
     */
    public function throwExceptionIfIdentifierDoesNotMatchFields(array $id)
    {
        if (count($id) != count($this->identifierFields)) {
            throw new TableException(
                'Id does not match identifier fields: ' . implode(', ', $this->identifierFields)
                    . ' (you gave me: ' . implode(', ', $id) . ')!'
            );
        }
    }


    /**
     * Modifies $fieldValues array. Deletes all fields that are not part of index and adds all fields of index not part
     *  of $fieldValues with value null.
     *
     * @param array $fieldValues
     * @param array $fieldsOfIndex
     * @return array
     */
    private function filterFieldValuesByFieldList(array $fieldValues, array $fieldsOfIndex)
    {
        $findByValues = array();
        foreach ($fieldsOfIndex as $field) {
            $findByValues[$field] = isset($fieldValues[$field]) ? $fieldValues[$field] : null;
        }
        return $findByValues;
    }


    /**
     * @param array $fieldValues
     * @return Query
     */
    private function createQueryByFieldValues(array $fieldValues)
    {
        $query = $this->createQuery();
        foreach ($fieldValues as $field => $value) {
            if ($this->hasField($field)) {
                if ($value === null) {
                    $query->andWhere("$field IS NULL");
                }
                else {
                    $query->andWhere("$field = ?", $value);
                }
            }
        }
        return $query;
    }


    /**
     * @param string $uniqueIndexName
     * @throws TableException
     * @return array
     */
    private function getFieldsOfUniqueIndex($uniqueIndexName)
    {
        $uniqueIndex = $this->getIndex($uniqueIndexName);
        if (!isset($uniqueIndex['type']) || $uniqueIndex['type'] !== 'unique') {
            throw new TableException("Index '$uniqueIndexName' is used as unique index, but it is not unique!");
        }
        return $uniqueIndex['fields'];
    }


    /**
     * @param string $indexName
     * @return array
     */
    private function getFieldsOfIndex($indexName)
    {
        $uniqueIndex = $this->getIndex($indexName);
        return $uniqueIndex['fields'];
    }


    /**
     * @param array $indexes
     * @param array $fieldValues
     * @return array
     */
    private function removeUnusedIndexes(array $indexes, array $fieldValues)
    {
        foreach ($indexes as $indexName => $indexDefinition) {
            $indexFields = $indexDefinition['fields'];
            foreach ($indexFields as $fieldName) {
                if (!isset($fieldValues[$fieldName]) && !array_key_exists($fieldName, $fieldValues)) {
                    unset($indexes[$indexName]);
                    continue;
                }
            }
        }
        return $indexes;
    }

}
