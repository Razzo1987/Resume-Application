// npm lockfile check: every package must come from the public npm registry over HTTPS and carry an integrity hash.
// A dependency resolved from a git URL, a tarball on another host or plain HTTP would bypass the registry checks.
// Usage: node scripts/check-lockfile.mjs package-lock.json
import { readFileSync } from 'node:fs';

const file = process.argv[2] ?? 'package-lock.json';
const lock = JSON.parse(readFileSync(file, 'utf8'));
const registry = 'https://registry.npmjs.org/';
const problems = [];

for (const [path, entry] of Object.entries(lock.packages ?? {})) {
    // "" is the project itself; links point to folders of this repository
    if (path === '' || entry.link) {
        continue;
    }

    if (typeof entry.resolved !== 'string' || !entry.resolved.startsWith(registry)) {
        problems.push(`${path}: resolved from ${entry.resolved ?? 'nowhere'}`);
    } else if (typeof entry.integrity !== 'string' || !entry.integrity.startsWith('sha512-')) {
        problems.push(`${path}: missing sha512 integrity`);
    }
}

if (problems.length > 0) {
    for (const problem of problems) {
        console.log(`::error::${problem}`);
    }
    process.exit(1);
}

console.log(`Lockfile OK: ${Object.keys(lock.packages ?? {}).length - 1} packages from ${registry}`);
