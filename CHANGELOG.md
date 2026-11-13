# Changelog

## [Unreleased]

## [1.0.0] - 2026-11-13

The first stable release: it installs with Composer's default minimum-stability (stable).

### Added

- Omnilab Catalogue of Life: a taxon or synonym of the Catalogue of Life by its COL id, by the GBIF
  key that names it (GBIF's taxonomy is the COL eXtended Release) or by its name - authority, rank,
  status, classification, vernacular names, distribution, scrutinizer, source record; its GBIF key
  and the iNaturalist, WoRMS or ITIS records COL names as cross-references; parent, accepted name
  and synonyms related - and a search of names, through ChecklistBank's API, no key needed.

### Release

- Requires `omnisense/omnilab` `^1.0`, stable versions, where it required `@dev` ones:
  `composer require omnilab/col` installs in a project at Composer's default minimum-stability
  (stable). `extra.branch-alias` names the branch (`dev-1.x`: `1.x-dev`).
- CI: `.github/workflows/ci.yml` (glitchr-studio/ci's php-package.yml, pinned: the package
  installed alone, PHPUnit, PHPStan against its baseline, a consumer install) and `release.yml`
  (that CI on the tag, then glitchr-studio/ci's php-release.yml); `phpunit.xml.dist` runs the
  package's own tests.
- README: the install line and a first call.
