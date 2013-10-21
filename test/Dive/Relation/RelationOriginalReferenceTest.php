<?php
/*
 * This file is part of the Dive ORM framework.
 * (c) Steffen Zeidler <sigma_z@sigma-scripts.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Dive\Test\Relation;

use Dive\Collection\RecordCollection;
use Dive\Record;
use Dive\RecordManager;
use Dive\Table;
use Dive\TestSuite\TestCase;

/**
 * @author  Steffen Zeidler <sigma_z@sigma-scripts.de>
 * @created 21.10.13
 */
class RelationOriginalReferenceTest extends TestCase
{

    private $tableRows = array(
        'JohnD' => array(
            'user' => array('JohnD'),
            'author' => array(
                'John' => array(
                    'firstname' => 'John',
                    'lastname' => 'Doe',
                    'email' => 'jdo@example.com',
                    'User' => 'JohnD'
                )
            ),
            'article' => array(
                'articleOne' => array('Author' => 'John'),
                'articleTwo' => array('Author' => 'John'),
                'articleThree' => array('Author' => 'John')
            )
        ),
        'SallyK' => array(
            'user' => array('SallyK'),
            'author' => array(
                'Sally' => array(
                    'firstname' => 'Sally',
                    'lastname' => 'Kingston',
                    'email' => 'ski@example.com',
                    'User' => 'SallyK'
                )
            )
        )
    );

    /** @var RecordManager */
    private $rm = null;


    protected function setUp()
    {
        parent::setUp();

        $this->rm = self::createDefaultRecordManager();
    }


    /**
     * @dataProvider provideOneToOne
     */
    public function testOneToOne($relationNameTo, $referenceExists)
    {
        $user = $this->createUserWithAuthor('JohnD', false);
        $relation = $user->getTable()->getRelation('Author');

        $isOwningSide = $relation->isOwningSide($relationNameTo);
        $fromRecord = $isOwningSide ? $user : $user->Author;
        $originalReferencedRecord = !$isOwningSide ? $user : $user->Author;

        $joinTable = $relation->getJoinTable($this->rm, $relationNameTo);

        if ($referenceExists) {
            $newUser = $this->createUserWithAuthor('SallyK', false);
            $newRecord = $isOwningSide ? $newUser->Author : $newUser;
        }
        else {
            $newRecord = $joinTable->createRecord();
        }

        $fromRecord->set($relationNameTo, $newRecord);

        $this->assertNotEquals($originalReferencedRecord, $newRecord);
        $this->assertNotEquals($originalReferencedRecord, $fromRecord->get($relationNameTo));

        $expectedOriginalIds = array($originalReferencedRecord->getInternalId());
        $this->assertOriginalReference($fromRecord, $relationNameTo, $expectedOriginalIds);
    }


    public function testOneToOneByOwningFieldToAnotherRecordId()
    {
        $user = $this->createUserWithAuthor('JohnD', false);
        $author = $user->Author;

        $newUser = $this->createUserWithAuthor('SallyK', false);
        $author->user_id = $newUser->id;
        $this->assertEquals($author->User, $newUser);
        $this->assertEquals($author->user_id, $newUser->getInternalId());

        $expectedOriginalIds = array($user->getInternalId());
        $this->assertOriginalReference($author, 'User', $expectedOriginalIds);
    }


    public function testOneToOneWithNotExistingId()
    {
        $user = $this->createUserWithAuthor('JohnD', false);
        $author = $user->Author;

        $author->user_id = 42;
        $this->assertEquals($author->user_id, 42);
        $this->assertFalse($author->User);

        $expectedOriginalIds = array($user->getInternalId());
        $this->assertOriginalReference($author, 'User', $expectedOriginalIds);
    }


    public function testOneToOneByOwningFieldToNull()
    {
        $user = $this->createUserWithAuthor('JohnD', false);
        $author = $user->Author;

        $author->user_id = null;
        $this->assertNull($author->User);

        $expectedOriginalIds = array($user->getInternalId());
        $this->assertOriginalReference($author, 'User', $expectedOriginalIds);
    }


    /**
     * @param Record   $record
     * @param string   $relationName
     * @param string[] $expectedOriginalIds
     */
    private function assertOriginalReference(Record $record, $relationName, array $expectedOriginalIds)
    {
        $relation = $record->getTable()->getRelation($relationName);
        $originalReferencedIds = $relation->getOriginalReferencedIds($record, $relationName);
        $this->assertEquals($expectedOriginalIds, $originalReferencedIds);
    }



    public function provideOneToOne()
    {
        return array(
            array('User', true),
            array('User', false),
            array('Author', true),
            array('Author', false)
        );
    }


