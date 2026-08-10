#!/usr/bin/env python3
from pathlib import Path
import subprocess, sys

script = Path(__file__).with_name('eighty-round-review.py')
proc = subprocess.run([sys.executable, str(script)], text=True, capture_output=True)
if proc.stdout:
    print(proc.stdout, end='')
if proc.stderr:
    print(proc.stderr, end='', file=sys.stderr)
if proc.returncode:
    for line in (proc.stdout + '\n' + proc.stderr).splitlines():
        if 'FAIL' in line or 'failed at rounds:' in line:
            safe = line.replace('%', '%25').replace('\r', '%0D').replace('\n', '%0A')
            print(f'::error title=File 03 80-round gate::{safe}')
    raise SystemExit(proc.returncode)
print('80-round CI diagnostic wrapper passed.')
