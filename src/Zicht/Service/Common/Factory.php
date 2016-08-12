<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */
namespace Zicht\Service\Common;

/**
 * Simple service factory calling a constructor with arguments upon initialization
 */
final class Factory implements ServiceFactoryInterface
{
    /**
     * Construct the factory with the passed classname and args as the instance definition.
     *
     * @param string $className
     * @param mixed[] $args
     */
    public function __construct($className, array $args = [])
    {
        $this->className = $className;
        $this->ctorArgs = $args;
    }

    /**
     * Creates the service.
     *
     * @return object
     */
    public function createService()
    {
        return (new \ReflectionClass($this->className))->newInstance(...$this->ctorArgs);
    }
}
