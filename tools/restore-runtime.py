#!/usr/bin/env python3
"""Restore the isolated Ubuntu 24.04 AMD64 development tools; never install globally."""
from pathlib import Path
import concurrent.futures
import hashlib
import json
import platform
import shlex
import subprocess
import urllib.request

ROOT = Path(__file__).resolve().parent.parent
RUNTIME = ROOT / '.runtime'
DOWNLOADS = ROOT / 'downloads'


def download(item):
    destination = DOWNLOADS / item['filename']
    if not destination.exists() or hashlib.sha256(destination.read_bytes()).hexdigest() != item['sha256']:
        with urllib.request.urlopen(item['url'], timeout=60) as response:
            content = response.read()
        if hashlib.sha256(content).hexdigest() != item['sha256']:
            raise RuntimeError('Checksum mismatch: ' + item['filename'])
        destination.write_bytes(content)
    return destination


def configure():
    (RUNTIME / 'bin').mkdir(parents=True, exist_ok=True)
    (RUNTIME / 'php-conf.d').mkdir(exist_ok=True)
    extension_dir = RUNTIME / 'usr/lib/php/20230831'
    extensions = ['pdo', 'mysqlnd', 'ctype', 'fileinfo', 'iconv', 'tokenizer',
                  'mbstring', 'pdo_mysql', 'mysqli', 'dom', 'simplexml', 'xml',
                  'xmlreader', 'xmlwriter', 'curl', 'zip', 'intl', 'bcmath',
                  'phar', 'posix', 'sockets', 'readline']
    ini = ['[PHP]', f'extension_dir="{extension_dir}"', 'date.timezone=UTC',
           'memory_limit=512M', 'expose_php=Off', 'display_errors=stderr',
           'error_reporting=E_ALL', 'log_errors=On', 'variables_order=EGPCS',
           'curl.cainfo=/etc/ssl/certs/ca-certificates.crt',
           'openssl.cafile=/etc/ssl/certs/ca-certificates.crt',
           'zend_extension=opcache', 'opcache.enable_cli=0']
    ini += [f'extension={name}' for name in extensions]
    (RUNTIME / 'php.ini').write_text('\n'.join(ini) + '\n')
    q = shlex.quote
    wrappers = {
        'php': f'export PHPRC={q(str(RUNTIME / "php.ini"))}\nexport PHP_INI_SCAN_DIR={q(str(RUNTIME / "php-conf.d"))}\nexec {q(str(RUNTIME / "usr/bin/php8.3"))} -c {q(str(RUNTIME / "php.ini"))} "$@"',
        'composer': f'export COMPOSER_HOME={q(str(RUNTIME / "composer-home"))}\nexport COMPOSER_ALLOW_SUPERUSER=1\nexec {q(str(RUNTIME / "bin/php"))} {q(str(RUNTIME / "composer.phar"))} "$@"',
    }
    for name, path in [('mysql', 'usr/bin/mysql'), ('mysqld', 'usr/sbin/mysqld')]:
        library_paths = str(RUNTIME / 'usr/lib/x86_64-linux-gnu') + ':' + str(RUNTIME / 'usr/lib/mysql/private')
        wrappers[name] = f'export LD_LIBRARY_PATH={q(library_paths)}${{LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}}\nexec {q(str(RUNTIME / path))} "$@"'
    for name, body in wrappers.items():
        target = RUNTIME / 'bin' / name
        target.write_text('#!/usr/bin/env bash\nset -euo pipefail\n' + body + '\n')
        target.chmod(0o755)
    (RUNTIME / 'env.sh').write_text(f'export PATH={q(str(RUNTIME / "bin"))}:"$PATH"\n')


def main():
    if platform.machine() != 'x86_64' or 'VERSION_ID="24.04"' not in Path('/etc/os-release').read_text():
        raise SystemExit('This optional runtime is for Ubuntu 24.04 AMD64. On other systems use PHP 8.3+, Composer 2 and MySQL 8.4.')
    RUNTIME.mkdir(exist_ok=True)
    DOWNLOADS.mkdir(exist_ok=True)
    packages = json.loads((ROOT / 'evidence/runtime-packages.json').read_text())
    for package, archive in zip(packages, concurrent.futures.ThreadPoolExecutor(max_workers=6).map(download, packages)):
        subprocess.run(['dpkg-deb', '--extract', str(archive), str(RUNTIME)], check=True)
        print(f"Ready: {package['package']} {package['version']} (SHA256 verified)", flush=True)
    composer = json.loads((ROOT / 'evidence/composer-download.json').read_text())
    composer['filename'] = 'composer.phar'
    (RUNTIME / 'composer.phar').write_bytes(download(composer).read_bytes())
    configure()
    print('Runtime configured. Activate with: source .runtime/env.sh')


if __name__ == '__main__':
    main()
