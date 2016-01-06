<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Soap;

/**
 * Base class for SOAP implementations
 *
 * @package Zicht\Service\Common\Soap
 */
class SoapClient extends \SoapClient
{
    /**
     * Constructor override, adds some sane defaults
     *
     * @param string $wsdl
     * @param array $options
     */
    public function __construct($wsdl, array $options = array())
    {
        parent::SoapClient(
            $wsdl,
            $options + array(
                'compression' => SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_ACCEPT | 9,
                'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
                'trace' => 1
            )
        );
    }
}