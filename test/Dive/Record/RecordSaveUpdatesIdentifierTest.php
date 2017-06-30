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
use Dive\Record\Generator\RecordGenerator;
use Dive\RecordManager;
use Dive\Relation\ReferenceMap;
use Dive\Relation\Relation;
use Dive\TestSuite\Model\Article;
use Dive\TestSuite\Model\Author;
use Dive\TestSuite\Model\Comment;
use Dive\TestSuite\Model\User;
use Dive\TestSuite\TestCase;
use Dive\Util\CamelCase;

/**
 * @author  Steffen Zeidler <sigma_z@sigma-scripts.de>
 * @created 12.09.2014
 */
class RecordSaveUpdatesIdentifierTest extends TestCase
{

    /** @var RecordManager */
    private $rm;

    /** @var Record */
    private $storedRecord;

    /** @var Record */
    private $relatedRecord;

    /** @var string */
    private $oldIdentifierStoredRecord;

    /** @var string */
    private $oldIdentifierRelatedRecord;

    /** @var RecordGenerator */
    private $recordGenerator;


    protected function tearDown()
    {
        if ($this->storedRecord) {
            $this->rm->scheduleDelete($this->storedRecord);
        }

        if ($this->relatedRecord) {
            $this->rm->scheduleDelete($this->relatedRecord);
        }
        $this->rm->commit();

        if ($this->recordGenerator) {
            $this->recordGenerator->removeGeneratedRecords();
        }

        parent::tearDown();
    }


    public function testSaveSingleRecord()
    {
        $this->givenIHaveARecordManager();
        $this->givenIHaveASingleRecordStored();

        $this->whenIChangeTheRecordIdentifierTo('123');
        $this->whenISaveTheRecord();

        $this->thenTheRecordIdentifierShouldHaveBeenUpdatedInTheDatabase();
        $this->thenTheRecordIdentifierShouldHaveBeenUpdatedInTheRepository();
    }


    public function testSaveOwningRecordOneToOne()
    {
        $this->givenIHaveARecordManager();
        $this->givenIHaveAOwningRecordWithAnOneToOneRelatedRecord();

        $this->whenIChangeTheRecordIdentifierTo('123');
        $this->whenIChangeTheRelatedRecordIdentifierTo('321');
        $this->whenISaveTheRecord();

        $this->thenTheRecordIdentifierShouldHaveBeenUpdatedInTheDatabase();
        $this->thenTheRecordIdentifierShouldHaveBeenUpdatedInTheRepository();
        $this->thenTheRelatedRecordIdentifierShouldHaveBeenUpdatedInTheDatabase();
        $this->thenTheRelatedRecordIdentifierShouldHaveBeenUpdatedInTheRepository();
        $this->thenTheOwningFieldMappingShouldReferenceTheReferencedRecord();
        $this->thenTheOwningFieldShouldBeEqualTheReferencedIdentifier();
    }


    public function testSaveReferencedRecordOneToOne()
    {
        $this->givenIHaveARecordManager();
        $this->givenIHaveAReferencedRecordWithAnOneToOneRelatedRecord();

        $this->whenIChangeTheRecordIdentifierTo('123');
        $this->whenIChangeTheRelatedRecordIdentifierTo('321');
        $this->whenISaveTheRecord();

        $this->thenTheRecordIdentifierShouldHaveBeenUpdatedInTheDatabase();
        $this->thenTheRecordIdentifierShouldHaveBeenUpdatedInTheRepository();
        $this->thenTheRelatedRecordIdentifierShouldHaveBeenUpdatedInTheDatabase();
        $this->thenTheRelatedRecordIdentifierShouldHaveBeenUpdatedInTheRepository();
        $this->thenTheOwningFieldMappingShouldReferenceTheReferencedRecord();
        $this->thenTheOwningFieldShouldBeEqualTheReferencedIdentifier();
    }


