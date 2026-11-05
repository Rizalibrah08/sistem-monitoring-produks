import { spawnSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname } from 'node:path';
import { browserDatabase, browserEnvironment } from './environment';

export default function setup() {
    mkdirSync(dirname(browserDatabase), { recursive: true });
    writeFileSync(browserDatabase, '', { flag: 'wx' });
    runArtisan(['migrate', '--no-interaction']);
    runArtisan(['app:create-admin', 'admin@backend.test', '--name=Admin Browser', '--password-stdin', '--no-interaction'], 'AishaBrowser123!\n');
}

function runArtisan(arguments_: string[], input?: string) {
    const result = spawnSync('php', ['artisan', ...arguments_], {
        env: { ...process.env, ...browserEnvironment },
        encoding: 'utf8',
        input,
    });
    if (result.status !== 0) throw new Error(result.stderr || result.stdout || result.error?.message || 'Persiapan database browser gagal.');
}
