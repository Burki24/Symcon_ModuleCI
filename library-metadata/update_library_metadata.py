#!/usr/bin/env python3
"""Central implementation of the existing per-source-commit library version policy."""

from __future__ import annotations

import argparse
import json
import os
from pathlib import Path
import re
import subprocess


METADATA_PREFIX = 'CHORE: Update library metadata'
SHA_PATTERN = re.compile(r'^[0-9a-fA-F]{40}$')
VERSION_PATTERN = re.compile(r'^(\d+)\.(\d+)$')


def version_tuple(value: object) -> tuple[int, int]:
    match = VERSION_PATTERN.fullmatch(value) if isinstance(value, str) else None
    if match is None:
        raise SystemExit('Library version must use major.minor format.')
    return int(match[1]), int(match[2])


def git(root: Path, *arguments: str, optional: bool = False) -> str | None:
    result = subprocess.run(
        ['git', '-C', str(root), *arguments],
        capture_output=True, text=True, encoding='utf-8', check=False,
    )
    if result.returncode:
        if optional:
            return None
        raise SystemExit(f'Git metadata lookup failed: {arguments[0]}.')
    return result.stdout.strip()


def plan(root: Path, library: dict, source: str, before: str) -> tuple[dict, str]:
    if SHA_PATTERN.fullmatch(source) is None or (before and SHA_PATTERN.fullmatch(before) is None):
        raise SystemExit('Source and before references must be complete Git commit SHAs.')
    version_tuple(library.get('version'))
    subject = git(root, 'show', '-s', '--format=%s', source)
    if subject.startswith(METADATA_PREFIX):
        return library.copy(), 'metadata-only'
    head = git(root, 'rev-parse', 'HEAD')
    if head != source:
        ancestor = git(root, 'merge-base', '--is-ancestor', source, head, optional=True)
        later = git(root, 'log', '--format=%s', f'{source}..{head}').splitlines() if ancestor is not None else []
        if ancestor is None or any(not item.startswith(METADATA_PREFIX) for item in later):
            return library.copy(), 'superseded'

    valid_before = before and set(before) != {'0'} and git(root, 'cat-file', '-e', f'{before}^{{commit}}', optional=True) is not None
    if valid_before:
        valid_before = git(root, 'merge-base', '--is-ancestor', before, source, optional=True) is not None
    base = before if valid_before else git(root, 'rev-parse', '--verify', f'{source}^', optional=True)
    blob = git(root, 'show', f'{base}:library.json', optional=True) if base else None
    if blob is None:
        blob = git(root, 'show', f'{source}:library.json', optional=True)
    try:
        base_library = json.loads(blob) if blob is not None else library
        base_version = version_tuple(base_library.get('version'))
    except (json.JSONDecodeError, AttributeError) as error:
        raise SystemExit('Invalid base library metadata.') from error
    increment = 1
    if valid_before:
        subjects = git(root, 'log', '--format=%s', f'{before}..{source}').splitlines()
        increment = max(1, sum(not item.startswith(METADATA_PREFIX) for item in subjects))
    proposed = (base_version[0], base_version[1] + increment)
    current = version_tuple(library['version'])
    selected = max(current, proposed)
    date = int(git(root, 'show', '-s', '--format=%ct', source))
    if date < 0:
        raise SystemExit('Source commit timestamp must be non-negative.')
    return library | {
        'version': f'{selected[0]}.{selected[1]}',
        'build': int(source[:7], 16),
        'date': date,
    }, ''


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--root', type=Path, required=True)
    parser.add_argument('--source-sha', required=True)
    parser.add_argument('--before-sha', default='')
    parser.add_argument('--dry-run', action='store_true')
    args = parser.parse_args()
    root = args.root.resolve()
    path = root / 'library.json'
    if path.is_symlink():
        raise SystemExit('library.json must not be a symbolic link.')
    try:
        library = json.loads(path.read_text(encoding='utf-8'))
    except (OSError, json.JSONDecodeError) as error:
        raise SystemExit('Cannot read library.json.') from error
    if not isinstance(library, dict):
        raise SystemExit('library.json must contain an object.')
    updated, skipped = plan(root, library, args.source_sha, args.before_sha)
    changed = library != updated
    if changed and not args.dry_run:
        path.write_text(json.dumps(updated, ensure_ascii=False, indent=4) + '\n', encoding='utf-8', newline='\n')
    outputs = {
        'changed': str(changed).lower(), 'version': updated['version'],
        'short-sha': args.source_sha[:7], 'skipped': skipped,
    }
    if os.environ.get('GITHUB_OUTPUT') and not args.dry_run:
        with Path(os.environ['GITHUB_OUTPUT']).open('a', encoding='utf-8', newline='\n') as output:
            output.write(''.join(f'{key}={value}\n' for key, value in outputs.items()))
    print(json.dumps(outputs, ensure_ascii=False))


if __name__ == '__main__':
    main()