    public function testSaveOwningRecordOneToMany()
    {
        $this->givenIHaveARecordManager();
        $this->givenIHaveAOwningRecordWithAnOneToManyRelatedRecord();

        $this->whenIChangeTheRecordIdentifierTo('123');
        $this->whenIChangeTheRelatedRecordIdentifierTo('321');
        $this->whenISaveTheRecord();

        $this->thenTheRecordIdentifierShouldHaveBeenUpdatedInTheDatabase();
        $this->thenTheRecordIdentifierShouldHaveBeenUpdatedInTheRepository();
        $this->thenTheRelatedRecordIdentifierShouldHaveBeenUpdatedInTheDatabase();
        $this->thenTheRelatedRecordIdentifierShouldHaveBeenUpdatedInTheRepository();
        $this->thenTheOwningFieldMappingShouldReferenceTheReferencedRecord();
        $this->thenTheOwningFieldShouldBeEqualTheReferencedIdentifier();
    }


    public function testSaveReferencedRecordOneToMany()
    {
        $this->givenIHaveARecordManager();
        $this->givenIHaveAReferencedRecordWithAnOneToManyRelatedRecord();

        $this->whenIChangeTheRecordIdentifierTo('123');
        $this->whenIChangeTheRelatedRecordIdentifierTo('321');
        $this->whenISaveTheRecord();

        $this->thenTheRecordIdentifierShouldHaveBeenUpdatedInTheDatabase();
        $this->thenTheRecordIdentifierShouldHaveBeenUpdatedInTheRepository();
        $this->thenTheRelatedRecordIdentifierShouldHaveBeenUpdatedInTheDatabase();
        $this->thenTheRelatedRecordIdentifierShouldHaveBeenUpdatedInTheRepository();
        $this->thenTheOwningFieldMappingShouldReferenceTheReferencedRecord();
        $this->thenTheOwningFieldShouldBeEqualTheReferencedIdentifier();
    }


    public function testSetReferenceOnOwningRecord()
    {
        $article = $this->givenIHaveASavedArticle('Post #1');
        $comment1 = $this->givenIHaveCreatedAComment_withUser('comment1', 'Hugo');
        $comment1->Article = $article;
        $this->whenISaveTheComment($comment1);

        $comment2 = $this->givenIHaveCreatedAComment_withUser('comment2', 'Herbert');
        $comment2->Article = $article;
        $this->whenISaveTheComment($comment2);

        $this->assertCount(2, $article->Comment);
    }


    public function testSetReferenceOnReferencedRecord()
    {
        $article = $this->givenIHaveASavedArticle('Post #1');
        $comment1 = $this->givenIHaveCreatedAComment_withUser('comment1', 'Hugo');
        $article->Comment[] = $comment1;
        $this->whenISaveTheComment($comment1);

        $comment2 = $this->givenIHaveCreatedAComment_withUser('comment2', 'Herbert');
        $article->Comment[] = $comment2;
        $this->whenISaveTheComment($comment2);

        $this->assertCount(2, $article->Comment);
    }


    // #####  given / when / then methods  #####
    private function givenIHaveARecordManager()
    {
        $this->rm = self::createDefaultRecordManager();
    }


    private function givenIHaveASingleRecordStored()
    {
        $user = $this->createUser();

        $this->rm->scheduleSave($user);
        $this->rm->commit();

        $this->oldIdentifierStoredRecord = $user->getIdentifierAsString();
        $this->storedRecord = $user;
    }


    private function givenIHaveAOwningRecordWithAnOneToOneRelatedRecord()
    {
        $user = $this->createUser();
        $author = $this->createAuthor();
        $author->User = $user;

        $this->rm->scheduleSave($author);
        $this->rm->commit();

        $this->oldIdentifierStoredRecord = $author->getIdentifierAsString();
        $this->oldIdentifierRelatedRecord = $user->getIdentifierAsString();
        $this->storedRecord = $author;
        $this->relatedRecord = $user;
    }


