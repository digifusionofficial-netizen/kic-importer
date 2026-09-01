# KIC Importer

Strict WordPress importer for the frozen `KIC-1.0` contract and target `wp-kadence-importer`.

Development is proceeding on `develop`. Import mutation remains intentionally unavailable until all mandatory preflight rules, the neutral `SiteSchema`, a tested Kadence adapter, and rollback-safe draft-first import are implemented.

Unknown WordPress/Kadence environments fail closed. The plugin never guesses Kadence block attributes.

## Current milestone

The repository currently contains the bootstrap, immutable contract identity and component taxonomy, structured validation primitives, a fail-closed compatibility gate, unit tests, and CI configuration.

Supported WordPress, PHP, and Kadence Blocks version ranges have not yet been approved. The current CI runtime is a development test environment, not a compatibility declaration.

## Development

```sh
composer install
composer test
```
