<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Web;

/**
 * SessionIterator implements an [[\Iterator|iterator]] for traversing session variables managed by [[Session]].
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class SessionIterator implements \Iterator
{
    /**
     * @var array list of keys in the map
     */
    private $keys;
    /**
     * @var mixed current key
     */
    private $key;


    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->keys = array_keys($_SESSION);
    }

    /**
     * Rewinds internal array pointer.
     * This method is required by the interface [[\Iterator]].
     */
    public function rewind()
    {
        $this->key = reset($this->keys);
    }

    /**
     * Returns the key of the current array element.
     * This method is required by the interface [[\Iterator]].
     * @return mixed the key of the current array element
     */
    public function key()
    {
        return $this->key;
    }

    /**
     * Returns the current array element.
     * This method is required by the interface [[\Iterator]].
     * @return mixed the current array element
     */
    public function current()
    {
        return isset($_SESSION[$this->key]) ? $_SESSION[$this->key] : null;
    }

    /**
     * Moves the internal pointer to the next array element.
     * This method is required by the interface [[\Iterator]].
     */
    public function next()
    {
        do {
            $this->key = next($this->keys);
        } while (!isset($_SESSION[$this->key]) && $this->key !== false);
    }

    /**
     * Returns whether there is an element at current position.
     * This method is required by the interface [[\Iterator]].
     * @return bool
     */
    public function valid()
    {
        return $this->key !== false;
    }
}
