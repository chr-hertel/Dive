<?php
/*
 * This file is part of the Dive ORM framework.
 * (c) Steffen Zeidler <sigma_z@sigma-scripts.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * @author Steffen Zeidler <sigma_z@sigma-scripts.de>
 * Date: 06.02.13
 */

namespace Dive\UnitOfWork;

use Dive\Record;
use Dive\RecordManager;
use Dive\Table;
use Dive\Exception as DiveException;


class UnitOfWork
{

    /**
     * @var RecordManager
     */
    private $rm = null;


    public function __construct(RecordManager $rm)
    {
        $this->rm = $rm;
    }


    /**
     * Gets record (retrieved from repository if exists, or create new record!)
     *
     * @param  Table $table
     * @param  array $data
     * @param  bool  $exists
     * @throws \UnexpectedValueException
     * @return Record
     */
    public function getRecord(Table $table, array $data, $exists = false)
    {
        $id = $this->getIdentifierFromData($table, $data);
        // TODO implement repository handling!!
        if ($id !== false && $table->isInRepository($id)) {
            $record = $table->getFromRepository($id);
        }
        else {
            $record = $table->createRecord($data, $exists);
        }
        return $record;
    }


    /**
     * TODO implement save
     */
    public function saveGraph(Record $record, ChangeSet $changeSet)
    {
        $changeSet->calculateSave($record);
        $this->executeChangeSet($changeSet);
    }


    /**
     * TODO implement delete
     */
    public function deleteGraph(Record $record, ChangeSet $changeSet)
    {
        $changeSet->calculateDelete($record);
        $this->executeChangeSet($changeSet);
    }


    private function executeChangeSet(ChangeSet $changeSet)
    {
        $conn = $this->rm->getConnection();
        try {
            $conn->beginTransaction();
            foreach ($changeSet->getScheduledForDelete() as $recordDelete) {
                $this->doDelete($recordDelete);
            }
            foreach ($changeSet->getScheduledForInsert() as $recordInsert) {
                $this->doInsert($recordInsert);
            }
            foreach ($changeSet->getScheduledForUpdate() as $recordUpdate) {
                $this->doUpdate($recordUpdate);
            }
            $conn->commit();
        }
        catch (DiveException $e) {
            $conn->rollBack();
            throw $e;
        }
    }


    private function doInsert(Record $record)
    {
        $table = $record->getTable();
        $pkFields = array();
        $data = array();
        foreach ($table->getFields() as $fieldName => $fieldDef) {
            if (isset($fieldDef['primary']) && $fieldDef['primary'] === true) {
                $pkFields[] = $fieldName;
            }
            $data[$fieldName] = $record->get($fieldName);
        }
        $conn = $table->getConnection();
        $conn->insert($table, $data);

        // only one primary key field
        if (!isset($pkFields[1])) {
            $id = $conn->getLastInsertId($record->getTable()->getTableName());
            $record->assignIdentifier($id);
            $table->refreshRecordIdentityInRepository($record);
        }
    }


    private function doDelete(Record $record)
    {
        $table = $record->getTable();
        $identifier = array();
        foreach ($table->getFields() as $fieldName => $fieldDef) {
            if (isset($fieldDef['primary']) && $fieldDef['primary'] === true) {
                $identifier[$fieldName] = $record->get($fieldName);
            }
        }
        $conn = $table->getConnection();
        $conn->delete($table, $identifier);
    }


    private function doUpdate(Record $record)
    {
        $table = $record->getTable();
        $identifier = array();
        $modifiedFields = array();
        foreach ($table->getFields() as $fieldName => $fieldDef) {
            if (isset($fieldDef['primary']) && $fieldDef['primary'] === true) {
                $identifier[$fieldName] = $record->get($fieldName);
            }
            if ($record->isFieldModified($fieldName)) {
                $modifiedFields[$fieldName] = $record->get($fieldName);
            }
        }

        $conn = $table->getConnection();
        $conn->update($table, $modifiedFields, $identifier);
    }


    /**
     * Gets identifier as string, but returns false, if identifier could not be determined
     *
     * @param  Table $table
     * @param  array $data
     * @return bool|string
     */
    private function getIdentifierFromData(Table $table, array $data)
    {
        $identifierFields = $table->getIdentifierAsArray();
        $identifier = array();
        foreach ($identifierFields as $fieldName) {
            if (!isset($data[$fieldName])) {
                return false;
            }
            $identifier[] = $data[$fieldName];
        }
        return implode(Record::COMPOSITE_ID_SEPARATOR, $identifier);
    }
}