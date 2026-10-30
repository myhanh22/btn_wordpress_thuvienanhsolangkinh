#!/usr/bin/env python3
"""Verify public delivery artifacts after building LaTeX; does not certify site security."""
import hashlib
import json
from pathlib import Path
import re
import subprocess

ROOT = Path(__file__).resolve().parents[1]
REPORT = ROOT / 'docs/report'
PDF = REPORT / 'output/lang-kinh-report.pdf'

def require(value, message):
    if not value:
        raise SystemExit('FAIL: ' + message)

require('Template: astra' in (ROOT/'astra-child/style.css').read_text(), 'Astra parent declaration')
for name in ['functions.php','front-page.php','style.css','js/custom.js','inc/upload-form.php','inc/hero.php']:
    require((ROOT/'astra-child'/name).exists(), 'child source ' + name)
for md in ROOT.rglob('*.md'):
    if '.git' in md.parts:
        continue
    for target in re.findall(r'!\[[^\]]*\]\(([^)]+)\)',md.read_text()):
        if not target.startswith(('https://','http://','data:')):
            require((md.parent/target.split('#')[0]).is_file(), 'Markdown image: '+str(md.relative_to(ROOT))+' -> '+target)
for name in re.findall(r'\\shot(?:\[[^]]*\])?\{([^}]+)\}',(REPORT/'sections/02-website.tex').read_text()+(REPORT/'sections/03-security.tex').read_text()+(REPORT/'sections/04-handover-wbs.tex').read_text()):
    require((REPORT/'figures'/name).is_file(), 'LaTeX figure '+name)
manifest=json.loads((ROOT/'database/media-manifest.json').read_text())
for item in manifest:
    require(item['source'].startswith('astra-child/assets/demo-watermarked/') and '..' not in item['source'], 'safe media source')
    require(hashlib.sha256((ROOT/item['source']).read_bytes()).hexdigest()==item['source_sha256'], 'media checksum')
sql=(ROOT/'database/gallery-demo.sql').read_text()
users_insert='\n'.join(line for line in sql.split('\n') if line.startswith('INSERT INTO `wp_users` VALUES '))
password_fields=re.findall(r"\(\d+,'(?:\\.|[^'\\])*','((?:\\.|[^'\\])*)',",users_insert)
require(password_fields and all(v=='!HANDOVER_DISABLED!' for v in password_fields), 'all demo password fields disabled')
require(not re.search(r'\$(?:P|2y|wp\$2y)\$',sql), 'no password hashes in public SQL')
require('session_tokens' not in sql and 'whl_page' not in sql, 'no sessions/private login configuration')
require(all(e.lower().endswith('@example.invalid') for e in re.findall(r'[\w.+%-]+@[\w.-]+',sql)), 'synthetic emails only')
restore=json.loads((ROOT/'database/restore-verification.json').read_text())
require(restore.get('http_bootstrap_login') and restore.get('admin_dashboard_access') and restore.get('restored_url_checked') and restore.get('upload_page_count')==1 and not restore['errors'], 'real restore evidence checks')
require(restore['sql_sha256']==hashlib.sha256((ROOT/'database/gallery-demo.sql').read_bytes()).hexdigest(), 'restore evidence bound to exact SQL')
require(PDF.is_file(), 'build the canonical PDF first')
info=subprocess.check_output(['pdfinfo',str(PDF)],text=True)
page_count=int(re.search(r'Pages:\s+(\d+)',info)[1])
text=subprocess.check_output(['pdftotext','-layout',str(PDF),'-'],text=True)
parts={}
for i,page in enumerate(text.split('\f'),1):
    found=re.match(r'\s*Phần\s+([1234])\b',page)
    if found:parts[int(found[1])]=i
require(1 in parts and 2 in parts,'chapter boundaries')
part_one=parts[2]-parts[1]
require(3<=part_one<=5,'Part 1 must be 3–5 pages')
require('??' not in text,'no unresolved PDF references')
log=(REPORT/'output/main.log').read_text()
for marker in ['Overfull','Missing character','undefined references','Citation `']:
    require(marker not in log,'LaTeX log: '+marker)
result={'engine_log_header':log.splitlines()[0],'pdf_pages':page_count,'part_one_pages':part_one,'part_start_physical_pages':parts,'undefined_references':False,'missing_glyphs':False,'overflow':False,'relative_images_and_media_hashes_valid':True,'restore_sql_hash_matches':True,'pdf_sha256':hashlib.sha256(PDF.read_bytes()).hexdigest(),'scope':'Artifact checks; real site/restore evidence is separately identified.'}
(REPORT/'build-verification.json').write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n')
print('PASS: delivery links, media hashes, sanitized SQL, restore evidence and PDF QA;',page_count,'pages; Part 1:',part_one)