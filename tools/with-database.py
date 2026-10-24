#!/usr/bin/env python3
"""Run a command with private MySQL in the same execution session."""
from pathlib import Path
import os
import subprocess
import sys
import time

ROOT = Path(__file__).resolve().parent.parent
RUNTIME = ROOT / '.runtime'


def main():
    command = sys.argv[1:]
    if command[:1] == ['--']:
        command.pop(0)
    if not command:
        raise SystemExit('Usage: python3 tools/with-database.py -- COMMAND [ARGUMENTS]')
    environment = os.environ.copy()
    environment['PATH'] = str(RUNTIME / 'bin') + os.pathsep + environment['PATH']
    server = subprocess.Popen([
        str(RUNTIME / 'bin/mysqld'), f'--defaults-file={RUNTIME / "mysql/my.cnf"}',
        f'--basedir={RUNTIME / "usr"}', f'--datadir={RUNTIME / "mysql/data"}',
        f'--lc-messages-dir={RUNTIME / "usr/share/mysql-8.4"}',
        f'--plugin-dir={RUNTIME / "usr/lib/mysql/plugin"}',
        f'--pid-file={RUNTIME / "mysql/mysqld.pid"}',
        f'--log-error={RUNTIME / "mysql/mysql-error.log"}',
    ], env=environment)
    client = [str(RUNTIME / 'bin/mysql'), f'--defaults-extra-file={RUNTIME / "mysql/admin.cnf"}']
    try:
        for _ in range(100):
            if server.poll() is not None:
                raise RuntimeError('MySQL exited during startup. Inspect its private error log.')
            check = subprocess.run([*client, '--execute', 'SELECT 1;'], capture_output=True, env=environment)
            if check.returncode == 0:
                break
            time.sleep(0.2)
        else:
            raise RuntimeError('MySQL was not ready within 20 seconds. Check for another process using the data directory.')
        print('Private MySQL connection verified.', flush=True)
        return subprocess.run(command, cwd=ROOT, env=environment).returncode
    finally:
        if server.poll() is None:
            subprocess.run([*client, '--execute', 'SHUTDOWN;'], capture_output=True, env=environment)
            try:
                server.wait(timeout=15)
            except subprocess.TimeoutExpired:
                server.terminate()
                server.wait(timeout=5)


if __name__ == '__main__':
    raise SystemExit(main())
