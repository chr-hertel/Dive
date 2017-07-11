<?php
/*
 * This file is part of the Dive ORM framework.
 * (c) Steffen Zeidler <sigma_z@sigma-scripts.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Dive\Collection;

/**
 * @author Steffen Zeidler <sigma_z@sigma-scripts.de>
 * Date: 11.02.13
 */
class Collection implements CollectionInterface
{

    /**
     * array with integer-indexes
     * @var \Dive\Record[]|array
     */
    protected $items = [];


    /**
     * @param array|\Dive\Record[] $items keys are the primary keys of the items, values can be array or objects
     */
    public function setItems(array $items)
    {
        $this->items = $items;
    }


    /**
     * @return array|\Dive\Record[]
     */
    public function getItems()
    {
        return $this->items;
    }


    /**
     * check, if item does exist for the given $id
     *
     * @param  string $key
     * @return bool
     */
    public function has($key)
    {
        return $this->offsetExists($key);
    }


    /**
     * gets item for the given $id
     *
     * @param  string $key
     * @return mixed
     */
    public function get($key)
    {
        return $this->offsetGet($key);
    }


    /**
     * removes item for the given $id
     *
     * @param  string $key
     * @return bool
     */
    public function remove($key)
    {
        if ($this->has($key)) {
            $this->offsetUnset($key);
            return true;
        }
        return false;
    }


    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean Returns true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->items);
    }


    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     */
    public function offsetGet($offset)
    {
        return $this->offsetExists($offset) ? $this->items[$offset] : null;
    }


    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        if ($offset !== null) {
            $this->items[$offset] = $value;
        }
        else {
            $this->items[] = $value;
        }
    }


    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->items[$offset]);
    }


    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     */
    public function count()
    {
        return count($this->items);
    }


    /**
     * Checks if the collection is empty
     *
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->items);
    }


    /**
     * @param array|\Dive\Record $item
     * @param int $key
     * @return Collection
     */
    public function add($item, $key = null)
    {
        $this->offsetSet($key, $item);
        return $this;
    }


    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Retrieve an external iterator
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->items);
    }


    /**
     * Gets keys of collection items
     *
     * @return array
     */
    public function keys()
    {
        return array_keys($this->items);
    }


    /**
     * @return mixed
     */
    public function first()
    {
        return reset($this->items);
    }


    /**
     * @return mixed
     */
    public function last()
    {
        return end($this->items);
    }


    /**
     * @return int|string
     */
    public function key()
    {
        return key($this->items);
    }


    /**
     * @return mixed
     */
    public function next()
    {
        return next($this->items);
    }

}
