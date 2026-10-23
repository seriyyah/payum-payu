# Contributing

Thank you for considering a contribution.

## Before you start

- Open an issue for anything larger than a small fix so the approach can be agreed first.
- Bug reports need the PHP version, the Payum version, the request you executed and the PayU response (redact
  credentials and personal data).

## Branches

| Branch | Purpose | Composer constraint |
| --- | --- | --- |
| `main` | released code; every commit on it is tagged | `^1.0` (tags) or `dev-main` |
| `develop` | integration branch for the next release | `1.x-dev` (branch alias) or `dev-develop` |

Pull requests target **`develop`**. `main` only receives merges from `develop` when a release is cut.

## Pull requests

1. Fork the repository and create a branch from `develop` (for a hotfix to a released version, branch from `main`
   and say so in the PR).
2. Write the change together with its tests. New behaviour needs a test that fails without the change.
3. Run `composer ci` locally (or `docker compose run --rm php composer ci`). The pipeline enforces PSR-12 +
   Slevomat style, PHPStan level 10, 100% coverage and a 100% mutation score; a PR that lowers any of these needs
   a written reason in its description.
4. Add a line to `CHANGELOG.md` under *Unreleased*.
5. Open the PR against `develop`. Keep it focused; unrelated refactoring goes in its own PR.

## Versioning and releases

The package follows [Semantic Versioning](https://semver.org/):

| Change | Version bump | Example |
| --- | --- | --- |
| bug fix, dependency bump, docs | patch | 1.0.0 → 1.0.1 |
| new feature, new option, new request, deprecation | minor | 1.0.1 → 1.1.0 |
| removed or renamed public API, changed behaviour, raised PHP minimum | major | 1.1.0 → 2.0.0 |

Public API means everything under `src/` that is not `@internal`: class names, method signatures, gateway options,
detail keys and the HTTP replies of the notify action. Deprecate first (`@deprecated` plus a changelog entry) and remove
in the next major.

Release steps (maintainers):

1. Move the *Unreleased* changelog entries under the new version and date, update the `branch-alias` in
   `composer.json` when starting a new major line.
2. Merge `develop` into `main`, tag `vX.Y.Z` on `main`, push the tag. Packagist picks it up through the GitHub hook.
3. Merge `main` back into `develop`.

## Coding guidelines

- `declare(strict_types=1)`, imports instead of fully qualified names, `final` and `readonly` by default.
- Typed value objects instead of arrays crossing a boundary; enums instead of string constants.
- Package exceptions only, all implementing `PayumPayU\Exception\ExceptionInterface`, with messages that say what
  was wrong and what to do.
- No comments that narrate the code. A short docblock is fine for generics, array shapes or a non-obvious reason.
- The core stays framework-agnostic; framework glue belongs under `src/Bridge/`.

## Running the PayU sandbox

The test suite never calls PayU. To exercise a change against the sandbox, create a sandbox POS at
<https://registration-merch-prod.snd.payu.com/> and run your integration with `environment: sandbox`.
