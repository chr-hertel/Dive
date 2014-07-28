<?php
/*
 * This file is part of the Dive ORM framework.
 * (c) Steffen Zeidler <sigma_z@sigma-scripts.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Dive\Test\Record;

use Dive\Record;
use Dive\RecordManager;
use Dive\TestSuite\TestCase;
use Dive\Util\FieldValuesGenerator;
use Dive\Validation\RecordInvalidException;

/**
 * Class RecordSaveUniqueConstraintTest
 * @author  Steffen Zeidler <sigma_z@sigma-scripts.de>
 * @created 02.05.2014
 */
class RecordSaveUniqueConstraintTest extends TestCase
{

    /** @var RecordManager */
    private $rm;

    /** @var RecordInvalidException */
    private $raisedException;

    /** @var Record */
    private $record;


    /**
     * @dataProvider provideDatabaseAwareTestCases
     * @param array $database
     */
    public function testSingleFieldUniqueConstraintNullConstrainedViolationThrowsException(array $database)
    {
        $this->givenIHaveConnectedTheDatabase($database);
        $this->givenIHaveStoredRecordWithData(array('single_unique' => 'unique'));
        $this->whenITryToSaveRecordWithData(array('single_unique' => 'unique'));
        $this->thenItShouldThrowAUniqueConstraintException();
        $this->thenItShouldHaveMarkedUniqueErrorForFields(array('single_unique'));
    }


    /**
     * @dataProvider provideDatabaseAwareTestCases
     * @param array $database
     */
    public function testSingleFieldUniqueConstraintNotNullConstrainedViolationThrowsException(array $database)
    {
        $this->givenIHaveConnectedTheDatabase($database);
        $this->givenIHaveStoredRecordWithData(array('single_unique_null_constrained' => 'unique'));
        $this->whenITryToSaveRecordWithData(array('single_unique_null_constrained' => 'unique'));
        $this->thenItShouldThrowAUniqueConstraintException();
        $this->thenItShouldHaveMarkedUniqueErrorForFields(array('single_unique_null_constrained'));
    }


    /**
     * @dataProvider provideDatabaseAwareTestCases
     * @param array $database
     */
    public function testSingleFieldUniqueConstraintNullConstrainedIsValid(array $database)
    {
        $this->givenIHaveConnectedTheDatabase($database);
        $this->givenIHaveStoredRecordWithData(array('single_unique' => null));
        $this->whenITryToSaveRecordWithData(array('single_unique' => 'unique'));
        $this->thenThereShouldBeTwoRecordsSaved();
    }


    /**
     * @dataProvider provideDatabaseAwareTestCases
     * @param array $database
     */
    public function testSingleFieldUniqueConstraintNullConstrainedIsValidWithNullValues(array $database)
    {
        $this->givenIHaveConnectedTheDatabase($database);
        $this->givenIHaveStoredRecordWithData(array('single_unique' => null));
        $this->whenITryToSaveRecordWithData(array('single_unique' => null));
        $this->thenThereShouldBeTwoRecordsSaved();
    }


    /**
     * @dataProvider provideDatabaseAwareTestCases
     * @param array $database
     */
    public function testCompositeUniqueConstraintNullConstrainedViolationThrowsException(array $database)
    {
        $this->givenIHaveConnectedTheDatabase($database);
        $this->givenIHaveStoredRecordWithData(array('composite_unique1' => 'unique', 'composite_unique2' => 'unique'));
        $this->whenITryToSaveRecordWithData(array('composite_unique1' => 'unique', 'composite_unique2' => 'unique'));
        $this->thenItShouldThrowAUniqueConstraintException();
        $this->thenItShouldHaveMarkedUniqueErrorForFields(array('composite_unique1', 'composite_unique2'));
    }


