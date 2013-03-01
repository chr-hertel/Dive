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
 * Date: 23.02.13
 */

namespace Dive\Test\Util;

use Dive\Util\StringExplode;


class StringExplodeTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider provideExplodeAt
     */
    public function testExplodeAt($string, $positions, $shift, $expected)
    {
        $actual = StringExplode::explodeAt($string, $positions, $shift);
        $this->assertEquals($expected, $actual);
    }


    public function provideExplodeAt()
    {
        $testString1 = 'a.TestString b';
        $pos1 = strpos($testString1, '.');
        $pos2 = strpos($testString1, ' ');
        return array(
            array(
                $testString1,
                array(),
                1,
                array($testString1)
            ),
            array(
                $testString1,
                array($pos1, $pos2),
                1,
                array('a', 'TestString', 'b')
            ),
            array(
                $testString1,
                array($pos1, $pos2),
                0,
                array('a', '.TestString', ' b')
            ),
            array(
                $testString1,
                array($pos1, $pos2, 20),
                1,
                array('a', 'TestString', 'b')
            ),
            array(
                $testString1,
                array($pos2, $pos1),
                1,
                array('a', 'TestString', 'b')
            ),
        );
    }

}