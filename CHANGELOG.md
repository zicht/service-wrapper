# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
- Nothing so far

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
