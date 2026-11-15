#!/usr/bin/env python3
"""Prevent silently skipped multiline PHP regression commands in GitHub Actions.

A YAML plain scalar such as:
  run: php tests/one.php
    php tests/two.php
does NOT execute two commands. It folds whitespace and runs php one.php two.php.
This exact false-green failure happened during File03 D07 review.
"""
from pathlib import Path
import re
root=Path(__file__).resolve().parents[1]
wf=root/'.github/workflows'
violations=[]
for file in wf.glob('*.yml'):
    text=file.read_text(encoding='utf-8')
    lines=text.splitlines()
    for n,(one,two) in enumerate(zip(lines,lines[1:]),1):
        if re.search(r'\brun:\s*php\s+tests/[\w-]+\.php\s*$',one) and re.search(r'^\s+php\s+tests/[\w-]+\.php\s*$',two):
            violations.append(f'{file.name}:{n}: folded two-PHP-command YAML scalar; use run: |')
checks={
    'fresh-eighty-round-review.yml':[
        'tests/minor-city-read-runtime.php','tests/file26-owner-field-parity-runtime.php',
        'tests/central-clinic-verification-runtime.php','tests/central-professional-services-guard-runtime.php',
    ],
    'latest-plan-completion.yml':[
        'tests/minor-city-read-runtime.php','tests/file26-owner-field-parity-runtime.php',
        'tests/central-clinic-verification-runtime.php','tests/central-professional-services-guard-runtime.php',
    ],
    'plan-completion-v2.yml':[
        'tests/minor-city-read-runtime.php','tests/file26-owner-field-parity-runtime.php',
        'tests/central-clinic-verification-runtime.php','tests/central-professional-services-guard-runtime.php',
    ],
}
for filename,commands in checks.items():
    content=(wf/filename).read_text(encoding='utf-8')
    for test in commands:
        if len(re.findall(r'^\s+php\s+'+re.escape(test)+r'\s*$',content,re.M))!=1:
            violations.append(filename+': expected exactly one independently terminated command '+test)
        if not (root/test).is_file(): violations.append(filename+': test target missing '+test)
if violations:
    for msg in violations:print('FAIL: '+msg)
    raise SystemExit(1)
print('PASS D06/D07/D09/D10 QA workflow includes 12 real standalone PHP commands and no known YAML folded-shell trap.')
