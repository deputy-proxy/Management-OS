# Composer dependency-constraint decisions

This note records the decisions for the three constraints reported by `composer validate --no-check-publish`. The committed lockfile remains the reproducible package-version selection; this file explains the root constraints and why the two local path-package exceptions remain broad.

## Decision table

| Package | Root constraint | Verified package/lock metadata | Decision |
| --- | --- | --- | --- |
| `alizharb/filament-activity-log` | `*` | Resolved from the local path repository `./packages/alizharb/filament-activity-log`; its package manifest has no `version` field. The lockfile records `dev-ci/comprehensive-pipeline` and a local path reference. | **Keep the wildcard intentionally.** Composer derives a development version from the local checkout rather than a stable release tag. Pinning a branch-derived version would tie installation to a particular branch name; inventing a stable version would require an explicit release/versioning policy for the embedded package. The lockfile still records the path reference and dependency metadata. |
| `hexters/hexa-lite` | `*` | Resolved from the local path repository `./packages/hexters/hexa-lite`; its manifest has no `version` field and defines a `dev-main` to `2.x-dev` branch alias. The current lockfile resolves the local checkout as `dev-ci/comprehensive-pipeline`. | **Keep the wildcard intentionally.** The branch alias does not describe every development branch used for this local path package. A stable constraint requires the package itself to adopt a deliberate versioning/release strategy and a coordinated lockfile update. |
| `relaticle/comments` | `^1.1.1` | Versioned GitHub/Packagist package, locked at `v1.1.1` (source reference `45059e7ca523c995d36fafaba85f0c858c7b44fa`). Its published metadata declares PHP `^8.2`, Filament `^4.0|^5.0`, Illuminate `^12.0|^13.0`, and Livewire `^3.5|^4.0`. See [Packagist](https://packagist.org/packages/relaticle/comments) and [the locked source tag](https://github.com/relaticle/comments/tree/v1.1.1). | **Use the compatible semver range `^1.1.1`.** This retains the currently locked version while allowing future compatible 1.x releases. The lockfile remains the exact installation pin; no package version upgrade is intended by this constraint change. |

## Reproducibility and future changes

- `composer install --no-interaction --prefer-dist --no-progress` is the supported CI installation command and must continue to work with the committed lockfile.
- After changing the root constraint, refresh only the lockfile content hash with `composer update --lock --no-install --no-scripts` from a checkout where the local path-package versions match the lockfile version `dev-ci/comprehensive-pipeline`. For this repository, a temporary local branch with that name may be needed because the path packages derive their version from the current checkout. Then inspect `git diff -- composer.lock`; this issue must not update package versions, path references, or the transitive dependency graph.
- `composer validate --no-check-publish` may continue to warn about the two local path-package wildcards. Those warnings are intentional until the embedded packages have explicit version metadata and a compatible release policy. The exact-constraint warning for `relaticle/comments` should be gone.
- Do not pin the path packages to the current `dev-ci/comprehensive-pipeline` string: it is a checkout-derived development version, not a stable package contract.
- Do not run a general `composer update` for this issue. Any future package-version upgrade must be a separate, explicit change with a reviewed lockfile diff and full CI validation.
