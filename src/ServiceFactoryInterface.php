<?php declare(strict_types=1);
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common;

/**
 * This interface provides a hint to use the service as a factory.
 *
 * This serves as a means of lazy loading services.
 */
interface ServiceFactoryInterface
{
    /**
     * @return object
     */
    public function createService();
}
