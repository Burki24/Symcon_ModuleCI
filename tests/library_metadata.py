#!/usr/bin/env python3

import importlib.util
import json
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch


ROOT = Path(__file__).resolve().parents[1]
sys.dont_write_bytecode = True
SPEC = importlib.util.spec_from_file_location('library_metadata', ROOT / 'library-metadata/update_library_metadata.py')
metadata = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(metadata)
SOURCE = '1234567890abcdef1234567890abcdef12345678'
BEFORE = 'abcdef1234567890abcdef1234567890abcdef12'
LATER = 'fedcba1234567890abcdef1234567890abcdef12'


class MetadataTests(unittest.TestCase):
    def setUp(self):
        self.library = {'id': 'synthetic-library', 'version': '1.0', 'build': 0, 'date': 0}
        self.head = SOURCE
        self.subject = 'FIX: Preserve a setting'
        self.subjects = ['FIX: Preserve a setting']
        self.later = []
        self.before_exists = True
        self.has_parent = True
        self.ancestor = True
        self.base_version = '1.0'

    def git(self, root, *args, optional=False):
        if args == ('show', '-s', '--format=%s', SOURCE):
            return self.subject
        if args == ('rev-parse', 'HEAD'):
            return self.head
        if args[0] == 'merge-base':
            return '' if self.ancestor else None
        if args[0] == 'cat-file':
            return '' if self.before_exists else None
        if args[0] == 'rev-parse':
            return BEFORE if self.has_parent else None
        if args[0] == 'log':
            return '\n'.join(self.later if args[-1] == f'{SOURCE}..{LATER}' else self.subjects)
        if args == ('show', '-s', '--format=%ct', SOURCE):
            return '1785081600'
        if args == ('show', f'{BEFORE}:library.json'):
            return json.dumps({'version': self.base_version})
        if args == ('show', f'{SOURCE}:library.json'):
            return json.dumps({'version': self.base_version})
        raise AssertionError(f'Unexpected Git boundary call: {args}')

    def plan(self, before=BEFORE):
        with patch.object(metadata, 'git', self.git):
            return metadata.plan(ROOT, self.library, SOURCE, before)

    def test_single_source_commit(self):
        result, skipped = self.plan()
        self.assertEqual(result, self.library | {'version': '1.1', 'build': int('1234567', 16), 'date': 1785081600})
        self.assertEqual(skipped, '')
        self.assertEqual(self.library['version'], '1.0')

    def test_batch_excludes_metadata_commits(self):
        self.subjects = ['FIX: One', 'CHORE: Update library metadata to v1.1', 'FEAT: Two']
        self.assertEqual(self.plan()[0]['version'], '1.2')

    def test_manual_version_cannot_regress(self):
        self.library['version'] = '2.0'
        self.assertEqual(self.plan()[0]['version'], '2.0')

    def test_zero_or_missing_before_uses_one_increment(self):
        self.subjects = ['FIX: One', 'FIX: Two']
        self.assertEqual(self.plan('0' * 40)[0]['version'], '1.1')
        self.before_exists = False
        self.assertEqual(self.plan()[0]['version'], '1.1')

    def test_root_commit_without_parent(self):
        self.has_parent = False
        self.assertEqual(self.plan('')[0]['version'], '1.1')

    def test_root_commit_rerun_uses_committed_baseline(self):
        self.has_parent = False
        self.library.update(self.plan('')[0])
        self.assertEqual(self.plan('')[0], self.library)

    def test_metadata_commit_does_not_loop(self):
        self.subject = 'CHORE: Update library metadata to v1.1'
        self.assertEqual(self.plan(), (self.library, 'metadata-only'))

    def test_repeated_source_after_metadata_is_idempotent(self):
        self.library.update(self.plan()[0])
        self.head = LATER
        self.later = ['CHORE: Update library metadata to v1.1']
        self.assertEqual(self.plan(), (self.library, ''))

    def test_superseded_source_cannot_overwrite_latest(self):
        self.head = LATER
        self.later = ['FEAT: Newer source']
        self.assertEqual(self.plan(), (self.library, 'superseded'))
        self.ancestor = False
        self.assertEqual(self.plan(), (self.library, 'superseded'))

    def test_invalid_input_is_rejected(self):
        with self.assertRaises(SystemExit):
            self.plan('not-a-ref')
        self.library['version'] = '1.0\nunsafe=output'
        with self.assertRaises(SystemExit):
            self.plan()

    def test_cli_preserves_other_fields_and_writes_only_metadata(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'library.json'
            path.write_text(json.dumps(self.library), encoding='utf-8')
            output = Path(directory) / 'outputs'
            arguments = ['metadata', '--root', directory, '--source-sha', SOURCE, '--before-sha', BEFORE]
            with patch('sys.argv', arguments), patch.object(metadata, 'git', self.git), patch.dict('os.environ', {'GITHUB_OUTPUT': str(output)}):
                metadata.main()
            result = json.loads(path.read_text(encoding='utf-8'))
            self.assertEqual(result['id'], 'synthetic-library')
            self.assertEqual(result['version'], '1.1')
            self.assertNotIn(b'\r\n', path.read_bytes())
            self.assertIn('changed=true\n', output.read_text(encoding='utf-8'))

    def test_dry_run_leaves_files_and_outputs_unchanged(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'library.json'
            original = json.dumps(self.library).encode()
            path.write_bytes(original)
            output = Path(directory) / 'outputs'
            arguments = ['metadata', '--root', directory, '--source-sha', SOURCE, '--before-sha', BEFORE, '--dry-run']
            with patch('sys.argv', arguments), patch.object(metadata, 'git', self.git), patch.dict('os.environ', {'GITHUB_OUTPUT': str(output)}):
                metadata.main()
            self.assertEqual(path.read_bytes(), original)
            self.assertFalse(output.exists())


if __name__ == '__main__':
    unittest.main()
