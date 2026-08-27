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
