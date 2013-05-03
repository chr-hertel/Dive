<?php
 /*
 * This file is part of the Dive ORM framework.
 * (c) Steffen Zeidler <sigma_z@sigma-scripts.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * @author  Steffen Zeidler <sigma_z@sigma-scripts.de>
 * @created 22.04.13
 *
 * TODO implementing nullable one-to-one relation test
 */

namespace Dive\Test\Relation;

require_once __DIR__ . '/AbstractRelationSetReferenceTestCase.php';

use Dive\Record;
use Dive\RecordManager;
use Dive\TestSuite\TestCase;
use Dive\Util\FieldValuesGenerator;

class SetOneToOneReferenceTest extends AbstractRelationSetReferenceTestCase
{

    /**
     * @dataProvider provideOneToOne
     *
     * @param bool $userExists
     * @param bool $authorExists
     */
    public function testOneToOneReferencedSide($userExists, $authorExists)
    {
        list($user, $author) = $this->createUserAndAuthor($userExists, $authorExists);

        // setting reference
        $user->Author = $author;

        // assertions
        $this->assertEquals($author, $user->Author);
        $this->assertEquals($user, $user->Author->User);
    }


    /**
     * @dataProvider provideOneToOne
     *
     * @param bool $authorExists
     * @param bool $userExists
     */
    public function testOneToOneOwningSide($userExists, $authorExists)
    {
        list($user, $author) = $this->createUserAndAuthor($userExists, $authorExists);

        // setting reference
        $author->User = $user;

        // assertions
        $this->assertEquals($user, $author->User);
        $this->assertEquals($author, $author->User->Author);
    }


    public function provideOneToOne()
    {
        $testCases = array();

        // [userExists, authorExists]
        $testCases[] = array(false, false);
        //$testCases[] = array(false, true); // should not work, because author cannot be saved for non-existing user!!
        $testCases[] = array(true, false);
        $testCases[] = array(true, true);

        return $testCases;
    }


    public function testOneToOneReferencedSideNullReference()
    {
        $user = $this->createUser('UserOne');
        $this->assertNull($user->Author, 'Expected reference $user->Author to be NULL!');

        $author = $this->createAuthor('AuthorOne');
        $this->assertNull($author->User, 'Expected reference $author->User to be NULL!');
        $user->Author = $author;
        $this->assertEquals($author, $user->Author, 'Expected reference $user->Author to be the Author record!');
        $this->assertEquals($user, $author->User, 'Expected reference $author->User to be the User record!');

        $user->Author = null;
        $this->assertNull($user->Author, 'Expected reference $user->Author to be NULL!');
        $this->assertNull($author->User, 'Expected reference $author->User to be NULL!');
    }


    public function testOneToOneOwningSideNullReference()
    {
        $user = $this->createUser('UserOne');
        $this->assertNull($user->Author, 'Expected reference $user->Author to be NULL!');

        $author = $this->createAuthor('AuthorOne');
        $this->assertNull($author->User, 'Expected reference $author->User to be NULL!');
        $author->User = $user;
        $this->assertEquals($user, $author->User, 'Expected reference $author->User to be the User record!');
        $this->assertEquals($author, $user->Author, 'Expected reference $user->Author to be the Author record!');

        $author->User = null;
        $this->assertNull($author->User, 'Expected reference $author->User to be NULL!');
        $this->assertNull($user->Author, 'Expected reference $user->Author to be NULL!');
    }


    private function createUserAndAuthor($userExists, $authorExists)
    {
        $user = $this->createUser('UserOne');
        if ($userExists) {
            $user->save();
        }
        $author = $this->createAuthor('AuthorOne');
        if ($userExists) {
            $author->user_id = $user->id;   // TODO foreign key should be set through Record::save()
        }
        if ($authorExists) {
            $author->save();
        }
        return array($user, $author);
    }

}