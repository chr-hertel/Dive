<?php
/*
 * This file is part of the Dive ORM framework.
 * (c) Steffen Zeidler <sigma_z@sigma-scripts.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Dive\Test\Record;

use Dive\RecordManager;
use Dive\TestSuite\DatasetRegistry;
use Dive\TestSuite\TestCase;

/**
 * @author  Steffen Zeidler <sigma_z@sigma-scripts.de>
 * @created 30.08.13
 */
class RecordDeleteGraphTest extends TestCase
{

    protected function setUp()
    {
        $this->markTestSkipped('UnitOfWork will handle record changes!');
    }


    /**
     * @dataProvider provideOneToOneDelete
     */
    public function testOneToOneReferencedSideDelete($tableName, array $graphData, $relationName)
    {
        $record = $this->saveRecordGraph($tableName, $graphData);
        $changeSet = $record->delete();

        $affected = $changeSet->getScheduledForDelete();
        echo count($affected);
    }


    /**
     * @dataProvider provideOneToOneDelete
     */
    public function testOneToOneOwningSideDelete($tableName, array $graphData, $relationName)
    {
        $record = $this->saveRecordGraph($tableName, $graphData);
        $record = $record->get($relationName);
        $changeSet = $record->delete();

        $affected = $changeSet->getScheduledForDelete();
        echo count($affected);
    }





//    public function testOneToOneReferencedSide()
//    {
//        $this->markTestIncomplete();
//
//        $author = $this->saveRecordGraph('author', self::$authorUserGraph);
//        $changeSet = $author->delete();
//
//        $this->assertFalse($author->exists());
//        $affected = $changeSet->getScheduledForDelete();
//        echo count($affected);
//    }
//
//
//    public function testOneToOneOwningSide()
//    {
//        $this->markTestIncomplete();
//
//        $author = $this->saveRecordGraph('author', self::$authorUserGraph);
//        $user = $author->User;
//        $changeSet = $user->delete();
//
//        $this->assertFalse($user->exists());
//        $affected = $changeSet->getScheduledForDelete();
//        echo count($affected);
//    }


    private function saveRecordGraph($tableName, array $graphData)
    {
        $rm = self::createDefaultRecordManager();
        $table = $rm->getTable($tableName);
        $record = $table->createRecord();
        $record->fromArray($graphData);
        $record->save();

        return $record;
    }


    public function provideOneToOneDelete()
    {
        $testCases = array();

        $authorUserGraphData = array(
            'username' => 'John',
            'password' => 'secret',
            'Author' => array(
                'firstname' => 'John',
                'lastname' => 'Doe',
                'email' => 'jdo@example.com'
            )
        );
        $testCases[] = array(
            'user',
            $authorUserGraphData,
            'Author',
            false
        );
        $testCases[] = array(
            'user',
            $authorUserGraphData,
            'Author',
            false
        );

        return $testCases;
    }

}
