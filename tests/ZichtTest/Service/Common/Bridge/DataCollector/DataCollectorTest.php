<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace ZichtTest\Service\Bridge\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Zicht\Service\Common\Bridge\DataCollector\DataCollector;
use Zicht\Service\Common\Bridge\DataCollector\Observer;

class DataCollectorTest extends TestCase
{
    public function testConstruct()
    {
        $observer = $this->getMockBuilder(Observer::class)->getMock();
        $observer->expects($this->never())->method('getCalls');
        $collector = new DataCollector($observer);

        $this->assertEquals('service', $collector->getName());
    }

    public function testCollectFetchesCallsFromObserver()
    {
        $observer = $this->getMockBuilder(Observer::class)->getMock();
        $collector = new DataCollector($observer);
        $observer->expects($this->once())->method('getCalls')->will($this->returnValue([]));
        $collector->collect($this->getMockBuilder(Request::class)->getMock(), $this->getMockBuilder(Response::class)->getMock(), null);
        $this->assertEquals(0, $collector->getCallCount());
    }

    public function testGetErrorCount()
    {
        $observer = $this->getMockBuilder(Observer::class)->getMock();
        $collector = new DataCollector($observer);
        $observer->expects($this->once())->method('getCalls')->will($this->returnValue(
            [
                ['is_error' => true],
                ['is_error' => false],
            ]
        ));
        $collector->collect($this->getMockBuilder(Request::class)->getMock(), $this->getMockBuilder(Response::class)->getMock(), null);
        $this->assertEquals(2, $collector->getCallCount());
        $this->assertEquals(1, $collector->getErrorCount());
    }

    public function testGetCallsCalculatesDeltas()
    {
        $observer = $this->getMockBuilder(Observer::class)->getMock();
        $collector = new DataCollector($observer);
        $observer->expects($this->once())->method('getCalls')->will(
            $this->returnValue(
                [
                    ['t_start' => 10, 't_end' => 11, 'mem_start' => 30, 'mem_end' => 50],   // delta t = 1, delta mem = 20
                    ['t_start' => 15, 't_end' => 17, 'mem_start' => 70, 'mem_end' => 120],  // delta t = 2, delta mem = 50
                ]
            )
        );

        $collector->collect($this->getMockBuilder(Request::class)->getMock(), $this->getMockBuilder(Response::class)->getMock(), null);

        $this->assertEquals(1, $collector->getCalls()[0]['t_delta']);
        $this->assertEquals(2, $collector->getCalls()[1]['t_delta']);
        $this->assertEquals(20, $collector->getCalls()[0]['mem_delta']);
        $this->assertEquals(50, $collector->getCalls()[1]['mem_delta']);
    }

    public function testGetTimeSpentCalculatesSumOfDeltasInSeconds()
    {
        $observer = $this->getMockBuilder(Observer::class)->getMock();
        $collector = new DataCollector($observer);
        $observer->expects($this->once())->method('getCalls')->will(
            $this->returnValue(
                [
                    ['t_start' => 10, 't_end' => 11],   // delta t = 1
                    ['t_start' => 15, 't_end' => 17],  // delta t = 2
                ]
            )
        );

        $collector->collect($this->getMockBuilder(Request::class)->getMock(), $this->getMockBuilder(Response::class)->getMock(), null);
        $this->assertEquals(3000.0, $collector->getTimeSpent());
    }

    public function testGetSummary()
    {
        $observer = $this->getMockBuilder(Observer::class)->getMock();
        $collector = new DataCollector($observer);
        $observer->expects($this->once())->method('getCalls')->will(
            $this->returnValue(
                [
                    ['t_start' => 10, 't_end' => 11, 'is_error' => true, 'is_cached' => false],  // delta t = 1
                    ['t_start' => 15, 't_end' => 17, 'is_error' => false, 'is_cached' => true],  // delta t = 2
                ]
            )
        );

        $collector->collect($this->getMockBuilder(Request::class)->getMock(), $this->getMockBuilder(Response::class)->getMock(), null);
        $this->assertEquals("2 call(s) in 3000 ms\n1 cached\n1 error(s)", $collector->getSummary());
    }


    public function get()
    {
        $observer = $this->getMockBuilder(Observer::class)->getMock();
        $collector = new DataCollector($observer);
        $observer->expects($this->once())->method('getCalls')->will(
            $this->returnValue(
                [
                    ['t_start' => 10, 't_end' => 11, 'mem_start' => 30, 'mem_end' => 50],   // delta t = 1, delta mem = 20
                    ['t_start' => 15, 't_end' => 17, 'mem_start' => 70, 'mem_end' => 120],  // delta t = 2, delta mem = 50
                ]
            )
        );

        $collector->collect($this->getMockBuilder(Request::class)->getMock(), $this->getMockBuilder(Response::class)->getMock(), null);

        $this->assertEquals(1, $collector->getCalls()[0]['t_delta']);
        $this->assertEquals(2, $collector->getCalls()[1]['t_delta']);
        $this->assertEquals(20, $collector->getCalls()[0]['mem_delta']);
        $this->assertEquals(50, $collector->getCalls()[1]['mem_delta']);
    }
}
