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
use Dive\Record;
use Dive\TestSuite\ChangeForCommitTestCase;

/**
 * @author  Steffen Zeidler <sigma_z@sigma-scripts.de>
 * @created 30.08.13
 */
class RecordOneToOneDeleteTest extends ChangeForCommitTestCase
{

    /**
     * @var array
     */
    protected $tableRows = array(
        'JohnD' => array(
            'user' => array('JohnD'),
            'author' => array(
                'John' => array(
                    'firstname' => 'John',
                    'lastname' => 'Doe',
                    'email' => 'jdo@example.com',
                    'User' => 'JohnD'
                )
            )
        ),
        'SallyK' => array(
            'user' => array('SallyK'),
            'author' => array(
                'SallyK' => array(
                    'firstname' => 'Sally',
                    'lastname' => 'Kingston',
                    'email' => 'ski@example.com',
                    'User' => 'SallyK'
                )
            )
        )
    );


    /**
     * @param string $side
     * @dataProvider provideRelationSides
     */
    public function testDeleteOnNonSavedRecordsOwningSide($side)
    {
        $rm = $this->getRecordManagerWithOverWrittenConstraint(self::CONSTRAINT_TYPE_ON_DELETE, 'author.user_id');

        $user = $rm->getRecord('user', array());
        $author = $rm->getTable('author')->createRecord();
        $user->Author = $author;

        if ($side == self::RELATION_SIDE_REFERENCED) {
            $recordToDelete = $user;
        }
        else {
            $recordToDelete = $author;
        }
        $rm->delete($recordToDelete);
        $this->assertRecordIsNotScheduledForDelete($author);
        $this->assertRecordIsNotScheduledForDelete($user);
    }


    /**
     * @param string $side
     * @dataProvider provideRelationSides
     */
    public function testDeleteSetNullConstraintOwningSide($side)
    {
        $rm = $this->getRecordManagerWithOverWrittenConstraint(
            self::CONSTRAINT_TYPE_ON_DELETE, 'author.user_id', PlatformInterface::SET_NULL
        );
        $user = $this->createUserWithAuthor($rm, 'JohnD');
        $author = $user->Author;


        if ($side == self::RELATION_SIDE_REFERENCED) {
            $rm->delete($user);
            $this->assertRecordIsScheduledForSave($author);

            $this->assertRecordIsScheduledForDelete($user);
            $this->assertNull($author->user_id);

            $deleteSecond = $author;
        }
        else {
            // does not touch user record
            $rm->delete($author);
            $this->assertRecordIsScheduledForDelete($author);

            $this->assertRecordIsNotScheduledForDelete($user);
            $this->assertNotNull($author->user_id);

            $deleteSecond = $user;
        }

        $rm->delete($deleteSecond);
        $this->assertRecordIsScheduledForDelete($user);
        $this->assertRecordIsScheduledForDelete($author);
    }


    /**
     * deleting the author, user stays untouched
     * both records have to be deleted
     */
    public function testDeleteCascadeConstraintReferencedSide()
    {
        $rm = $this->getRecordManagerWithOverWrittenConstraint(
            self::CONSTRAINT_TYPE_ON_DELETE, 'author.user_id', PlatformInterface::CASCADE
        );

        $user = $this->createUserWithAuthor($rm, 'JohnD');
        $author = $user->Author;

        $this->assertRecordIsNotScheduledForDelete($author);
        $this->assertRecordIsNotScheduledForDelete($user);
        $rm->delete($user);

        $this->assertFalse($author->isFieldModified('user_id'));
        $this->assertRecordIsScheduledForDelete($user);
        $this->assertRecordIsScheduledForDelete($author);

        $rm->delete($author);
        $this->assertRecordIsScheduledForDelete($author);
    }


    /**
     */
    public function testDeleteCascadeConstraintOwningSide()
    {
        $rm = $this->getRecordManagerWithOverWrittenConstraint(
            self::CONSTRAINT_TYPE_ON_DELETE, 'author.user_id', PlatformInterface::CASCADE
        );

        $user = $this->createUserWithAuthor($rm, 'JohnD');
        $author = $user->Author;

        $rm->delete($author);
        $this->assertRecordIsScheduledForDelete($author);
        $this->assertRecordIsNotScheduledForDelete($user);
    }


    /**
     * @param string $constraint
     * @dataProvider provideConstraints
     */
    public function testDeleteThrowsExceptionForScheduleSaveAfterDelete($constraint)
    {
        $rm = $this->getRecordManagerWithOverWrittenConstraint(
            self::CONSTRAINT_TYPE_ON_DELETE, 'author.user_id', $constraint
        );
        $user = $this->createUserWithAuthor($rm, 'JohnD');
        $rm->delete($user->Author);
        $this->assertRecordIsScheduledForDelete($user->Author);
        $this->assertRecordIsNotScheduledForDelete($user);

        // when the record is scheduled for delete it can't be saved again
        $this->setExpectedException('\Dive\UnitOfWork\UnitOfWorkException');
        $rm->save($user->Author);
    }


    /**
     * @param string $side
     * @dataProvider provideRelationSides
     */
    public function testRecordWithModifiedReferenceOnReferenceSide($side = 'reference')
    {
        $rm = $this->getRecordManagerWithOverWrittenConstraint(
            self::CONSTRAINT_TYPE_ON_DELETE, 'author.user_id', PlatformInterface::CASCADE
        );

        $userJohn = $this->createUserWithAuthor($rm, 'JohnD');
        $authorJohn = $userJohn->Author;
        $userSally = $this->createUserWithAuthor($rm, 'SallyK');
        $authorSally = $userSally->Author;

        if ($side == self::RELATION_SIDE_REFERENCED) {
            $recordToBeDeleted = $userJohn;
            $authorWithoutUser = $authorJohn;
            $recordNotToBeDeleted = $authorJohn;
            $recordToBeDeleted->Author = $authorSally;
        }
        else {
            $recordToBeDeleted = $authorJohn;
            $authorWithoutUser = $authorSally;
            $recordNotToBeDeleted = $userJohn;
            $authorJohn->User = $userSally;
        }
        $rm->delete($recordToBeDeleted);
        $this->assertRecordIsScheduledForDelete($recordToBeDeleted);
        $this->assertNull($authorWithoutUser->get('user_id'));
        $this->assertRecordIsNotScheduledForDelete($recordNotToBeDeleted);

        $this->assertRecordIsNotScheduledForDelete($userSally);
        $this->assertRecordIsNotScheduledForDelete($authorSally);
    }


    /**
     * @dataProvider provideConstraints
     */
    public function testRecordWithoutOwningSide($constraint)
    {
        $rm = $this->getRecordManagerWithOverWrittenConstraint(
            self::CONSTRAINT_TYPE_ON_DELETE, 'author.user_id', $constraint
        );

        $userData = array('username' => 'JohnD', 'password' => 'secret');
        $user = $rm->getTable('user')->createRecord($userData);
        $rm->save($user);
        $rm->commit();

        $relation = $user->getTableRelation('Author');
        $this->assertFalse($relation->hasReferenceLoadedFor($user, 'Author'));

        $rm->delete($user);
        $this->assertRecordIsScheduledForDelete($user);
    }

}
