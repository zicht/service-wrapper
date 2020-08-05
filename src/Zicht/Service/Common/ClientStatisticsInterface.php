<?php
/**
 * @copyright Zicht online <http://zicht.nl>
 */

namespace Zicht\Service\Common;

interface ClientStatisticsInterface
{
    /**
     * Returns an array with statistics that is stored with `$call->setInfo('ClientStatistics', ...)`
     *
     * @return array
     */
    // @codingStandardsIgnoreStart
    public function __getLastStatistics();
    // @codingStandardsIgnoreEnd
}
