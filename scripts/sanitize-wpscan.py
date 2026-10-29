#!/usr/bin/env python3
"""Export an allowlisted summary. Never publish raw WPScan output or credentials."""
import argparse
import hashlib
from datetime import datetime, timezone
import json
import os
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('raw')
parser.add_argument('output')
parser.add_argument('--metadata', required=True, help='Verified runner JSON: tool_version, command_argv, raw_sha256, exit_code (null if unavailable)')
parser.add_argument('--browser-user-agent', action='store_true', help='Compatibility only; does not establish WAF configuration')
parser.add_argument('--api-token-used', action='store_true')
parser.add_argument('--installed-inventory', help='Allowlisted filesystem inventory JSON, never a credential file')
args = parser.parse_args()
try:
    raw = json.loads(Path(args.raw).read_text())
    metadata = json.loads(Path(args.metadata).read_text())
    if not isinstance(raw, dict) or not raw or not isinstance(raw.get('target_url'), str):
        raise ValueError('Missing WPScan target/schema')
    from urllib.parse import urlsplit
    if urlsplit(raw['target_url']).scheme not in ('http', 'https'):
        raise ValueError('Invalid scan target')
    for key in ('start_time', 'stop_time'):
        if key in raw and raw[key] is not None:
            if type(raw[key]) not in (int, float) or raw[key] < 0:
                raise ValueError('Invalid scan timestamp')
            datetime.fromtimestamp(raw[key], timezone.utc)
    if raw.get('stop_time') and raw.get('start_time') and raw['stop_time'] < raw['start_time']:
        raise ValueError('Scan stop precedes start')
    if 'requests_done' in raw and (type(raw['requests_done']) is not int or raw['requests_done'] < 0):
        raise ValueError('Invalid request count')
    for key in ('plugins', 'themes', 'vuln_api'):
        if key in raw and not isinstance(raw[key], dict):
            raise ValueError('Invalid WPScan object: ' + key)
    for key in ('plugins', 'themes'):
        if any(not isinstance(v, dict) for v in raw.get(key, {}).values()):
            raise ValueError('Invalid component record')
    if not isinstance(raw.get('interesting_findings', []), list) or any(not isinstance(v, dict) for v in raw.get('interesting_findings', [])):
        raise ValueError('Invalid findings')
    if not raw.get('scan_aborted') and not isinstance(raw.get('start_time'), (int, float)):
        raise ValueError('Missing scan start/abort evidence')
    if not isinstance(metadata, dict) or metadata.get('verified') is not True:
        raise ValueError('Runner metadata must be verified')
    if not isinstance(metadata.get('tool_version'), str) or not metadata['tool_version'].strip():
        raise ValueError('Missing verified tool version')
    if metadata.get('raw_sha256') != hashlib.sha256(Path(args.raw).read_bytes()).hexdigest():
        raise ValueError('Runner metadata does not match this raw scan')
    argv = metadata.get('command_argv')
    if not isinstance(argv, list) or not argv or any(not isinstance(v, str) for v in argv):
        raise ValueError('Missing actual command argv')
    exit_code = metadata.get('exit_code')
    if exit_code is not None and type(exit_code) is not int:
        raise ValueError('Invalid runner exit code')
except (ValueError, OSError, TypeError, OverflowError) as error:
    parser.exit(2, 'Invalid scan/metadata; output not written: ' + str(error) + '\n')
slug = ''
if os.environ.get('LK_SECURITY_PRIVATE'):
    slug = json.loads(Path(os.environ['LK_SECURITY_PRIVATE']).read_text()).get('login_slug', '')

def safe(value):
    if isinstance(value, dict):
        return {k: ('[REDACTED]' if k.lower().replace('-', '_') in ('token', 'api_token', 'access_token', 'password', 'user_pass', 'cookie', 'cookies', 'authorization', 'secret', 'api_key') else safe(v)) for k, v in value.items()}
    if isinstance(value, list):
        return [safe(v) for v in value]
    if isinstance(value, str):
        import re
        if slug:
            value = value.replace(slug, '[PRIVATE_LOGIN]')
        token = os.environ.get('WPSCAN_API_TOKEN')
        if token:
            value = value.replace(token, '[REDACTED]')
        # A header credential includes its scheme and ALL subsequent credentials.
        value = re.sub(r'(?im)((?:proxy-authorization|authorization|set-cookie|cookie)\s*[:=]\s*)[^\r\n]*', r'\1[REDACTED]', value)
        value = re.sub(r'(?i)((?:api[-_]?token|password|cookie|authorization|secret)\s*[:=]\s*)[^&\s]+', r'\1[REDACTED]', value)
        value = re.sub(r'(https?://)[^/@\s]+:[^/@\s]+@', r'\1[REDACTED]@', value)
    return value

