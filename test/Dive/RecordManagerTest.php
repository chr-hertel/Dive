<?php
/*
 * This file is part of the Dive ORM framework.
 * (c) Steffen Zeidler <sigma_z@sigma-scripts.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Dive\Test;

use Dive\Hydrator\HydratorInterface;
use Dive\RecordManager;
use Dive\Schema\DataTypeMapper\DataTypeMapper;
use Dive\Table\Behaviour\TimestampableBehaviour;
use Dive\TestSuite\Record\Record;
use Dive\TestSuite\TestCase;
use Dive\Validation\FieldValidator\FieldTypeValidator;
use Dive\Validation\ValidatorInterface;

/**
 * @author Steffen Zeidler <sigma_z@sigma-scripts.de>
 * Date: 24.11.12
 */
class RecordManagerTest extends TestCase
{

    /** @var RecordManager */
    private $rm;


    protected function setUp()
    {
        parent::setUp();

        $this->rm = $this->createDefaultRecordManager();
    }


    public function testCreatedRecordManager()
    {
        $this->assertInstanceOf('\Dive\RecordManager', $this->rm);
    }


    public function testGetTable()
    {
        $table = $this->rm->getTable('user');
        $this->assertInstanceOf('\Dive\Table', $table);
    }


    public function testGetTableRepository()
    {
        $repository = $this->rm->getTableRepository('user');
        $this->assertInstanceOf('\Dive\Table\Repository', $repository);
    }


    /**
     * @expectedException \Dive\Schema\SchemaException
     */
    public function testGetNotExistingTable()
    {
        $this->rm->getTable('notexistingtable');
    }


    public function testGetConnection()
    {
        $this->assertInstanceOf('Dive\Connection\Connection', $this->rm->getConnection());
    }


    public function testClearTables()
    {
        $this->assertCount(0, self::readAttribute($this->rm, 'tables'));
        $this->rm->getTable('user');
        $this->assertCount(1, self::readAttribute($this->rm, 'tables'));
        $this->rm->getTable('author');
        $this->assertCount(2, self::readAttribute($this->rm, 'tables'));
        $this->rm->getTable('author');
        $this->assertCount(2, self::readAttribute($this->rm, 'tables'));
        $this->rm->clearTables();
        $this->assertCount(0, self::readAttribute($this->rm, 'tables'));
    }


    /**
     * @param string $hydratorName
     * @param string $expectedHydratorClassName
     *
     * @dataProvider provideGetDiveDefinedHydrator
     */
    public function testGetDiveDefinedHydrator($hydratorName, $expectedHydratorClassName)
    {
        $collHydrator = $this->rm->getHydrator($hydratorName);
        $this->assertInstanceOf($expectedHydratorClassName, $collHydrator);
    }


    /**
     * @return array
     */
    public function provideGetDiveDefinedHydrator()
    {
        return array(
            array(RecordManager::FETCH_RECORD_COLLECTION, '\Dive\Hydrator\RecordCollectionHydrator'),
            array(RecordManager::FETCH_RECORD,            '\Dive\Hydrator\RecordHydrator'),
            array(RecordManager::FETCH_ARRAY,             '\Dive\Hydrator\ArrayHydrator'),
            array(RecordManager::FETCH_SINGLE_ARRAY,      '\Dive\Hydrator\SingleArrayHydrator'),
            array(RecordManager::FETCH_SCALARS,           '\Dive\Hydrator\ScalarHydrator'),
            array(RecordManager::FETCH_SINGLE_SCALAR,     '\Dive\Hydrator\SingleScalarHydrator'),
        );
    }


    /**
     * @expectedException \Dive\Exception
     */
    public function testGetDiveDefinedHydratorNotExistingException()
    {
        $this->rm->getHydrator('notexistingname');
    }


    public function testGetSchema()
    {
        $this->assertInstanceOf('\Dive\Schema\Schema', self::readAttribute($this->rm, 'schema'));
    }


    public function testSetCustomHydrator()
    {
        /** @var HydratorInterface $customHydrator */
        $customHydrator = $this->getMockForAbstractClass('\Dive\Hydrator\HydratorInterface');
        $this->rm->setHydrator('custom', $customHydrator);
        $actualCustomHydrator = $this->rm->getHydrator('custom');
        $this->assertEquals($customHydrator, $actualCustomHydrator);
    }


    /**
     * @expectedException \Dive\Schema\SchemaException
     */
    public function testTableNotFoundException()
    {
        $this->rm->getTable('notexistingtablename');
    }


    public function testGetTableWithBehaviour()
    {
        $tableName = 'article';
        // initializes article table and instances TimestampableBehaviour as shared instance
        $this->rm->getTable($tableName);

        $tableBehaviours = self::readAttribute($this->rm, 'tableBehaviours');
        $this->assertCount(1, $tableBehaviours);
        /** @var TimestampableBehaviour $timestampableBehaviour */
        $timestampableBehaviour = current($tableBehaviours);
        $this->assertInstanceOf('\Dive\Table\Behaviour\TimestampableBehaviour', $timestampableBehaviour);

        $eventDispatcher = $this->rm->getEventDispatcher();
        $this->assertCount(1, $eventDispatcher->getListeners(Record::EVENT_PRE_SAVE));
        $this->assertCount(1, $eventDispatcher->getListeners(Record::EVENT_PRE_UPDATE));
        $this->assertCount(1, $eventDispatcher->getListeners(Record::EVENT_PRE_INSERT));
    }


    public function testHasAConfiguredValidationContainer()
    {
        $rm = self::createDefaultRecordManager();
        $validationContainer = $rm->getRecordValidationContainer();
        $this->assertNotNull($validationContainer);
        $this->assertInstanceOf('\Dive\Validation\ValidationContainer', $validationContainer);

        $uniqueValidator = $validationContainer->getValidator(ValidatorInterface::VALIDATOR_UNIQUE_CONSTRAINT);
        $this->assertNotNull($uniqueValidator);
        $this->assertInstanceOf('\Dive\Validation\UniqueValidator\UniqueRecordValidator', $uniqueValidator);

        /** @var FieldTypeValidator $fieldTypeValidator */
        $fieldTypeValidator = $validationContainer->getValidator(ValidatorInterface::VALIDATOR_FIELD_TYPE);
        $this->assertNotNull($fieldTypeValidator);
        $this->assertInstanceOf('\Dive\Validation\FieldValidator\FieldTypeValidator', $fieldTypeValidator);
        $booleanOrmDataTypeValidator = $fieldTypeValidator->getDataTypeValidator(DataTypeMapper::OTYPE_BOOLEAN);
        $this->assertInstanceOf('\Dive\Schema\OrmDataType\BooleanOrmDataTypeValidator', $booleanOrmDataTypeValidator);

//        $validator = $validationContainer->getValidator(ValidatorInterface::VALIDATOR_FIELD_LENGTH);
//        $this->assertNotNull($validator);
    }

}
