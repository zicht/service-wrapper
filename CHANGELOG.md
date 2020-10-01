# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
### Added|Changed|Deprecated|Removed|Fixed|Security

## 3.2.4 - 2020-10-01
### Fixed
- Fixes naming confusion.  The variables are called '$...micoseconds' and hence their
  value should be in microseconds.

## 3.2.3 - 2020-09-30
### Fixed
- Fixes bug in `RedisLockingCacheObserver` where the busy-wait would wait 1000 times
  as short, resulting in 1000 times the number of Redis checks, causing a large CPU spike
  to occur whenever the lock could not be obtained.

  [usleep](https://www.php.net/manual/en/function.usleep.php) expects a value in microseconds,
  i.e. this means that 1,000,000 equals 1 seconds.  We were calling `usleep` with, on average,
  150 microseconds.  This should have been 150,000 microseconds.

  'Fun fact'... The `RedisLockingCacheObserver`, and this bug, was introduced over two years
  ago in 2018-06-26... and has been causing occasional problems ever since.

## 3.2.2 - 2020-07-17
### Fixed
- The `RedisLockingCacheObserver` would always unlock, even when the lock should have been
  maintained.  This fixes a bug introduced in version 3.1.2.
- To reduce CPU and traffic usage during busy-wait, the `RedisLockingCacheObserver` will has
  increased the default `$minLockSleepMicroSeconds` and `$maxLockSleepMicroSeconds` times.
- To reduce the maximum time a process will busy-wait, the `$minLockTTLSeconds` was reduced.
- Rename variables to clearly indicate whether their values are in seconds or microseconds.
- Add unit tests to verify the behavior of the `RedisLockingCacheObserver`.

## 3.2.1 - 2020-05-20
### Fixed
- This library is also compatible with Guzzle version 6.

## 3.2.0 - 2020-05-20
### Added
- The `ResponseInterface` now has a `isFrozen` method to check if `freeze` has been called.

## 3.1.2 - 2020-01-28
### Fixed
- Made sure the `RedisLockingCacheObserver` always unlocks to prevent unwanted side-effects.

## 3.1.1 - 2020-01-20
### Fixed
- CS lint cleanup.

## 3.1.0 - 2020-01-17
### Added
- `SoapClient::__call` provides a 3-attempts retry for
  'Could not connect to host' and 'Error Fetching http headers' errors.
- `reset` method in `DataCollector`.

## 3.0.4 - 2019-01-07
### Fixed
- Changes to the debug output strings to more easily `| grep CacheObserver`.

## 3.0.3 - 2018-12-07
### Fixed
- Update `composer.lock`.
- Update code to conform with `zicht/standards-php` 3.4.0.

## 3.0.2 - 2018-12-07
### Fixed
- Merged from 2.3.6.

## 3.0.1 - 2018-11-09
### Fixed
- Now using composer scripts for phpunit and linter
- Fixed several unit tests
- Removed test files from autoload

## 3.0.0 - 2018-06-26
### Added
- `RedisStorageFactory`: this can be loaded as a service that can provide one `\Redis`
  instance by calling `getClient` or multiple by calling `createClient`.
- Two simple caching observers: the `RedisCacheObserver` and `RedisLockingCacheObserver`.
  Either one can be used.  They support the all existing matchers but only support the
  Redis storage engine.  Support for other storage engines will require the implementation
  of their own specific observers.
### Changed
- Now only supports php 7.1
- `phpunit/phpunit` was updated
- The dependency on `zicht/util` was removed, it was only used for `Debug::dump`
- The caching component was made less abstract.  Before there was a Cache observer
  that could be configured to use any matchers and any storage engine, this
  was held together using the CacheAdapter.
### Removed
- `CacheAdapter`, `Observers/Cache`
- `FileStorage`, `MemoryStorage`, and `Storage`
- `RedisBase` and `RedisStorage`

## 2.3.4 - 2018-03-21
### Changed
- Removed the `final` flag from the __call method, allowing us to
  mock this method for unit-tests.

## 2.3.3 - 2017-10-31
### Fixed
- Disabled WSDL_CACHE_MEMORY and WSDL_CACHE_BOTH.

  We use only DISK cache.  Unfortunately there is a bug in the SoapClient
  that causes problems when WSDL_CACHE_MEMORY or WSDL_CACHE_BOTH are used,
  resulting in a segmentation fault, after exit, i.e. in a registered shutdown function.
  see: https://bugs.php.net/bug.php?id=71931

## 2.3.2 - 2017-10-12
### Fixed
- Cache keys, which use multiple attributes, will sort the attributes alphabetically to ensure
  that the resulting keys are the same, regardless of how the request was made

## 2.3.1 - 2017-09-19
### Fixed
- The Redis host can now be either a hostname, or a hostname:port combination

## 2.3.0 - 2017-05-09
### Added
- the ability to rewrite content while it is being downloaded,
  see the $rewriteContent parameter in the SoapClient constructor

## 2.2.0 - 2017-04-06
### Added
- `getAttributesDeep` on Request
- New trait for common code in `get[Attributes|Parameters|Properties]Deep` to ensure the same code is used

## 2.1.0 - 2016-12-28
### Added
- a base service for REST api's

## 2.0.2 - 2017-02-20
### Added
- a counter to the symfony toolbar summary, telling how many service calls were cancelled by the cache observer

## 2.0.0 - 2016-10-13 [Important bugfix release with breaking changes]
### Changed
- ServiceObserver was renamed to ServiceObserverInterface
- This release contains a fix related to caching which has a breaking change. See RELEASE-2.0.md for more information

The 2.0 release introduces two new methods as part of the ServiceObserverInterface: `alterRequest` and `alterResponse`. These may change request parameters and response contents.
In previous versions this was typically implemented in `notifyBefore` and `notifyAfter`.

To make sure that the logic is implemented in the correct methods, it is now forbidden to alter the request in the `notifyBefore` and alter the response in the `notifyAfter`. This way, the cache observer can be sure that it may write the cache within the `notifyAfter` and compose the cache key within the `notifyBefore`.

Usually it is safe to say that all `notifyBefore` can be replaced by `alterRequest` and all `notifyAfter` can be replaced by `alterResponse`, though _logic dictates_ that the alter-methods should only be used for actually altering the request or response respectively.

## 1.6.0 - 2016-08-12
### Added
- a factory for services, which can be used to lazy-initialize e.g. a SoapClient

## 1.5.0 - 2016-07-21
### Added
- a DataCollector to bridge with the Symfony profiler toolbar

## 1.4.0 - 2016-06-28
### Added
- an option to rewrite Soap urls coming from the wsdl's

## 1.3.0 - 2015-12-30
### Added
- a CacheKey class and CacheKeyInterface interface for generating readable cache keys

## 1.2.0 - 2015-11-02
### Added
- the unregisterObserver() method, which allows to remove an observer.
- the $index parameter to the registerObserver() method, to restore a previously removed observer at the original position.

## 1.1.0 - 2015-06-16
### Added
- a possibility to mark a response as uncachable

## 1.0.0
- First stable release based on observer logic from zicht/sro
