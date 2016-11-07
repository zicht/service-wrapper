<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */
namespace Zicht\Service\Common;

/**
 * Common logic for freezable objects
 */
trait FreezableTrait
{
    protected $isFrozen;

    /**
     * Freeze the object.
     *
     * @return void
     */
    public function freeze()
    {
        if ($this->isFrozen) {
            throw new \LogicException("You can not freeze an object that was already frozen");
        }
        $this->isFrozen = true;
    }


    /**
     * Make sure the object is not frozen, otherwise throw an exception
     *
     * @return void
     */
    public function assertNotFrozen()
    {
        if ($this->isFrozen) {
            throw new \LogicException(get_class($this) . ' is frozen, you can no longer alter it\'s state');
        }
    }
}