def command_without_secrets(argv):
    import shlex
    result, redact_next = [], False
    for arg in argv:
        if redact_next:
            result.append('[REDACTED]')
            redact_next = False
        elif arg.startswith('--output='):
            result.append('--output=<PRIVATE_RAW_OUTPUT>')
        elif any(key in arg.lower() for key in ('token', 'password', 'cookie', 'authorization', 'secret', 'http-auth', 'basic-auth')):
            result.append(arg.split('=', 1)[0] + '=[REDACTED]' if '=' in arg else arg)
            redact_next = '=' not in arg
        elif result and result[-1] in ('--output', '-o'):
            result.append('<PRIVATE_RAW_OUTPUT>')
        else:
            result.append(safe(arg))
    return shlex.join(result)


def version(value):
    if not isinstance(value, dict):
        return None
    return {key: value[key] for key in ['number', 'status', 'found_by', 'confidence'] if key in value}

def vulnerabilities(value):
    if not isinstance(value, dict):
        return []
    results = []
    for item in value.get('vulnerabilities', []):
        if not isinstance(item, dict):
            continue
        record = {key: item[key] for key in ['id', 'title', 'vuln_type', 'fixed_in', 'cvss', 'verified'] if key in item}
        refs = item.get('references', {})
        if isinstance(refs, dict):
            record['references'] = {key: refs[key] for key in ['cve', 'url', 'wpvulndb'] if key in refs}
        results.append(record)
    return results

api = raw.get('vuln_api', {})
api_success = bool(api.get('plan') and api.get('requests_remaining') is not None and not api.get('error') and not raw.get('scan_aborted'))
api_summary = {
    'token_provided': bool(args.api_token_used or api_success),
    'vulnerability_data_available': api_success,
    'limitation': 'Only detected components/versions are covered; custom code and missing API records are not certified safe.' if api_success else 'No successful vulnerability API result: do not infer absence of vulnerabilities.',
}
for key in ['plan', 'requests_done_during_scan', 'requests_remaining', 'error']:
    if key in api:
        api_summary[key] = safe(api[key])

summary = {
    'tool': 'WPScan', 'tool_version': metadata['tool_version'],
    'scope': safe(raw.get('target_url')), 'start_time': raw.get('start_time'),
    'stop_time': raw.get('stop_time'),
    'start_time_utc': datetime.fromtimestamp(raw['start_time'], timezone.utc).isoformat() if isinstance(raw.get('start_time'), (int, float)) else None,
    'stop_time_utc': datetime.fromtimestamp(raw['stop_time'], timezone.utc).isoformat() if isinstance(raw.get('stop_time'), (int, float)) else None, 'elapsed_seconds': raw.get('elapsed'),
    'requests_done': raw.get('requests_done'), 'cached_requests': raw.get('cached_requests'), 'scan_aborted': safe(raw.get('scan_aborted')),
    'command_without_secrets': command_without_secrets(argv),
    'vulnerability_api': api_summary,
    'detected_wordpress': version(raw.get('version')),
    'findings': [{'type': item.get('type'), 'summary': safe(item.get('to_s')), 'found_by': item.get('found_by')} for item in raw.get('interesting_findings', [])],
    'detected_plugins': {name: version(info.get('version')) for name, info in raw.get('plugins', {}).items()},
    'detected_themes': {name: version(info.get('version')) for name, info in raw.get('themes', {}).items()},
}
summary['vulnerabilities'] = {
    'wordpress': vulnerabilities(raw.get('version')),
    'main_theme': vulnerabilities(raw.get('main_theme')),
    'plugins': {name: vulnerabilities(info) for name, info in raw.get('plugins', {}).items()},
    'themes': {name: vulnerabilities(info) for name, info in raw.get('themes', {}).items()},
}
main_theme = raw.get('main_theme')
if isinstance(main_theme, dict):
    summary['main_theme'] = {'slug': main_theme.get('slug'), 'version': version(main_theme.get('version'))}
if args.installed_inventory:
    inventory = json.loads(Path(args.installed_inventory).read_text())
    summary['installed_inventory'] = inventory
    summary['installed_plugins_not_detected'] = [item['slug'] for item in inventory.get('plugins', []) if item['slug'] not in raw.get('plugins', {})]
# Historical start/stop values come only from this raw scan, never the current clock.
summary['exit_code'] = exit_code
completed = bool(raw.get('stop_time') and isinstance(raw.get('requests_done'), int) and not raw.get('scan_aborted'))
summary['scan_status'] = 'aborted' if raw.get('scan_aborted') else ('completed' if completed and exit_code == 0 else ('failed' if exit_code not in (None, 0) else 'unknown'))
summary['waf_mode_during_scan'] = 'not_verified'
summary['raw_sha256'] = hashlib.sha256(Path(args.raw).read_bytes()).hexdigest()
Path(args.output).write_text(json.dumps(safe(summary), ensure_ascii=False, indent=2) + '\n')