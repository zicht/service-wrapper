<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common;

interface LoggerConstants
{
    /** Debug log level */
    const DEBUG = 100;

    /** Info log level */
    const INFO = 200;

    /** Notice log level */
    const NOTICE = 250;

    /** Warning log level */
    const WARNING = 300;

    /** Error log level */
    const ERROR = 400;

    /** Critical log level */
    const CRITICAL = 500;

    /** Alert log level */
    const ALERT = 550;

    /** Emergency log level */
    const EMERGENCY = 600;
}
