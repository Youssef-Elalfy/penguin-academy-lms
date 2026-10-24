#!/usr/bin/env python3
"""Create private local environment files from the isolated database credentials."""
from pathlib import Path
import base64
import json
import os
import secrets

ROOT = Path(__file__).resolve().parent.parent
APP = ROOT / 'app'


def replace_values(template, replacements):
    return '\n'.join(f'{line.split("=", 1)[0]}={replacements[line.split("=", 1)[0]]}'
                     if '=' in line and line.split('=', 1)[0] in replacements else line
                     for line in template.splitlines()) + '\n'


def main():
    credentials = json.loads((ROOT / '.runtime/mysql/credentials.json').read_text())
    for filename, purpose in [('.env', 'development'), ('.env.testing', 'testing')]:
        destination = APP / filename
        if destination.exists():
            print(f'Preserved existing {filename}.')
            continue
        template = APP / ('.env.example' if purpose == 'development' else '.env.testing.example')
        text = replace_values(template.read_text(), {
            'APP_KEY': 'base64:' + base64.b64encode(secrets.token_bytes(32)).decode(),
            'DB_PASSWORD': credentials[purpose],
        })
        descriptor = os.open(destination, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
        with os.fdopen(descriptor, 'w') as stream:
            stream.write(text)
        print(f'Created private {filename}; no secrets printed.')


if __name__ == '__main__':
    main()
