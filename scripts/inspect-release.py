#!/usr/bin/env python3
"""Checks the content of a release package before it is published (release workflow, after the build).

    python3 scripts/inspect-release.py dist/resume-app-X.Y.Z.zip

Fails (exit code 1) when the zip contains something that must never be published or misses something an
installation needs:
- paths outside the package, symbolic links, executable files (only core/artisan);
- data and secrets: .env files (only .env.example), .git folders, files in core/storage other than .gitignore,
  uploads/, private keys (PEM files are allowed only with public certificates, e.g. vendor/composer/ca-bundle);
- development files: tests/, node_modules/, the build scripts;
- themes other than base (core/themes/{name} and the compiled assets in themes/{name});
- a .htaccess in the web root (it is never overwritten: the package brings htaccess.dist);
- the files the app checks before an update (PackageInspector::REQUIRED), core/.htaccess and htaccess.dist;
- core/VERSION different from VERSION, or core/manifest.json that does not list exactly the files of the zip with
  their SHA-256.
"""

import hashlib
import json
import re
import stat
import sys
import zipfile
from pathlib import Path

REQUIRED = [
    'index.php', 'htaccess.dist', 'core/.htaccess', 'core/VERSION', 'core/manifest.json', 'core/artisan',
    'core/bootstrap/app.php', 'core/vendor/autoload.php', 'core/themes/base/theme.json',
]

FORBIDDEN = [
    (re.compile(r'(^|/)\.git(/|$)'), 'git folder'),
    (re.compile(r'(^|/)node_modules/'), 'node_modules'),
    (re.compile(r'^core/(tests|scripts|\.github|\.devcontainer)/'), 'development files'),
    (re.compile(r'^uploads/'), 'uploaded files'),
    (re.compile(r'^\.htaccess$'), '.htaccess in the web root (the package brings htaccess.dist)'),
    (re.compile(r'^core/themes/(?!base/)[^/]+/'), 'theme other than base'),
    (re.compile(r'^themes/(?!base/)[^/]+/'), 'assets of a theme other than base'),
    (re.compile(r'(^|/)(id_rsa|id_ed25519|auth\.json)$|\.(key|p12|pfx|gpg)$'), 'key or credentials file'),
]

EXECUTABLE_ALLOWED = re.compile(r'^core/artisan$')


def real_name(info: zipfile.ZipInfo) -> str:
    """Name as written by zip: without the UTF-8 flag Python reads it as cp437, but the bytes are UTF-8."""
    if info.flag_bits & 0x800:
        return info.filename
    try:
        return info.filename.encode('cp437').decode('utf-8')
    except UnicodeError:
        return info.filename


def main(path: str) -> int:
    problems: list[str] = []
    version = Path(__file__).resolve().parent.parent.joinpath('VERSION').read_text().strip()

    with zipfile.ZipFile(path) as archive:
        infos = [info for info in archive.infolist() if not info.is_dir()]
        names = {real_name(info) for info in infos}

        for info in infos:
            name = real_name(info)
            mode = info.external_attr >> 16

            if name.startswith('/') or '\\' in name or '..' in Path(name).parts:
                problems.append(f'path outside the package: {name}')
            if stat.S_ISLNK(mode):
                problems.append(f'symbolic link: {name}')
            if mode & 0o111 and not EXECUTABLE_ALLOWED.match(name):
                problems.append(f'executable file: {name}')

            base = name.rsplit('/', 1)[-1]
            if (base == '.env' or base.startswith('.env.')) and base != '.env.example':
                problems.append(f'environment file: {name}')
            if name.startswith('core/storage/') and base != '.gitignore':
                problems.append(f'data in core/storage: {name}')
            for pattern, label in FORBIDDEN:
                if pattern.search(name):
                    problems.append(f'{label}: {name}')
            if base.endswith('.pem') and b'PRIVATE KEY' in archive.read(info):
                problems.append(f'private key: {name}')

        for required in REQUIRED:
            if required not in names:
                problems.append(f'missing: {required}')

        if 'core/VERSION' in names and archive.read('core/VERSION').decode().strip() != version:
            problems.append(f'core/VERSION is not {version}')

        if 'core/manifest.json' in names:
            manifest = json.loads(archive.read('core/manifest.json'))
            listed = manifest.get('files', {})

            if manifest.get('version') != version:
                problems.append(f'manifest.json: version is not {version}')
            for name in sorted(names - set(listed) - {'core/manifest.json'}):
                problems.append(f'manifest.json: file not listed: {name}')
            for name in sorted(set(listed) - names):
                problems.append(f'manifest.json: listed but missing: {name}')
            by_name = {real_name(info): info for info in infos}
            for name in sorted(set(listed) & names):
                if hashlib.sha256(archive.read(by_name[name])).hexdigest() != listed[name]:
                    problems.append(f'manifest.json: wrong SHA-256 of {name}')

    for problem in problems:
        print(f'::error::{problem}')

    if problems:
        print(f'Package inspection: FAILED ({len(problems)} problems)')
        return 1

    print(f'Package inspection: OK ({len(names)} files, version {version})')
    return 0


if __name__ == '__main__':
    if len(sys.argv) != 2:
        print('usage: inspect-release.py package.zip', file=sys.stderr)
        sys.exit(2)
    sys.exit(main(sys.argv[1]))
