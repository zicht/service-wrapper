# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased] 2.1.x
### Added
- `getAttributesDeep` on Request
- New trait for common code in `get[Attributes|Parameters|Properties]Deep` to ensure the same code is used

## 2.1.0
- Add a base service for REST api's

## 2.0.2
- Adds a counter to the symfony toolbar summary, telling how many service calls were cancelled by the cache observer

## 2.0.0
### Important bugfix release with breaking changes
- ServiceObserver was renamed to ServiceObserverInterface
- This release contains a fix related to caching which has a breaking change. See RELEASE-2.0.md for more information

## 1.6.0
- Added a factory for services, which can be used to lazy-initialize e.g. a SoapClient

## 1.5.0
- Add a DataCollector to bridge with the Symfony profiler toolbar

## 1.4.0
- Add an option to rewrite Soap urls coming from the wsdl's

## 1.3.0
- Add a CacheKey class and CacheKeyInterface interface for generating readable cache keys

## 1.2.0
- Added the unregisterObserver() method, which allows to remove an observer.
- Added the $index parameter to the registerObserver() method, to restore a previously removed observer at the original position.

## 1.1.0
- Added a possibility to mark a response as uncachable

## 1.0.0
- First stable release based on observer logic from zicht/sro