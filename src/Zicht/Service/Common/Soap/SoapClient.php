<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Soap;
use Zicht\Service\Common\CurlStreamWrapper;

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
     * The rewrite urls can be used to rewrite action URLs the SoapClient should operate. If the main wsdl imports
     * WSDL that refer to locations not reachable on the network, for example, those urls get rewritten when doing
     *
     * @param string $wsdl
     * @param array $options
     * @param array $rewriteUrls
     */
    public function __construct($wsdl, array $options = array(), array $rewriteUrls = array())
    {
        if ($rewriteUrls) {
            // this wrapper is needed for reading the WSDL from a rewritten url.
            CurlStreamWrapper::register($rewriteUrls);
        }

        parent::SoapClient(
            $wsdl,
            $options + array(
                'compression' =>SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
                'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
                'trace' => 0
            )
        );
        $this->rewriteUrls = $rewriteUrls;

        if ($rewriteUrls) {
            CurlStreamWrapper::unregister();
        }
    }


    /**
     * @{inheritDoc}
     */
    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {
        foreach ($this->rewriteUrls as $pattern => $replacement) {
            $location = preg_replace($pattern, $replacement, $location);
        }
        return parent::__doRequest($request, $location, $action, $version, $one_way);
    }
}