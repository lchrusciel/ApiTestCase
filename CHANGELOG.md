# Changelog

## Changes from v5 to v5.1

#### TL;DR:

- Support for PHP 8.0 added and for PHP 7.2 dropped
- Support for PHP-Matcher 5 & 6 added, for PHP-Matcher 4 dropped
- Support for PHPUnit 7 dropped

### Added
- [#182](https://github.com/lchrusciel/ApiTestCase/issues/182) Support for PHP 8.0 added ([@lchrusciel](https://github.com/lchrusciel), [@emodric](https://github.com/emodric))

## Changes from v4 to v5

#### TL;DR:

- Support for Symfony 5, PHPUnit 8, PHPUnit 9
- Dropped support for SymfonyMockerContainer

### Added
- [#149](https://github.com/lchrusciel/ApiTestCase/issues/149) Added UUID matcher ([@MichaelKubovic](https://github.com/MichaelKubovic))
- [#175](https://github.com/lchrusciel/ApiTestCase/issues/175) [Maintenance] Support for PHPUnit 9 ([@lchrusciel](https://github.com/lchrusciel))

### Changed
- [#148](https://github.com/lchrusciel/ApiTestCase/issues/148) [Composer] After release changes ([@lchrusciel](https://github.com/lchrusciel))
- [#155](https://github.com/lchrusciel/ApiTestCase/issues/155) Fixing phpstan issues ([@mamazu](https://github.com/mamazu))
- [#156](https://github.com/lchrusciel/ApiTestCase/issues/156) Removing deprecation in the root dir ([@mamazu](https://github.com/mamazu), [@lchrusciel](https://github.com/lchrusciel))
- [#157](https://github.com/lchrusciel/ApiTestCase/issues/157) [Maintenance] Fix phpunit verbosity ([@lchrusciel](https://github.com/lchrusciel))
- [#137](https://github.com/lchrusciel/ApiTestCase/issues/137) [Maintenance] Fix CS ([@lchrusciel](https://github.com/lchrusciel))
- [#159](https://github.com/lchrusciel/ApiTestCase/issues/159) [Maintenance] Bump php version ([@lchrusciel](https://github.com/lchrusciel))
- [#170](https://github.com/lchrusciel/ApiTestCase/issues/170) Update dependencies(support for Symfony 5 & PHPunit 8) ([@angelov](https://github.com/angelov), [@loic425](https://github.com/loic425), [@lchrusciel](https://github.com/lchrusciel))
- [#171](https://github.com/lchrusciel/ApiTestCase/issues/171) [Maintenance] Dead code removal ([@lchrusciel](https://github.com/lchrusciel))
- [#172](https://github.com/lchrusciel/ApiTestCase/issues/172) [README] Add upgrade note ([@lchrusciel](https://github.com/lchrusciel))
- [#173](https://github.com/lchrusciel/ApiTestCase/issues/173) Remove mocked feature ([@lchrusciel](https://github.com/lchrusciel))
- [#174](https://github.com/lchrusciel/ApiTestCase/issues/174) [Maintenance] Bump PHPMatcher dependency ([@lchrusciel](https://github.com/lchrusciel))

### Fixed
- [#150](https://github.com/lchrusciel/ApiTestCase/issues/150) Call \Mockery::close() only if MockerContainer is present ([@angelov](https://github.com/angelov))
- [#152](https://github.com/lchrusciel/ApiTestCase/issues/152) Make ApiTestCase::tearDown protected ([@emodric](https://github.com/emodric))
- [#158](https://github.com/lchrusciel/ApiTestCase/issues/158) Fix wrong typehint in constructor ([@lchrusciel](https://github.com/lchrusciel))
