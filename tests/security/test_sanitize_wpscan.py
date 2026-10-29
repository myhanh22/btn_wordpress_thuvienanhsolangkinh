"""Synthetic scanner data only: these are not vulnerability scan evidence."""
import json
import hashlib
from pathlib import Path
import subprocess
import tempfile
import unittest

SCRIPT = Path(__file__).resolve().parents[1] / 'scripts/sanitize-wpscan.py'

class SanitizerTest(unittest.TestCase):
    def run_scan(self, raw, code=0, version='4.1.0', extra=(), meta_changes=None):
        with tempfile.TemporaryDirectory() as d:
            d = Path(d)
            (d/'raw.json').write_text(json.dumps(raw))
            (d/'meta.json').write_text(json.dumps(dict(verified=True, tool_version=version,
                exit_code=code, raw_sha256=hashlib.sha256((d/'raw.json').read_bytes()).hexdigest(), command_argv=['wpscan','--url','http://example.local', '--api-token', 'SECRET_TEST_VALUE', '--random-user-agent'])))
            if meta_changes:
                m=json.loads((d/'meta.json').read_text());m.update(meta_changes);(d/'meta.json').write_text(json.dumps(m))
            result = subprocess.run(['python3',str(SCRIPT),str(d/'raw.json'),str(d/'out.json'),'--metadata',str(d/'meta.json'),*extra], capture_output=True, text=True)
            return result.returncode, json.loads((d/'out.json').read_text()) if (d/'out.json').exists() else None

    def complete(self):
        return dict(target_url='http://example.local/', start_time=100, stop_time=120,
                    requests_done=12, plugins={}, themes={}, interesting_findings=[])

    def test_actual_version_exit_and_no_waf_inference(self):
        rc, data = self.run_scan(self.complete(), version='9.2.7', extra=['--browser-user-agent'])
        self.assertEqual(rc,0)
        self.assertEqual(data['tool_version'],'9.2.7')
        self.assertEqual(data['exit_code'],0)
        self.assertEqual(data['scan_status'],'completed')
        self.assertEqual(data['waf_mode_during_scan'],'not_verified')
        self.assertNotIn('SECRET_TEST_VALUE',json.dumps(data))
        self.assertEqual(data['start_time'],100)
        self.assertIn('--url http://example.local',data['command_without_secrets'])
        self.assertIn('--random-user-agent',data['command_without_secrets'])

    def test_empty_or_invalid_schema_rejected(self):
        for raw in ({}, [], {'target_url':'http://example.local','plugins':[]}, {'target_url':'http://example.local','start_time':100,'plugins':{'x':None}}):
            self.assertNotEqual(self.run_scan(raw)[0],0)

    def test_wrong_or_unverified_metadata_rejected(self):
        for changes in ({'verified':False}, {'raw_sha256':'0'*64}, {'tool_version':''}, {'command_argv':[]}, {'exit_code':'0'}):
            self.assertNotEqual(self.run_scan(self.complete(),meta_changes=changes)[0],0)

    def test_truncated_json_rejected(self):
        with tempfile.TemporaryDirectory() as d:
            d=Path(d);(d/'raw').write_text('{');(d/'meta').write_text('{}')
            r=subprocess.run(['python3',str(SCRIPT),str(d/'raw'),str(d/'out'),'--metadata',str(d/'meta')],capture_output=True)
            self.assertNotEqual(r.returncode,0)
            self.assertFalse((d/'out').exists())

    def test_complete_authorization_header_redacted(self):
        for header in ('Authorization: Bearer SYNTHETIC_CREDENTIAL', 'authorization: Basic SYNTHETIC_CREDENTIAL', 'Proxy-Authorization: Negotiate SYNTHETIC_CREDENTIAL', 'Cookie: first=SYNTHETIC_CREDENTIAL; second=SYNTHETIC_CREDENTIAL'):
            raw=self.complete();raw['interesting_findings']=[{'to_s':header+'\nServer: nginx','type':'headers'}]
            _,data=self.run_scan(raw)
            self.assertNotIn('SYNTHETIC_CREDENTIAL',json.dumps(data))
            self.assertIn('Server: nginx',data['findings'][0]['summary'])

    def test_blocked(self):
        _, data = self.run_scan(dict(target_url='http://example.local',scan_aborted='HTTP 403'),code=4)
        self.assertEqual(data['exit_code'],4)
        self.assertEqual(data['scan_status'],'aborted')

    def test_missing_actual_exit_is_unknown(self):
        _, data = self.run_scan(self.complete(),code=None)
        self.assertEqual(data['scan_status'],'unknown')
        self.assertIsNone(data['exit_code'])

    def test_nonzero_not_success(self):
        self.assertEqual(self.run_scan(self.complete(),code=7)[1]['scan_status'],'failed')

    def test_api_error_missing_and_success(self):
        for api, expected in (({},False),({'error':'API unavailable'},False),({'plan':'free','requests_remaining':19},True)):
            raw=self.complete(); raw['vuln_api']=api
            self.assertEqual(self.run_scan(raw)[1]['vulnerability_api']['vulnerability_data_available'],expected)

if __name__ == '__main__':
    unittest.main()
