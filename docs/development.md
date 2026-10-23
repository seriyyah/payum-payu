# Development

Everything runs with Composer scripts; the Docker image only adds Xdebug for coverage and Infection.

```bash
composer install
composer check        # phpcs, phpstan, phpunit, composer-require-checker, composer-unused, composer normalize
composer ci           # check + coverage gate + infection + rector dry-run
```

| Script | What it runs |
| --- | --- |
| `composer cs` / `cs:fix` | PHP_CodeSniffer with PSR-12 and a curated Slevomat ruleset (`phpcs.xml.dist`), `phpcbf` fixes |
| `composer stan` | PHPStan level 10 with strict, deprecation and PHPUnit rules; Payum types come from `stubs/` |
| `composer test` | PHPUnit, randomised order, fails on warnings, notices and deprecations |
| `composer coverage` | PHPUnit with Clover/HTML reports, then `tools/coverage-check.php` fails below 100% of statements and methods |
| `composer mutation` | Infection, minimum MSI 100 |
| `composer deps` | composer-require-checker (undeclared dependencies) and composer-unused |
| `composer normalize` | composer.json formatting check |
| `composer rector` / `rector:fix` | Rector dry-run with PHP 8.2 and code quality sets |

## Docker

```bash
docker compose build php
docker compose run --rm php composer ci
docker compose run --rm php composer coverage
```

The container is PHP 8.4 CLI with Xdebug in coverage mode and Composer 2. Mount is the project root.

## SonarQube

```bash
docker compose --profile sonar up -d sonarqube      # http://localhost:9000, admin/admin on first start
docker compose run --rm php composer coverage         # produces build/coverage/clover.xml and build/junit.xml
SONAR_TOKEN=... docker compose --profile sonar run --rm sonar-scanner
```

`sonar-project.properties` points Sonar at the reports.

## Test layout

- `tests/<Namespace>/...Test.php` mirrors `src/`.
- `tests/Gateway/` builds a real Payum gateway from `PayUGatewayFactory` with a recording PSR-18 client and runs the
  Payum requests end to end; these tests are the behavioural contract.
- `tests/Double/` holds the test doubles (recording HTTP client, frozen clock, header provider, token factory).
- Exceptions are asserted with `TestCase::assertThrows()`; it works on PHPUnit 11, 12 and 13 alike.

## Branches and releases

Work lands on `dev`; `main` holds released, tagged code. The release checklist and the semantic versioning rules
are in [CONTRIBUTING.md](../CONTRIBUTING.md).
