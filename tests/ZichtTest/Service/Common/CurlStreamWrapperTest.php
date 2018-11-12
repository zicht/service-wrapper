<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace ZichtTest\Service\Common;

use PHPUnit\Framework\TestCase;
use Zicht\Service\Common\CurlStreamWrapper;
use Zicht\Service\Common\Soap\SoapClient;

/**
 * Class CurlStreamWrapperTest
 *
 * @covers \Zicht\Service\Common\CurlStreamWrapper
 * @covers \Zicht\Service\Common\Soap\SoapClient
 * @group integration
 * @package ZichtTest\Service\Common
 */
class CurlStreamWrapperTest extends TestCase
{
    /**
     * Test soap integration
     * @skip
     */
    public function testSoapIntegration()
    {
        // this wsdl comes from http://quicksoftwaretesting.com/sample-wsdl-urls-testing-soapui/
        $this->markTestSkipped('Service with sample wsdl is no longer available');

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

    /**
     * Test the url rewrite functionality
     */
    public function testWrappingWithUrlRewrites()
    {
        // prepare a file for this test
        $filename = tempnam('/tmp', 'unit-test');
        file_put_contents($filename, 'This is the content you are looking for');

        // configure the wrapper to (1) redirect to the file on disk,
        // and (2) replace parts of the file content
        $urlRewrites = [
            '#foo://this-is-redirected-to-a-file#' => sprintf('file://%s', $filename),
        ];
        CurlStreamWrapper::register($urlRewrites, [], ['foo']);

        // check that the file contents has been modified as configured
        $data = file_get_contents('foo://this-is-redirected-to-a-file');
        $this->assertEquals('This is the content you are looking for', $data);

        // cleanup
        CurlStreamWrapper::unregister();
    }

    /**
     * Test the url and content rewrite functionality
     */
    public function testWrappingWithContentRewrites()
    {
        // prepare a file for this test
        $filename = tempnam('/tmp', 'unit-test');
        file_put_contents($filename, 'This is not the content you are looking for');

        // configure the wrapper to (1) redirect to the file on disk,
        // and (2) replace parts of the file content
        $urlRewrites = [
            '#foo://this-is-redirected-to-a-file#' => sprintf('file://%s', $filename),
        ];
        $contentRewrites = [
            [
                'file_pattern' => sprintf('#%s#', $filename),
                'pattern' => '#is not#',
                'replacement' => 'IS',
            ],
            [
                'file_pattern' => sprintf('#%s#', $filename),
                'pattern' => '#looking#',
                'replacement' => 'SEARCHING',
            ],
        ];
        CurlStreamWrapper::register($urlRewrites, $contentRewrites, ['foo']);

        // check that the file contents has been modified as configured
        $data = file_get_contents('foo://this-is-redirected-to-a-file');
        $this->assertEquals('This IS the content you are SEARCHING for', $data);

        // cleanup
        CurlStreamWrapper::unregister();
    }
}
