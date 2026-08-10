#!/usr/bin/env python3
import subprocess, sys
commands = [
    ['python3','tests/architecture-check.py'],
    ['python3','tests/source-security-regression.py'],
    ['python3','tests/schema-check.py'],
    ['python3','tests/plan-coverage.py'],
    ['python3','tests/forty-round-review.py'],
    ['php','tests/bootstrap-load.php'],
    ['php','tests/authorization-runtime.php'],
    ['php','tests/verification-runtime.php'],
    ['php','tests/state-runtime.php'],
    ['php','tests/timeline-runtime.php'],
    ['php','tests/forty-round-runtime.php'],
]
for command in commands:
    proc = subprocess.run(command, text=True, capture_output=True)
    label = ' '.join(command)
    if proc.stdout: print(proc.stdout, end='')
    if proc.stderr: print(proc.stderr, end='', file=sys.stderr)
    if proc.returncode:
        message=(proc.stdout+' '+proc.stderr).strip().replace('%','%25').replace('\r','%0D').replace('\n','%0A')
        print(f'::error title=File 03 regression failure::{label}: {message}')
        raise SystemExit(proc.returncode)
    print(f'PASS — {label}')
print('All preserved regression suites passed.')
