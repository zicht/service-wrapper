<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */
namespace Zicht\Service\Common;

/**
 * Class NestedValueTrait.
 */
trait NestedValueTrait
{
    /**
     * Lookup a value from given path.
     *
     * @param array $path
     * @param object|array $value
     * @return mixed|null
     */
    public function getValueFromPath(array $path, $value)
    {
        $ptr = $value;
        foreach ($path as $key) {
            if (is_object($ptr) && isset($ptr->$key)) {
                $ptr = $ptr->$key;
            } elseif (is_array($ptr) && isset($ptr[$key])) {
                $ptr = $ptr[$key];
            } else {
                return null;
            }
        }
        return $ptr;
    }
}
