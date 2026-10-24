#!/usr/bin/env python3
"""Manage this workspace's private development MySQL server, never a remote server."""
from pathlib import Path
import argparse
import json
import os
import secrets
import subprocess

ROOT = Path(__file__).resolve().parent.parent
RUNTIME = ROOT / '.runtime'
STATE = RUNTIME / 'mysql'
CONFIG = STATE / 'my.cnf'
ADMIN = STATE / 'admin.cnf'
PORT = 33067


def private_write(path, text):
    descriptor = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
    with os.fdopen(descriptor, 'w') as stream:
        stream.write(text)


def run(binary, *args, **kwargs):
    return subprocess.run([str(RUNTIME / 'bin' / binary), *map(str, args)], check=True, **kwargs)


def start():
    if not CONFIG.exists():
        raise SystemExit('Run python3 tools/database.py init first.')
    run('mysqld', f'--defaults-file={CONFIG}', '--daemonize')
    print('MySQL started on 127.0.0.1:' + str(PORT))


def initialize():
    if STATE.exists():
        raise SystemExit('Database state already exists. Refusing to overwrite. Use start/status.')
    STATE.mkdir(mode=0o700)
    (STATE / 'data').mkdir(mode=0o700)
    settings = [
        '[mysqld]', f'basedir={RUNTIME / "usr"}', f'datadir={STATE / "data"}',
        f'lc-messages-dir={RUNTIME / "usr/share/mysql-8.4"}',
        f'plugin-dir={RUNTIME / "usr/lib/mysql/plugin"}',
        'socket=', f'pid-file={STATE / "mysqld.pid"}',
        f'log-error={STATE / "mysql-error.log"}', 'bind-address=127.0.0.1',
        f'port={PORT}', 'mysqlx=OFF', 'skip-name-resolve=ON',
        'character-set-server=utf8mb4', 'collation-server=utf8mb4_unicode_ci',
        'default-time-zone=+00:00', 'default-storage-engine=InnoDB',
        'innodb-buffer-pool-size=64M', 'max-connections=30',
        'skip-log-bin', 'local-infile=OFF', 'secure-file-priv=NULL',
    ]
    if os.getuid() == 0:
        settings += ['user=root']
    private_write(CONFIG, '\n'.join(settings) + '\n')
    credentials = {name: secrets.token_hex(32) for name in ['root', 'development', 'testing']}
    private_write(STATE / 'credentials.json', json.dumps(credentials) + '\n')
    private_write(ADMIN, f'[client]\nuser=root\npassword={credentials["root"]}\nprotocol=TCP\nhost=127.0.0.1\nport={PORT}\n')
    bootstrap = STATE / 'bootstrap.sql'
    statements = [
        f"ALTER USER 'root'@'localhost' IDENTIFIED BY '{credentials['root']}';",
        f"CREATE USER 'root'@'127.0.0.1' IDENTIFIED BY '{credentials['root']}';",
        "GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;",
    ]
    for database, username, purpose in [('penguin_academy', 'penguin_dev', 'development'), ('penguin_academy_test', 'penguin_test', 'testing')]:
        statements += [
            f'CREATE DATABASE `{database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;',
            f"CREATE USER '{username}'@'127.0.0.1' IDENTIFIED BY '{credentials[purpose]}';",
            f"GRANT ALL PRIVILEGES ON `{database}`.* TO '{username}'@'127.0.0.1';",
        ]
    private_write(bootstrap, '\n'.join(statements) + '\n')
    run('mysqld', f'--defaults-file={CONFIG}', '--initialize-insecure')
    try:
        run('mysqld', f'--defaults-file={CONFIG}', f'--init-file={bootstrap}', '--daemonize')
        run('mysql', f'--defaults-extra-file={ADMIN}', '--batch', '--execute', 'SELECT 1 AS ready;')
    finally:
        bootstrap.unlink(missing_ok=True)
    print('Created isolated development/test databases and separate database users. Credentials are private local files.')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('action', choices=['init', 'start', 'serve', 'stop', 'status'])
    action = parser.parse_args().action
    if action == 'init':
        initialize()
    elif action == 'start':
        start()
    elif action == 'serve':
        run('mysqld', f'--defaults-file={CONFIG}')
    elif action == 'stop':
        run('mysql', f'--defaults-extra-file={ADMIN}', '--execute', 'SHUTDOWN;')
    else:
        run('mysql', f'--defaults-extra-file={ADMIN}', '--batch', '--execute',
            "SELECT VERSION() AS version, @@global.time_zone AS timezone, @@character_set_server AS charset, @@default_storage_engine AS engine; SHOW DATABASES LIKE 'penguin_academy%';")


if __name__ == '__main__':
    main()
