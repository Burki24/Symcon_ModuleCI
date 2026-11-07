#!/usr/bin/env python3
"""Verify desktop metadata while retaining the existing library policy."""

import contextlib
import importlib.util
import io
import json
import os
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch

sys.dont_write_bytecode = True
SPEC = importlib.util.spec_from_file_location('metadata_cases', Path(__file__).with_name('library_metadata.py'))
cases = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(cases)
metadata = cases.metadata


class ApplicationMetadataTests(unittest.TestCase):
    def setUp(self):
        self.fixture = cases.MetadataTests()
        self.fixture.setUp()
        self.fixture.library = {'name': 'synthetic-app', 'version': '0.1.0', 'build': 0, 'date': 0}
        self.fixture.base_version = '0.1.0'

    def git(self, root, *args, **kwargs):
        args = tuple(arg.replace(':version.json', ':library.json') for arg in args)
        return self.fixture.git(root, *args, **kwargs)

    def plan(self, before=cases.BEFORE):
        with patch.object(metadata, 'git', self.git):
            return metadata.plan(cases.ROOT, self.fixture.library, cases.SOURCE, before, 'application')

    def test_application_uses_semver_and_source_identity(self):
        result, skipped = self.plan()
        self.assertEqual(result, {'name': 'synthetic-app', 'version': '0.2.0',
                                 'build': int(cases.SOURCE[:7], 16), 'date': 1785081600})
        self.assertEqual(skipped, '')
        self.assertEqual(self.fixture.library['version'], '0.1.0')

    def test_batch_excludes_application_metadata(self):
        self.fixture.subjects = ['FEAT: One', 'CHORE: Update application metadata to v0.2.0', 'FIX: Two']
        self.assertEqual(self.plan()[0]['version'], '0.3.0')

    def test_patch_resets_when_minor_advances_and_manual_version_is_preserved(self):
        self.fixture.base_version = '1.4.9'
        self.assertEqual(self.plan()[0]['version'], '1.5.0')
        self.fixture.library['version'] = '2.0.3'
        self.assertEqual(self.plan()[0]['version'], '2.0.3')

    def test_metadata_only_and_superseded_sources_are_skipped(self):
        self.fixture.subject = 'CHORE: Update application metadata to v0.2.0'
        self.assertEqual(self.plan()[1], 'metadata-only')
        self.fixture.subject = 'FEAT: One'
        self.fixture.head = cases.LATER
        self.fixture.later = ['FEAT: Newer source']
        self.assertEqual(self.plan()[1], 'superseded')

    def test_retry_after_bot_commit_is_idempotent(self):
        self.fixture.library.update(self.plan()[0])
        self.fixture.head = cases.LATER
        self.fixture.later = ['CHORE: Update application metadata to v0.2.0']
        self.assertEqual(self.plan()[0], self.fixture.library)

    def test_missing_before_and_root_commit(self):
        self.fixture.has_parent = False
        self.assertEqual(self.plan('0' * 40)[0]['version'], '0.2.0')
        self.fixture.library.update(self.plan('')[0])
        self.assertEqual(self.plan('')[0]['version'], '0.2.0')

    def test_invalid_versions_and_targets_are_rejected(self):
        for value in ['0.1', '0.01.0', '0.1.0-beta', '0.1.0\nunsafe=output']:
            self.fixture.library['version'] = value
            with self.assertRaises(SystemExit):
                self.plan()
        with self.assertRaises(SystemExit):
            metadata.plan(cases.ROOT, {}, cases.SOURCE, cases.BEFORE, '../outside')
        with self.assertRaises(SystemExit):
            metadata.version_tuple('1.2', 'unsupported')

    def test_application_mode_does_not_change_the_library_format(self):
        self.assertEqual(metadata.version_tuple('0.180'), (0, 180))
        self.assertEqual(metadata.version_tuple('0.1.0', 'application'), (0, 1, 0))
        with self.assertRaises(SystemExit):
            metadata.version_tuple('0.1.0')
        with self.assertRaises(SystemExit):
            metadata.version_tuple('0.180', 'application')

    def test_cli_updates_only_application_metadata(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            path = root / 'version.json'
            path.write_text(json.dumps(self.fixture.library), encoding='utf-8')
            library = root / 'library.json'
            library.write_text('{"version":"7.4"}\n', encoding='utf-8')
            output = root / 'outputs'
            args = ['metadata', '--root', directory, '--target', 'application',
                    '--source-sha', cases.SOURCE, '--before-sha', cases.BEFORE]
            with patch('sys.argv', args), patch.object(metadata, 'git', self.git), patch.dict(
                os.environ, {'GITHUB_OUTPUT': str(output)}
            ), contextlib.redirect_stdout(io.StringIO()):
                metadata.main()
            self.assertEqual(json.loads(path.read_text())['version'], '0.2.0')
            self.assertEqual(library.read_text(), '{"version":"7.4"}\n')
            self.assertIn('changed=true\n', output.read_text())
            self.assertNotIn(b'\r', path.read_bytes())

    def test_application_dry_run_is_read_only(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'version.json'
            raw = json.dumps(self.fixture.library).encode()
            path.write_bytes(raw)
            output = Path(directory) / 'outputs'
            args = ['metadata', '--root', directory, '--target', 'application',
                    '--source-sha', cases.SOURCE, '--before-sha', cases.BEFORE, '--dry-run']
            with patch('sys.argv', args), patch.object(metadata, 'git', self.git), patch.dict(
                os.environ, {'GITHUB_OUTPUT': str(output)}
            ), contextlib.redirect_stdout(io.StringIO()):
                metadata.main()
            self.assertEqual(path.read_bytes(), raw)
            self.assertFalse(output.exists())


if __name__ == '__main__':
    unittest.main()
