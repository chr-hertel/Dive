<?php
/*
 * This file is part of the Dive ORM framework.
 * (c) Steffen Zeidler <sigma_z@sigma-scripts.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Dive\Test\Record;

use Dive\Platform\PlatformInterface;
use Dive\Record\Generator\RecordGenerator;
use Dive\Record;
use Dive\RecordManager;
use Dive\TestSuite\Constraint\RecordScheduleConstraint;
use Dive\TestSuite\Model\Article;
use Dive\TestSuite\Model\Author;
use Dive\TestSuite\Model\User;
use Dive\TestSuite\TableRowsProvider;
use Dive\TestSuite\TestCase;
use Dive\UnitOfWork\UnitOfWork;

/**
 * @author  Steffen Zeidler <sigma_z@sigma-scripts.de>
 * @created 15.11.13
 */
class RecordDeleteConstraintTest extends TestCase
{

    const NOT_REFERENCED = 0;
    const REFERENCED = 1;
    const NESTED_REFERENCED = 2;

    /** @var array */
    private static $tableRows = array();

    /** @var RecordGenerator */
    private static $recordGenerator;


    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$tableRows = TableRowsProvider::provideTableRows();
        $rm = self::createDefaultRecordManager();
        self::$recordGenerator = self::saveTableRows($rm, self::$tableRows);
    }


    /**
     * @param string $tableName
     * @throws \Exception
     * @dataProvider provideDeleteOnNonSavedRecords
     */
    public function testDeleteOnNonSavedRecordsOwningSide($tableName)
    {
        $rm = self::createDefaultRecordManager();

        /** @var User $user */
        $user = $rm->getRecord('user', array());
        /** @var Author $author */
        $author = $rm->getRecord('author', array());
        /** @var Article $article */
        $article = $rm->getRecord('article', array());
        $author->Article[] = $article;
        $user->Author = $author;

        switch ($tableName) {
            case 'user':
                $recordToDelete = $user;
                break;

            case 'author':
                $recordToDelete = $author;
                break;

            case 'article':
                $recordToDelete = $article;
                break;

            default:
                throw new \Exception("Test for $tableName is not supported!");
        }

        $rm->delete($recordToDelete);
        $this->assertRecordIsNotScheduledForDelete($author);
        $this->assertRecordIsNotScheduledForDelete($user);
        $this->assertRecordIsNotScheduledForDelete($article);
    }


    /**
     * @return array
     */
    public function provideDeleteOnNonSavedRecords()
    {
        return array(
            array('user'),
            array('author'),
            array('article'),
        );
    }


    /**
     * @dataProvider provideDelete
     * @param array  $deleteGraph
     * @param array  $constraints
     */
    public function testDelete(array $deleteGraph, array $constraints)
    {
        $tableName = key($deleteGraph[0]);
        $recordKey = $deleteGraph[0][$tableName][0];
        $rm = $this->getRecordManagerWithOverWrittenConstraints($tableName, $constraints);
        $record = $this->getGeneratedRecord($rm, $tableName, $recordKey);
        $constraint = $constraints[0];

        $isConstraintRestricted = self::isRestrictedConstraint($constraint);
        $expectException = false;
        if ($isConstraintRestricted && (!empty($deleteGraph[1]) || !empty($deleteGraph[2]))) {
            $expectException = true;
            $this->setExpectedException('\Dive\UnitOfWork\UnitOfWorkException');
        }
        if (!empty($deleteGraph[1]) && ($constraint == PlatformInterface::CASCADE)) {
            $nestedConstraint = $constraints[1];
            $isNestedConstraintRestricted = self::isRestrictedConstraint($nestedConstraint);
            if ($isNestedConstraintRestricted) {
                $expectException = true;
            }
        }

        if ($expectException) {
            $this->setExpectedException('\Dive\UnitOfWork\UnitOfWorkException');
        }
        $rm->delete($record);

        $message = implode(', ', $constraints) . " on table '$tableName' for recordKey '$recordKey'!";
        $this->assertScheduledOperationsForCommit($constraints, $deleteGraph, $rm, $message);
    }


    /**
     * TODO define more tests
     * NOTE records are referenced by TableRowsProvider::provideTableRows()
     *
     * @return array[]
     */
    public function provideDelete()
    {
        $testCases = array();

        //
        $testCases[] = array(
            'deleteGraph' => array(
                array(
                    'user' => array('JohnD')
                ),
                array(
                    'author' => array('John Doe')
                ),
                array(
                    'article' => array('helloWorld', 'DiveORM released')
                ),
                array(
                    'article2tag' => array('DiveORM released#Release Notes', 'DiveORM released#News'),
                    'comment' => array('DiveORM released#1')
                )
            )
//            'referencesByLevel' => array(
//                'user' => array('JohnD')
//            )
        );
//        $testCases[] = array(
//            'tableName' => 'author',
//            'recordKey' => 'John Doe',
//            'referencesByLevel' => array(2, 3)
//        );
//        $testCases[] = array(
//            'tableName' => 'user',
//            'recordKey' => 'JamieTK',
//            'referencesByLevel' => array(3, 1, 4)
//        );

        $combinedTestCases = array();
        $combinedConstraints = $this->getCombinedConstraints();
        foreach ($testCases as $testCase) {
            foreach ($combinedConstraints as $constraintCombination) {
                $combinedTestCases[] = array_merge($testCase, array($constraintCombination));
            }
        }
        return $combinedTestCases;
    }


    /**
     * @return array[]
     */
    public function getCombinedConstraints()
    {
        $constraints = array(
            PlatformInterface::CASCADE,
            PlatformInterface::SET_NULL,
            PlatformInterface::NO_ACTION,
            PlatformInterface::RESTRICT
        );

        $combinedConstraints = array();
        foreach ($constraints as $constraint) {
            foreach ($constraints as $nestedConstraint) {
                $combinedConstraints[] = array($constraint, $nestedConstraint);
            }
        }
        return $combinedConstraints;
    }


    /**
     * @param  string $tableName
     * @param  array  $constraints
     * @return \Dive\RecordManager
     */
    protected function getRecordManagerWithOverWrittenConstraints($tableName, array $constraints)
    {
        $schemaDefinition = self::getSchemaDefinition();
        self::processSchemaConstraints($schemaDefinition, $tableName, 'onDelete', $constraints);
        return self::createDefaultRecordManager($schemaDefinition);
    }


    /**
     * @param array  $schemaDefinition
     * @param string $tableName
     * @param string $constraintType
     * @param array  $constraints
     * @param array  $processedTables
     */
    private static function processSchemaConstraints(
        &$schemaDefinition,
        $tableName,
        $constraintType,
        array $constraints,
        array &$processedTables = array()
    )
    {
        if (in_array($tableName, $processedTables) || empty($constraints)) {
            return;
        }

        $constraint = array_shift($constraints);
        if ($constraint == PlatformInterface::CASCADE && empty($constraints)) {
            $constraints[] = PlatformInterface::CASCADE;
        }
        $processedTables[] = $tableName;
        foreach ($schemaDefinition['relations'] as &$relation) {
            if ($relation['refTable'] == $tableName) {
                $relation[$constraintType] = $constraint;
                if ($relation['owningTable'] != $tableName) {
                    self::processSchemaConstraints(
                        $schemaDefinition, $relation['owningTable'], $constraintType, $constraints, $processedTables
                    );
                }
            }
        }
    }


    /**
     * @param  string $constraint
     * @return bool
     */
    private static function isRestrictedConstraint($constraint)
    {
        return in_array($constraint, array(PlatformInterface::RESTRICT, PlatformInterface::NO_ACTION));
    }


    /**
     * @param Record $record
     * @param string $message
     */
    protected function assertRecordIsScheduledForDelete(Record $record, $message = '')
    {
        self::assertThat($record, self::isScheduledFor(UnitOfWork::OPERATION_DELETE), $message);
    }


    /**
     * @param  string $operation
     * @return RecordScheduleConstraint
     */
    private static function isScheduledFor($operation)
    {
        return new RecordScheduleConstraint($operation);
    }


    /**
     * @param Record $record
     * @param string $message
     */
    protected function assertRecordIsNotScheduledForDelete(Record $record, $message = '')
    {
        self::assertThat($record, self::logicalNot(self::isScheduledFor(UnitOfWork::OPERATION_DELETE)), $message);
    }


    /**
     * @param array         $constraints
     * @param array         $deleteGraph
     * @param RecordManager $rm
     * @param string        $message
     * @throws \PHPUnit_Framework_Exception
     */
    protected function assertScheduledOperationsForCommit(
        array $constraints, array $deleteGraph, RecordManager $rm, $message
    )
    {
        $expectedScheduledForCommit = self::getExpectedScheduledOperationsForCommit($rm, $constraints, $deleteGraph);
        if (empty($expectedScheduledForCommit)) {
            throw \PHPUnit_Util_InvalidArgumentHelper::factory(1, 'not empty array');
        }

        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = self::readAttribute($rm, 'unitOfWork');
        /** @var string[] $scheduledForCommit */
        $scheduledForCommit = self::readAttribute($unitOfWork, 'scheduledForCommit');

        $actualScheduledForCommit = array(
            UnitOfWork::OPERATION_DELETE => array(),
            UnitOfWork::OPERATION_SAVE => array()
        );
        foreach ($scheduledForCommit as $oid => $operation) {
            $actualScheduledForCommit[$operation][] = $oid;
        }

        sort($expectedScheduledForCommit[UnitOfWork::OPERATION_DELETE]);
        sort($actualScheduledForCommit[UnitOfWork::OPERATION_DELETE]);
        sort($expectedScheduledForCommit[UnitOfWork::OPERATION_SAVE]);
        sort($actualScheduledForCommit[UnitOfWork::OPERATION_SAVE]);
        $this->assertEquals($expectedScheduledForCommit, $actualScheduledForCommit, $message);
    }


    /**
     * @param \Dive\RecordManager $rm
     * @param  array              $constraints
     * @param  array              $deleteGraph
     * @return array
     */
    private function getExpectedScheduledOperationsForCommit(RecordManager $rm, array $constraints, array $deleteGraph)
    {
        $constraint = $constraints[0];
        $nestedConstraint = $constraints[1];

        $expected = array(
            UnitOfWork::OPERATION_DELETE => array(),
            UnitOfWork::OPERATION_SAVE => array()
        );

        foreach ($deleteGraph as $level => $tableReferences) {
            foreach ($tableReferences as $tableName => $recordKeys) {
                foreach ($recordKeys as $recordKey) {
                    $record = $this->getGeneratedRecord($rm, $tableName, $recordKey);
                    $oid = $record->getOid();
                    if ($level == 0) {
                        $expected[UnitOfWork::OPERATION_DELETE][] = $oid;
                    }
                    else if ($level == 1) {
                        if ($constraint == PlatformInterface::CASCADE) {
                            $expected[UnitOfWork::OPERATION_DELETE][] = $oid;
                        }
                        else if ($constraint == PlatformInterface::SET_NULL) {
                            $expected[UnitOfWork::OPERATION_SAVE][] = $oid;
                        }
                    }
                    else if ($constraint == PlatformInterface::CASCADE) {
                        if ($nestedConstraint == PlatformInterface::CASCADE) {
                            $expected[UnitOfWork::OPERATION_DELETE][] = $oid;
                        }
                        else if ($level == 2 && $nestedConstraint == PlatformInterface::SET_NULL) {
                            $expected[UnitOfWork::OPERATION_SAVE][] = $oid;
                        }
                    }
                }
            }
        }
        return $expected;
    }


    /**
     * @param  RecordManager $rm
     * @param  string        $tableName
     * @param  string        $recordKey
     * @return Record
     */
    private function getGeneratedRecord(RecordManager $rm, $tableName, $recordKey)
    {
        $pk = self::$recordGenerator->getRecordIdFromMap($tableName, $recordKey);
        $table = $rm->getTable($tableName);
        if ($table->hasCompositePrimaryKey()) {
            $pk = explode(Record::COMPOSITE_ID_SEPARATOR, $pk);
        }
        $record = $table->findByPk($pk);
        $message = "Could not load record for '$recordKey' in table '$tableName'";
        $this->assertInstanceOf('\Dive\Record', $record, $message);
        return $record;
    }

}
