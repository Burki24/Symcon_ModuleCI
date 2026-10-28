# Symcon_ModuleCI

`Symcon_ModuleCI` provides centrally maintained GitHub Actions building blocks for Burki24 Symcon module repositories.

The repository separates reusable CI logic from the modules themselves. Consumer repositories keep only a small workflow and a repository-specific test entry point. The required status checks are therefore identical everywhere:

- `tests`
- `style`

## Actions

### `php-tests`

The composite action initializes repository submodules, prepares PHP and optionally Python, then performs the shared base checks:

- recursive initialization of configured Git submodules
- PHP syntax validation for all repository PHP files
- JSON syntax validation for all repository JSON files
- read-only encoding validation of own project text files (UTF-8 and typical corrupted German characters)
- execution of one repository-specific test command

The default test command is:

```bash
php tests/run.php
```

Example consumer workflow:

```yaml
name: Tests

on:
  push:
    branches:
      - '**'
  pull_request:

permissions:
  contents: read

jobs:
  tests:
    name: tests
    runs-on: ubuntu-latest

    steps:
      - name: Check out repository
        uses: actions/checkout@v6

      - name: Run module tests
        uses: Burki24/Symcon_ModuleCI/php-tests@v1.0.0
        with:
          test-command: php tests/run.php
```

Available inputs:

| Input | Default | Purpose |
|---|---|---|
| `php-version` | `8.5` | PHP version for the test job |
| `php-extensions` | empty | Optional comma-separated PHP extensions |
| `setup-python` | `true` | Enables Python for local test scripts |
| `init-submodules` | `true` | Initializes configured Git submodules recursively |
| `python-version` | `3.13` | Python version when enabled |
| `lint-php` | `true` | Runs `php -l` for repository PHP files |
| `validate-json` | `true` | Decodes repository JSON files with strict error handling |
| `check-encoding` | `true` | Checks own text files; bundled upstream sources and dependencies are excluded |
| `test-command` | `php tests/run.php` | Repository-specific test entry point |

### `style`

The style action delegates directly to the official `symcon/action-style@v3` action. The consumer repository stores `symcon/StylePHP` as the `.style` Git submodule. The official action initializes that submodule and then runs the Symcon PHP and JSON style checks.

Example consumer workflow:

```yaml
name: Check Style

on:
  push:
    branches:
      - '**'
  pull_request:

permissions:
  contents: read

jobs:
  style:
    name: style
    runs-on: ubuntu-latest

    steps:
      - name: Check out repository
        uses: actions/checkout@v6

      - name: Run Symcon style check
        uses: Burki24/Symcon_ModuleCI/style@v1.0.0
```

### `style-fix`

The `style-fix` action applies the same official `symcon/StylePHP` configuration used by the read-only style check, but runs PHP CS Fixer without `--dry-run`. It therefore automatically fixes rules such as `binary_operator_spaces`, `single_quote`, `ordered_imports`, `method_argument_space`, `braces_position`, blank-line rules, and the remaining official Symcon PHP style rules. The official JSON fixer is applied as well.

By default, tracked changes are committed and pushed back to the currently checked out branch. Automatic pushes to `main` and `master` are blocked by default.

Example for a normal development branch:

```yaml
permissions:
  contents: write

steps:
  - name: Check out repository
    uses: actions/checkout@v6
    with:
      fetch-depth: 0
      ref: ${{ github.ref_name }}

  - name: Apply automatic Symcon style fixes
    uses: Burki24/Symcon_ModuleCI/style-fix@dev
```

Available inputs:

| Input | Default | Purpose |
|---|---|---|
| `commit` | `true` | Creates a commit when tracked files were changed |
| `push` | `true` | Pushes the generated commit to the checked out branch |
| `commit-message` | `STYLE: Apply automatic Symcon style fixes` | Commit subject |
| `commit-user-name` | `github-actions[bot]` | Commit author name |
| `commit-user-email` | GitHub Actions bot noreply address | Commit author email |
| `blocked-branches` | `main master` | Branches that the action must never push automatically |

Outputs:

| Output | Purpose |
|---|---|
| `changed` | `true` when tracked files were modified |
| `committed` | `true` when an automatic style commit was created |
| `commit-sha` | SHA of the generated style commit |

For repositories that already have another workflow responsible for the final push, use `push: 'false'`. The style fixes are then committed locally and can be tested before the existing workflow pushes all commits together. This prevents competing automation workflows from racing each other.

A checkout performed with the normal `GITHUB_TOKEN` can push changes, but that push normally does not start another push-triggered workflow. If follow-up workflows must run, check out with a GitHub App token or another suitable token that is allowed to trigger them.

### `library-metadata`

Generates `version`, `build`, and `date` in the consumer's `library.json` using
the existing OpenHomeAlarm/SymconECharts policy. All other fields are preserved.
It does not commit, push, tag, publish, or access any module runtime.

