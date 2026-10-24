#!/usr/bin/env python3
"""Internal development preview; never use PHP's built-in server for deployment."""
from pathlib import Path
import argparse
import runpy
import subprocess
import sys

ROOT = Path(__file__).resolve().parent.parent
parser = argparse.ArgumentParser()
parser.add_argument('--host', default='127.0.0.1')
parser.add_argument('--port', type=int, default=4173)
parser.add_argument('--strictPort', action='store_true')
args = parser.parse_args()
runpy.run_path(str(ROOT / 'tools/restore-runtime.py'))['configure']()
raise SystemExit(subprocess.run([
    sys.executable, str(ROOT / 'tools/with-database.py'), '--', 'php',
    'app/artisan', 'serve', f'--host={args.host}', f'--port={args.port}', '--no-reload',
], cwd=ROOT).returncode)