    /**
     * @dataProvider provideDatabaseAwareTestCases
     * @param array $database
     */
    public function testCompositeUniqueConstraintNotNullConstrainedViolationThrowsException(array $database)
    {
        $this->givenIHaveConnectedTheDatabase($database);
        $this->givenIHaveStoredRecordWithData(array(
            'composite_unique_null_constrained1' => null,
            'composite_unique_null_constrained2' => 'unique'
        ));
        $this->whenITryToSaveRecordWithData(array(
            'composite_unique_null_constrained1' => null,
            'composite_unique_null_constrained2' => 'unique'
        ));
        $this->thenItShouldThrowAUniqueConstraintException();
        $this->thenItShouldHaveMarkedUniqueErrorForFields(
            array('composite_unique_null_constrained1', 'composite_unique_null_constrained2')
        );
    }


    /**
     * @dataProvider provideDatabaseAwareTestCases
     * @param array $database
     */
    public function testCompositeUniqueConstraintNullConstrainedIsValid(array $database)
    {
        $this->givenIHaveConnectedTheDatabase($database);
        $this->givenIHaveStoredRecordWithData(array('composite_unique1' => null, 'composite_unique2' => 'unique'));
        $this->whenITryToSaveRecordWithData(array('composite_unique1' => 'unique', 'composite_unique2' => 'unique'));
        $this->thenThereShouldBeTwoRecordsSaved();
    }


    /**
     * @dataProvider provideDatabaseAwareTestCases
     * @param array $database
     */
    public function testCompositeUniqueConstraintNullConstrainedIsValidWithNullValues(array $database)
    {
        $this->givenIHaveConnectedTheDatabase($database);
        $this->givenIHaveStoredRecordWithData(array('composite_unique1' => null, 'composite_unique2' => 'unique'));
        $this->whenITryToSaveRecordWithData(array('composite_unique1' => null, 'composite_unique2' => 'unique'));
        $this->thenThereShouldBeTwoRecordsSaved();
    }


    /**
     * @param array $database
     */
    private function givenIHaveConnectedTheDatabase(array $database)
    {
        $this->rm = self::createRecordManager($database);
    }


    /**
     * @param array $recordData
     */
    private function givenIHaveStoredRecordWithData(array $recordData)
    {
        $record = $this->createRecordWithRandomData($recordData);
        $this->rm->scheduleSave($record);
        $this->rm->commit();
    }


    /**
     * @param array $recordData
     */
    private function whenITryToSaveRecordWithData(array $recordData)
    {
        $this->raisedException = null;
        try {
            $this->record = $this->createRecordWithRandomData($recordData);
            $this->rm->scheduleSave($this->record);
            $this->rm->commit();
        }
        catch (RecordInvalidException $e) {
           $this->raisedException = $e;
        }
    }


    private function thenItShouldThrowAUniqueConstraintException()
    {
        $this->assertNotNull($this->raisedException, 'Expected exception to be thrown');
        $this->assertInstanceOf('\\Dive\\Validation\\RecordInvalidException', $this->raisedException);
    }


    private function thenThereShouldBeTwoRecordsSaved()
    {
        $this->assertEquals(null, $this->raisedException, 'Expected exception NOT to be thrown');
        $this->assertEquals(2, $this->rm->getTable('unique_constraint_test')->count());
    }


    /**
     * @param array $recordData
     * @return \Dive\Record
     */
    private function createRecordWithRandomData(array $recordData)
    {
        $table = $this->rm->getTable('unique_constraint_test');
        $fieldValueGenerator = new FieldValuesGenerator();
        $recordData = $fieldValueGenerator->getRandomRecordData(
            $table->getFields(), $recordData, FieldValuesGenerator::MAXIMAL_WITHOUT_AUTOINCREMENT
        );
        return $table->createRecord($recordData);
    }


    /**
     * @param array $errorFields
     */
    private function thenItShouldHaveMarkedUniqueErrorForFields(array $errorFields)
    {
        $expectedErrors = array();
        foreach ($errorFields as $errorField) {
            $expectedErrors[$errorField] = array('unique');
        }
        $this->assertEquals($expectedErrors, $this->record->getErrorStack()->toArray());
    }
}