    private function givenIHaveAReferencedRecordWithAnOneToOneRelatedRecord()
    {
        $user = $this->createUser();
        $author = $this->createAuthor();
        $user->Author = $author;

        $this->rm->scheduleSave($user);
        $this->rm->commit();

        $this->oldIdentifierStoredRecord = $user->getIdentifierAsString();
        $this->oldIdentifierRelatedRecord = $author->getIdentifierAsString();
        $this->storedRecord = $user;
        $this->relatedRecord = $author;
    }


    private function givenIHaveAOwningRecordWithAnOneToManyRelatedRecord()
    {
        $user = $this->createUser();
        $author = $this->createAuthor();
        $article = $this->createArticle();
        $article->Author = $author;
        $author->User = $user;

        $this->rm->scheduleSave($article);
        $this->rm->commit();

        $this->oldIdentifierStoredRecord = $article->getIdentifierAsString();
        $this->oldIdentifierRelatedRecord = $author->getIdentifierAsString();
        $this->storedRecord = $article;
        $this->relatedRecord = $author;
    }


    private function givenIHaveAReferencedRecordWithAnOneToManyRelatedRecord()
    {
        $user = $this->createUser();
        $author = $this->createAuthor();
        $article = $this->createArticle();
        $author->Article[] = $article;
        $author->User = $user;

        $this->rm->scheduleSave($author);
        $this->rm->commit();

        $this->oldIdentifierStoredRecord = $author->getIdentifierAsString();
        $this->oldIdentifierRelatedRecord = $article->getIdentifierAsString();
        $this->storedRecord = $author;
        $this->relatedRecord = $article;
    }


    /**
     * @param  string $identifier
     * @throws \Dive\Table\TableException
     */
    private function whenIChangeTheRecordIdentifierTo($identifier)
    {
        $this->storedRecord->set('id', $identifier);
    }


    private function whenISaveTheRecord()
    {
        $this->rm->scheduleSave($this->storedRecord);
        $this->rm->commit();
    }


    /**
     * @param  string $id
     * @throws \Dive\Table\TableException
     */
    private function whenIChangeTheRelatedRecordIdentifierTo($id)
    {
        $this->relatedRecord->set('id', $id);
    }


    private function thenTheRecordIdentifierShouldHaveBeenUpdatedInTheDatabase()
    {
        $identifier = $this->storedRecord->getIdentifierAsString();
        $table = $this->storedRecord->getTable();
        $isStoredInDatabase = $table->createQuery()->where('id = ?', $identifier)->hasResult();
        $this->assertTrue($isStoredInDatabase);
    }


    private function thenTheRelatedRecordIdentifierShouldHaveBeenUpdatedInTheDatabase()
    {
        $identifier = $this->relatedRecord->getIdentifierAsString();
        $table = $this->relatedRecord->getTable();
        $isStoredInDatabase = $table->createQuery()->where('id = ?', $identifier)->hasResult();
        $this->assertTrue($isStoredInDatabase);
    }


    private function thenTheRecordIdentifierShouldHaveBeenUpdatedInTheRepository()
    {
        $identifier = $this->storedRecord->getIdentifierAsString();
        $table = $this->storedRecord->getTable();
        $repository = $table->getRepository();
        $this->assertFalse($repository->hasByInternalId($this->oldIdentifierStoredRecord));
        $this->assertTrue($repository->hasByInternalId($identifier));
    }


    private function thenTheRelatedRecordIdentifierShouldHaveBeenUpdatedInTheRepository()
    {
        $identifier = $this->relatedRecord->getIdentifierAsString();
        $table = $this->relatedRecord->getTable();
        $repository = $table->getRepository();
        $this->assertFalse($repository->hasByInternalId($this->oldIdentifierRelatedRecord));
        $this->assertTrue($repository->hasByInternalId($identifier));
    }


    /**
     * @param string $userName
     * @return User
     */
    private function createUser($userName = 'Hugo')
    {
        $table = $this->rm->getTable('user');
        return self::getRecordWithRandomData($table, ['username' => $userName]);
    }