- `version`: keep the major number and increment the minor number once per
  source commit in the push. Commits starting with
  `CHORE: Update library metadata` do not count. A higher current version is
  never lowered.
- `build`: the first seven hexadecimal digits of the source SHA as an integer.
- `date`: the source commit's Unix timestamp.
- An unavailable/zero `before-sha` falls back to one increment. A repeated run
  for the same source and push range preserves the generated metadata.
- A source already superseded by another source commit is skipped. Later
  metadata-only commits are allowed, so rerunning a completed job is safe.

Use a full-history checkout of the development branch and run the consumer's
tests first. Pass the push event's full `github.sha` and `github.event.before`:

```yaml
- name: Generate library metadata
  id: metadata
  uses: Burki24/Symcon_ModuleCI/library-metadata@dev
  with:
    source-sha: ${{ github.sha }}
    before-sha: ${{ github.event.before }}
```

This new action is initially tested with `@dev`; switch to the next published
ModuleCI release tag after verification. Existing actions remain on their
current release tag.

Outputs: `changed` (`true`/`false`), `version`, `short-sha`, and `skipped`
(empty, `metadata-only`, or `superseded`). Only publish when `changed` is true.
The caller owns authentication and the final commit, limited to `library.json`.
It must skip metadata-only pushes to prevent a loop and must never force-push.
A normal non-fast-forward rejection protects newer branch changes; rerun the
latest source workflow if the branch advanced during validation.

When the generated commit must start follow-up tests/style workflows, use an
installed GitHub App with Contents write access for checkout/push, rather than
the default `GITHUB_TOKEN`. SymconiCloud uses the existing repository variable
`HELPER_SYNC_APP_CLIENT_ID` and secret `HELPER_SYNC_APP_PRIVATE_KEY`. Never store
the private key in a workflow or the repository.

Local validation requires PHP and Python 3.10 or newer:

```bash
php tests/run.php
```

To inspect a consumer's proposed metadata without changing its files:

```bash
python library-metadata/update_library_metadata.py --root /path/to/consumer --source-sha FULL_SOURCE_SHA --before-sha FULL_BEFORE_SHA --dry-run
```

## Official Symcon submodules

Repositories using this CI foundation should reference the official Symcon sources as Git submodules:

```ini
[submodule ".style"]
    path = .style
    url = https://github.com/symcon/StylePHP
[submodule "tests/stubs"]
    path = tests/stubs
    url = https://github.com/symcon/SymconStubs
```

The `php-tests`, `style`, and `style-fix` actions intentionally do not maintain their own Symcon style rules. They rely on the official `symcon/StylePHP` configuration stored in the consumer repository's `.style` submodule.

## Versioning and branches

- `dev` is used to develop and test changes.
- `main` contains the stable released state.
- Consumer repositories use a release tag such as `v1.0.0`, not `main` or `dev`.

New action versions should first be tested from a consumer development branch with `@dev`. After successful verification, publish a new release tag and switch consumers to that tag.

## Repository-specific tests

The central action intentionally does not know the internal test files of a module. Each consumer provides a single stable entry point at `tests/run.php`. That file can call PHP, Python, shell, or Node.js tests required by the module.

This preserves module-specific coverage while keeping workflow names, setup, linting, JSON validation, and Ruleset checks uniform.

## Shared text encoding check

The canonical implementation is `php-tests/scripts/check-encoding.php`. The
`php-tests` action runs it before repository tests with `check-encoding: 'true'`
by default. This addition is developed on `@dev`; an existing `v1.0.0` consumer
will not gain it until it deliberately adopts this development version or a
future release. No existing tag is changed.

Run the same check locally against an explicit repository:

```bash
php /path/to/Symcon_ModuleCI/php-tests/scripts/check-encoding.php /path/to/library
```

It reports relative file paths and line numbers and exits with 1 on findings.
It checks invalid UTF-8, Unicode replacement characters and known single/double
Windows-1252 or Latin-1 corruption patterns of German characters. This is a
focused heuristic, not a general language validator. Intentional malformed test
inputs must use Unicode or byte escapes. It never rewrites files.

Own PHP, Markdown, JSON, JavaScript/TypeScript, YAML, HTML, CSS, SVG and text
files are checked, together with LICENSE and common repository configuration
files. `.git`, `.style`, `tests/stubs`, `libs/helper`, `libs/vendor`, `vendor`,
`node_modules` and local preview/IDE folders are excluded. Symbolic links are
not followed. Other binary types and `.env` files are not read.

For consumer-local test commands, the composite action exports
`SYMCON_MODULE_CI_ROOT` pointing to this action checkout. A consumer can use
that variable to load the canonical script; locally it may use an adjacent
`Symcon_ModuleCI` checkout. Fail clearly when the shared source is missing;
do not silently skip or download code during tests. Keep the implementation
and its regression suite here rather than copying them into each library.

Z2MAutomations is the initial consumer and references `php-tests@dev` for this
integration. Push the central implementation before the consumer workflow.
Publishing a release or changing other libraries is a separate task.
