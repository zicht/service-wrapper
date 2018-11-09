# Zicht service wrapper #

Provides a wrapper to easily allow for an aspect-oriented approach of influencing response 
and requests to the service.

## Scripts
- unit test: `composer test`
- lint test: `composer lint`

## General approach ##

All calls to the service are wrapped in a call that notifies all _observers_ of the call. 
The observers get a change to do their own housekeeping, or even alter the request or the 
response.

Any observer must implement the `notifyBefore`, `alterRequest`, `alterResponse` and 
`notifyAfter` methods. Each observer will get an instance of a `ServiceCall` object, which 
contains the request and the response objects. This more or less works the same as an event
loop, but is intentionally not implemented as such to avoid the overhead of having a
dispatcher and listener structure in place.

## Common observers ##
For two very common practices, a logger and a cache observer are available.

## Example ##

```
class MyService
{
    public function doIt($name)
    {
        return sprintf("Hi, %s, you have %d marbles in your pocket!", $name, rand());
    }
}

class MyObserver implements ServiceObserverInterface
{
    public function notifyBefore(ServiceCallInterface $call) {}
    public function notifyAfter(ServiceCallInterface $call) {}
    public function alterRequest(ServiceCallInterface $call) {}
    public function alterResponse(ServiceCallInterface $call) 
    {
        $call->setResponse(strrev($call->getResponse()->getResponse()));
    }
}

$wrapper = new ServiceWrapper(new MyService());
$wrapper->registerObserver(new MyObserver());

echo $wrapper->doIt("Bart");
echo $wrapper->doIt("Lisa");
```

See [doc/example.php](doc/example.php) for a more thorough example.

## Applications ##

You can implement observer that:

- Add more data to responses, i.e. to enrich data
- Add basic logging
- Extensively monitor requests and responses with your own observers
- Add sanity checks and such that are costly in development, but can now easily be isolated in one object.
- Add caching

# Maintainer
* Boudewijn Schoon <boudewijn@zicht.nl> 
