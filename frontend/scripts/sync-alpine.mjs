import { copyFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const rootDir = fileURLToPath(new URL('..', import.meta.url));
const sourcePath = require.resolve('alpinejs/dist/cdn.min.js');
const targetPath = join(rootDir, '..', 'public', 'assets', 'vendor', 'alpinejs', 'alpine.min.js');

mkdirSync(dirname(targetPath), { recursive: true });
copyFileSync(sourcePath, targetPath);

console.log(`Copied Alpine.js to ${targetPath}`);
