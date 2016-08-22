<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace ZichtTest\Service\Common;

use Zicht\Service\Common\CurlStreamWrapper;
use Zicht\Service\Common\Soap\SoapClient;

/**
 * @covers Zicht\Service\Common\CurlStreamWrapper
 * @covers Zicht\Service\Common\Soap\SoapClient
 * @group integration
 */
class CurlStreamWrapperTest extends \PHPUnit_Framework_TestCase
{
    public function testSoapIntegration()
    {
        // this wsdl comes from http://quicksoftwaretesting.com/sample-wsdl-urls-testing-soapui/

        // we're making use of the fact that the windows-hosted service is case insensitive for testing this
        // without actually leading to errors.

        ini_set('soap.wsdl_cache', 0);
        $client = new SoapClient(
            'http://www.webservicex.com/globalweather.asmx?wsdl',
            [],
            ['!http://www.webservicex.com/(.*)!' => 'http://WWW.WEBSERVICEX.COM/$1']
        );

        $client->GetCitiesByCountry(['CountryName' => "Netherlands"]);
    }


    public function testWrapping()
    {
        $file = tempnam('/tmp', preg_replace('/\W/', '_', __CLASS__));
        file_put_contents($file, 'http://www.example.org/');

        CurlStreamWrapper::register(['!foo://example.org!' => 'http://example.org'], ['foo']);

        file_get_contents('foo://example.org');

        CurlStreamWrapper::unregister();
    }
}