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

The `php-tests` action initializes configured submodules recursively before linting and repository-specific tests. The `style` action intentionally does not maintain its own style rules and relies exclusively on `symcon/action-style@v3`.

## Versioning and branches

- `dev` is used to develop and test changes.
- `main` contains the stable released state.
- Consumer repositories use a release tag such as `v1.0.0`, not `main` or `dev`.

Before the first release, the actions can be tested temporarily from a consumer feature branch with `@dev`.

## Repository-specific tests

The central action intentionally does not know the internal test files of a module. Each consumer provides a single stable entry point at `tests/run.php`. That file can call PHP, Python, shell, or Node.js tests required by the module.

This preserves module-specific coverage while keeping workflow names, setup, linting, JSON validation, and Ruleset checks uniform.
