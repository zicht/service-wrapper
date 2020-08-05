<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Soap;

use Zicht\Service\Common\ClientStatisticsInterface;
use Zicht\Service\Common\CurlStreamWrapper;

/**
 * Base class for SOAP implementations
 *
 */
class SoapClient extends \SoapClient implements ClientStatisticsInterface
{
    /** @var array */
    private $rewriteUrls;

    /** @var array */
    private $rewriteContent;

    /** @var array */
    private $lastStatistics;

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
        $this->lastStatistics = ['requestSize' => 0, 'responseSize' => 0];

        try {
            parent::SoapClient($wsdl, $options + $this->getDefaultOptions());
        } finally {
            if ($needRewrite) {
                CurlStreamWrapper::unregister();
            }
        }
    }

    /**
     * @return array
     */
    protected function getDefaultOptions()
    {
        return [
            'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
            'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
            'trace' => 0,
            // We use only DISK cache.  Unfortunately there is a bug in the SoapClient
            // that causes problems when WSDL_CACHE_MEMORY or WSDL_CACHE_BOTH are used,
            // resulting in a segmentation fault, after exit, i.e. in a registered shutdown function.
            // see: https://bugs.php.net/bug.php?id=71931
            'cache_wsdl' => WSDL_CACHE_DISK,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function __call($function_name, $arguments)
    {
        $retry = 3;
        while ($retry > 0) {
            $retry -= 1;
            try {
                return parent::__call($function_name, $arguments);
            } catch (\SoapFault $soapFault) {
                $message = $soapFault->getMessage();
                if ($message === 'Could not connect to host' || $message === 'Error Fetching http headers') {
                    // Retry until $retry limit is reached
                    continue;
                }

                throw $soapFault;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    // @codingStandardsIgnoreStart
    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {
        // @codingStandardsIgnoreEnd
        foreach ($this->rewriteUrls as $pattern => $replacement) {
            $location = preg_replace($pattern, $replacement, $location);
        }
        $response = parent::__doRequest($request, $location, $action, $version, $one_way);
        $this->lastStatistics['requestSize'] = strlen($request);
        $this->lastStatistics['responseSize'] = strlen($response);
        return $response;
    }

    /**
     * {@inheritdoc}
     */
    // @codingStandardsIgnoreStart
    public function __getLastStatistics()
    {
        // @codingStandardsIgnoreEnd
        return $this->lastStatistics;
    }
}
