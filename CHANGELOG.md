# Changelog

All notable changes to `zola/crud-generator` are documented here.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-09-04

### Added
- `zola:make-crud` command that scaffolds the full stack (model, migration,
  repository, service, controller and Data classes), interactively or headless.
  It is transactional: a failure rolls back every file created during the run.
- `zola:make-controller` command that generates a controller plus the service,
  repository, model and Data classes it depends on.
- `zola:make-migration` command that generates a timestamped migration with one
  column per field.
- `zola:make-data` command that generates `Store{Model}Data` and
  `Update{Model}Data` (spatie/laravel-data) with validation attributes derived
  from each field's type and nullability.
- `--fields` support (`name:type[:nullable]`) that populates the model
  `$fillable`/`$casts`, migration columns and Data properties from one definition.
- Generated services now expose `list()`, `store()`, `update()` and `delete()`,
  and generated controllers expose the matching REST actions, wired through the
  repository and the Data classes.
- `spatie/laravel-data` (^4.0) as a runtime dependency.

### Fixed
- Controller was written with a double `.php` extension.
- Controller namespace now matches its directory (`Http\Controllers`).
- Laravel Modules mode: namespace/path resolution and recursive directory
  creation for nested `Modules/{module}/app/...` targets.
- Repository/service filename resolution (broken string interpolation) and
  case-insensitive suffix handling to avoid names like `ProductRepositoryRepository`.
- Existence checks no longer crash when autoloading fails (a missing vendor
  dependency or a stale optimized classmap); the class is treated as absent.

## [1.0.0]

### Added
- Initial release: `zola:make-model`, `zola:make-repository` and
  `zola:make-service`, backed by a shared `BaseRepository`.

[1.1.0]: https://github.com/Ilhammeru/crud-generator/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/Ilhammeru/crud-generator/releases/tag/v1.0.0
