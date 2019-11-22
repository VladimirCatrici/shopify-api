# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.2.0 - 2019-11-22
### Added
-   Client class added as a replacement of the API class 
-   ClientConfig added to configure new Shopify API client
-   Option to set up response data formatter. ResponseDefaultFormatter added and used by default
-   Declaring strict types

### Deprecated
-   API class deprecated, use Client instead

## 0.1.3 - 2019-11-19
### Added
-   RequestException->getRequest() method added

## 0.1.2 - 2019-11-18
### Fixed
-   RequestException->getDetailsJson() method now returns correct request details

## 0.1.1 - 2019-11-14
### Added
-   CHANGELOG.md

## 0.1.0 - 2019-11-04
### Added
-   Initial release
