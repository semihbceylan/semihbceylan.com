const { mkdirSync, copyFileSync, cpSync, existsSync } = require('node:fs');
const path = require('node:path');

// Explicit allowlist: never publish PHP source, credentials, or diagnostics.
const root = path.join(__dirname, '..');
const output = path.join(root, 'public');
mkdirSync(output, { recursive: true });
for (const file of ['index.html', 'style.css', 'app.js', 'resume.pdf']) {
  if (existsSync(path.join(root, file))) {
    copyFileSync(path.join(root, file), path.join(output, file));
  }
}
cpSync(path.join(root, 'images'), path.join(output, 'images'), { recursive: true });
