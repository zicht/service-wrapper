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
    /** @var array */
    private $rewriteUrls;

    /** @var array */
    private $rewriteContent;

    /**
     * Constructor override, adds some sane defaults
     *
     * The rewrite urls can be used to rewrite action URLs the SoapClient should operate. If the main wsdl imports
     * WSDL that refer to locations not reachable on the network, for example, those urls get rewritten when doing
     *
     * $rewriteUrls = [
     *     '#https?://193.0.23.17/#' => 'http://37.17.212.175/',
     * ]
     *
     * The rewrite content can be used to rewrite the WSDL content itself.  This can be used to, for example, rename
     * services when they use duplicate names (which is not supported by the PHP soap client)
     *
     * $rewriteContent = [
     *     [
     *         'file_pattern' => '#get.resource/wsdl/All/wsdl1#',
     *         'pattern' => '#FacadeService#',
     *         'replacement' => 'AlternativeFacadeService',
     *     ]
     * ]
     *
     * @param string $wsdl
     * @param array $options
     * @param array $rewriteUrls
     * @param array $rewriteContent
     */
    public function __construct($wsdl, array $options = [], array $rewriteUrls = [], array $rewriteContent = [])
    {
        $needRewrite = $rewriteUrls || $rewriteContent;
        if ($needRewrite) {
            // this wrapper is needed for reading the WSDL from a rewritten url.
            CurlStreamWrapper::register($rewriteUrls, $rewriteContent);
        }

        $this->rewriteUrls = $rewriteUrls;
        $this->rewriteContent = $rewriteContent;

        try {
            parent::SoapClient(
                $wsdl,
                array_merge(
                    $options,
                    [
                        'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
                        'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
                        'trace' => 0,
                    ]
                )
            );
        } finally {
            if ($needRewrite) {
                CurlStreamWrapper::unregister();
            }
        }
    }


    /**
     * {@inheritDoc}
     */
    // @codingStandardsIgnoreStart
    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {
        // @codingStandardsIgnoreEnd
        foreach ($this->rewriteUrls as $pattern => $replacement) {
            $location = preg_replace($pattern, $replacement, $location);
        }
        return parent::__doRequest($request, $location, $action, $version, $one_way);
    }
}
