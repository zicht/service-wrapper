<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common;

/**
 * This interface provides a hint to use the service as a factory.
 *
 * This serves as a means of lazy loading services.
 *
 * @package Zicht\Service\Common
 */
interface ServiceFactoryInterface
{
    /**
     * Create the service
     *
     * @return object
     */
    public function createService();
}