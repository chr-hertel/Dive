<?php
/*
 * This file is part of the Dive ORM framework.
 * (c) Steffen Zeidler <sigma_z@sigma-scripts.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Dive\Schema\OrmDataType;

/**
 * Class BooleanOrmDataType
 * @author  Steffen Zeidler <sigma_z@sigma-scripts.de>
 * @created 04.07.2014
 */
class BooleanOrmDataType extends OrmDataType
{

    /**
     * Validates whether the value matches the field type, or not
     *
     * @param  mixed $value
     * @param  array $field
     * @return bool
     */
    public function validateType($value, array $field)
    {
        if ($value === '1' || $value === '0') {
            return true;
        }
        if (is_bool($value)) {
            return true;
        }
        if ($value === 1 || $value === 0) {
            return true;
        }

        return false;
    }


    /**
     * Validates whether the value fits to the field length, or not
     *
     * @param  mixed $value
     * @param  array $field
     * @return bool
     */
    public function validateLength($value, array $field)
    {
        return $value !== '';
    }

}
