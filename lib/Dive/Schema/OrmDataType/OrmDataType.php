<?php
/*
 * This file is part of the Dive ORM framework.
 * (c) Steffen Zeidler <sigma_z@sigma-scripts.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Dive\Schema\OrmDataType;

use Dive\Expression;

/**
 * Class OrmDataType
 * @author  Steffen Zeidler <sigma_z@sigma-scripts.de>
 * @created 04.07.2014
 */
abstract class OrmDataType implements OrmDataTypeInterface
{

    /** @var string */
    protected $type;


    /**
     * @param string $type
     */
    public function __construct($type)
    {
        $this->type = $type;
    }


    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }


    /**
     * @param  mixed $value
     * @return bool
     */
    protected function canValueBeValidated($value)
    {
        return !($value instanceof Expression);
    }


    /**
     * Validates whether the value fits to the field length, or not
     * TODO: Implement validateLength() method.
     *
     * @param  mixed $value
     * @param  array $field
     * @return bool
     */
    public function validateLength($value, array $field)
    {
        return true;
    }

}