    public function testOneToManyByChangingOwningField()
    {
        $user = $this->createUserWithAuthor('JohnD', true);
        $author = $user->Author;
        $authorTable = $this->rm->getTable('author');
        $relation = $authorTable->getRelation('Article');

        // assert before change
        /** @var RecordCollection $articles */
        $articles = $author->Article;
        $this->assertCount(3, $articles);

        // do change, and assert that collection has changed
        $articleToModify = $articles->getIterator()->current();
        // TODO
        $this->markTestIncomplete();
        $articleToModify->author_id = null;
        $this->assertCount(2, $articles);
        $this->assertFalse($articles->has($articleToModify->getInternalId()));

        // assert original references
        $originalReferencedIds = $relation->getOriginalReferencedIds($author, 'Article');
        $this->assertCount(3, $originalReferencedIds);
    }


    public function testOneToManyByRecordCollectionAdd()
    {
        $user = $this->createUserWithAuthor('JohnD', true);
        $author = $user->Author;
        $authorTable = $this->rm->getTable('author');
        $relation = $authorTable->getRelation('Article');

        $newArticle = $this->rm->getTable('article')->createRecord();

        // assert before change
        /** @var RecordCollection $articles */
        $articles = $author->Article;
        $this->assertCount(3, $articles);

        // do change, and assert that collection has changed
        $this->assertFalse($articles->has($newArticle->getInternalId()));
        $articles->add($newArticle);
        $this->assertCount(4, $articles);
        $this->assertTrue($articles->has($newArticle->getInternalId()));

        // assert original references
        $originalReferencedIds = $relation->getOriginalReferencedIds($author, 'Article');
        $this->assertCount(3, $originalReferencedIds);
    }


    public function testOneToManyByRecordCollectionRemove()
    {
        $user = $this->createUserWithAuthor('JohnD', true);
        $author = $user->Author;
        $authorTable = $this->rm->getTable('author');
        $relation = $authorTable->getRelation('Article');

        // assert before change
        /** @var RecordCollection $articles */
        $articles = $author->Article;
        $this->assertCount(3, $articles);

        // do change, and assert that collection has changed
        /** @var Record $unlinkArticle */
        $unlinkArticle = $articles->getIterator()->current();
        $this->assertTrue($articles->has($unlinkArticle->getInternalId()));
        $articles->unlinkRecord($unlinkArticle);
        $this->assertCount(2, $articles);
        $this->assertFalse($articles->has($unlinkArticle->getInternalId()));

        // assert original references
        $originalReferencedIds = $relation->getOriginalReferencedIds($author, 'Article');
        $this->assertCount(3, $originalReferencedIds);
    }


    public function testOneToManyByExchangingRecordCollection()
    {
        $user = $this->createUserWithAuthor('JohnD', true);
        $author = $user->Author;
        $authorTable = $this->rm->getTable('author');
        $relation = $authorTable->getRelation('Article');

        // assert before change
        /** @var RecordCollection $articles */
        $articles = $author->Article;
        $this->assertCount(3, $articles);

        // do change, and assert that collection has changed
        $articleTable = $this->rm->getTable('article');
        $newRecordCollection = new RecordCollection($articleTable);
        $newRecordCollection->add($articleTable->createRecord());

        $author->Article = $newRecordCollection;
        $this->assertCount(1, $author->Article);

        // assert original references
        $originalReferencedIds = $relation->getOriginalReferencedIds($author, 'Article');
        $this->assertCount(3, $originalReferencedIds);
    }


    public function testOneToManyByChangingReferencedRecordToNull()
    {
        $user = $this->createUserWithAuthor('JohnD', true);
        $author = $user->Author;
        $authorTable = $this->rm->getTable('author');
        $relation = $authorTable->getRelation('Article');

        // assert before change
        /** @var RecordCollection $articles */
        $articles = $author->Article;
        $this->assertCount(3, $articles);

        // do change, and assert that collection has changed
        $articleToModify = $articles->getIterator()->current();
        // TODO
        $this->markTestIncomplete();
        $articleToModify->Author = null;
        $this->assertCount(2, $articles);
        $this->assertFalse($articles->has($articleToModify->getInternalId()));

        // assert original references
        $originalReferencedIds = $relation->getOriginalReferencedIds($author, 'Article');
        $this->assertCount(3, $originalReferencedIds);
    }


    private function createUserWithAuthor($username, $withArticles)
    {
        $tablesRows = $this->tableRows[$username];
        if (!$withArticles) {
            unset($tablesRows['articles']);
        }

        $recordGenerator = $this->createRecordGenerator($this->rm);
        $recordGenerator
            ->setTablesMapField(array('user' => 'username'))
            ->setTablesRows($tablesRows)
            ->generate();

        $userId = $recordGenerator->getRecordIdFromMap('user', $username);
        $this->assertNotEmpty($userId);
        $userTable = $this->rm->getTable('user');
        $user = $userTable->findByPk($userId);

        $this->assertInstanceOf('\Dive\Record', $user);
        $this->assertInstanceOf('\Dive\Record', $user->Author);

        if ($withArticles && isset($tablesRows['articles'])) {
            $this->assertInstanceOf('\Dive\Collection\RecordCollection', $user->Author->Article);
            $this->assertEquals(count($tablesRows['articles']), $user->Author->Article->count());
        }

        return $user;
    }

}