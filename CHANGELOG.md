# 1.0.0 #
- First stable release based on observer logic from zicht/sro

# 1.1.0 #
- Added a possibility to mark a response as uncachable 

# 1.2.0 #
- Added the unregisterObserver() method, which allows to remove an observer.
- Added the $index parameter to the registerObserver() method, to restore a previously removed observer at the original position.

# 1.3.0 #
- Add a CacheKey class and CacheKeyInterface interface for generating readable cache keys

# 1.4.0 #
- Add an option to rewrite Soap urls coming from the wsdl's

# 1.5.0 #
- Add a DataCollector to bridge with the Symfony profiler toolbar

# 1.6.0 #
- Added a factory for services, which can be used to lazy-initialize e.g. a SoapClient

# 2.0.0 - Important bugfix release with breaking changes #
- This release contains a fix related to caching which has a breaking change. If
  you implement ServiceObserver, please replace that by extending ServiceObserverAdapter
  or by implementing the `alterRequest()` and `alterResponse()` methods. If the `notifyBefore()`
  changed the request, replace it with `alterRequest()`. If the `notifyAfter()` changed the
  response, replace it with `alterResponse()`.

  This fixes an important ordering logic bug, which causes caches to either not work or not be
  populated with the correct data. 