    /**
     * @return Author
     */
    private function createAuthor()
    {
        $table = $this->rm->getTable('author');
        $recordData = array('id' => '1', 'lastname' => 'Smith', 'email' => 'smith@example.com');
        return self::getRecordWithRandomData($table, $recordData);
    }


    /**
     * @return Article
     */
    private function createArticle()
    {
        $table = $this->rm->getTable('article');
        $recordData = array(
            'id' => '1',
            'title' => 'Release announcement',
            'teaser' => 'Dive release with some brand new features',
            'text' => 'Dive into Dive'
        );
        return self::getRecordWithRandomData($table, $recordData);
    }


    private function thenTheOwningFieldMappingShouldReferenceTheReferencedRecord()
    {
        $relation = $this->getRelation();
        $relationName = CamelCase::toCamelCase($this->relatedRecord->getTable()->getTableName());
        $owningRecord = $this->getOwningRecord($relation, $relationName);
        $referencedRecord = $this->getReferencedRecord($relation, $relationName);

        /** @var ReferenceMap $referenceMap */
        $referenceMap = self::readAttribute($relation, 'map');
        $owningOid = $owningRecord->getOid();
        $this->assertTrue($referenceMap->hasFieldMapping($owningOid));
        $this->assertEquals($referencedRecord->getOid(), $referenceMap->getFieldMapping($owningOid));
    }


    private function thenTheOwningFieldShouldBeEqualTheReferencedIdentifier()
    {
        $relation = $this->getRelation();
        $relationName = CamelCase::toCamelCase($this->relatedRecord->getTable()->getTableName());
        $owningRecord = $this->getOwningRecord($relation, $relationName);
        $referencedRecord = $this->getReferencedRecord($relation, $relationName);

        $this->assertEquals($referencedRecord->getIdentifierAsString(), $owningRecord->get($relation->getOwningField()));
    }


    /**
     * @return Relation
     */
    private function getRelation()
    {
        $relationName = CamelCase::toCamelCase($this->relatedRecord->getTable()->getTableName());
        return $this->storedRecord->getTableRelation($relationName);
    }


    /**
     * @param  Relation $relation
     * @param  string   $relationName
     * @return Record
     */
    private function getOwningRecord(Relation $relation, $relationName)
    {
        return $relation->isOwningSide($relationName)
            ? $this->relatedRecord
            : $this->storedRecord;
    }


    /**
     * @param  Relation $relation
     * @param  string   $relationName
     * @return Record
     */
    private function getReferencedRecord(Relation $relation, $relationName)
    {
        return $relation->isReferencedSide($relationName)
            ? $this->relatedRecord
            : $this->storedRecord;
    }


    /**
     * @param string $articleTitle
     * @return Article
     */
    private function givenIHaveASavedArticle($articleTitle)
    {
        $this->givenIHaveARecordManager();
        $this->initRecordGenerator();
        $id = $this->recordGenerator->generateRecord('article', [['title' => $articleTitle]]);
        return $this->rm->getTable('article')->findByPk($id);
    }


    private function initRecordGenerator()
    {
        if (!$this->recordGenerator) {
            $this->recordGenerator = self::createRecordGenerator($this->rm);
        }
    }


    /**
     * @param string $commentTitle
     * @param string $userName
     * @return Comment
     */
    private function givenIHaveCreatedAComment_withUser($commentTitle, $userName)
    {
        $user = $this->createUser($userName);
        $this->rm->scheduleSave($user)->commit();
        $table = $this->rm->getTable('comment');
        $recordData = ['title' => $commentTitle, 'user_id' => $user->id];
        return self::getRecordWithRandomData($table, $recordData);

    }


    /**
     * @param Comment $comment
     */
    private function whenISaveTheComment(Comment $comment)
    {
        $this->rm->scheduleSave($comment)->commit();
    }

}
