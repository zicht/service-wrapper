<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Observers;

use Zicht\Service\Common\LoggerConstants;

/**
 * @deprecated Remove in next major version
 */
interface LoggerAwareInterface extends LoggerConstants
{
    /**
     * Logger implementation
     *
     * @param mixed $logger
     * @return mixed
     */
    public function setLogger($logger);
}
