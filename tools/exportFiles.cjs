const fs = require('fs');
const path = require('path');

const projectRoot = path.resolve(__dirname, '..');

const appPath = path.join(projectRoot, 'app');
const routesPath = path.join(projectRoot, 'routes');
const viewsPath = path.join(projectRoot, 'resources', 'views');
const nodeScannerPath = path.join(projectRoot, 'node-scanner');

function shouldIgnore(filePath) {
  const name = path.basename(filePath).toLowerCase();

  if (name === 'node_modules') return true;
  if (name === '.git') return true;
  if (name.startsWith('.env')) return true;
  if (name.endsWith('.log')) return true;

  if (/\d/.test(name)) return true;
  if (name.includes('save')) return true;
  if (name.includes('off')) return true;
  if (name.includes('copy')) return true;

  return false;
}

function getFilesRecursive(dir) {
  let results = [];

  if (!fs.existsSync(dir)) return results;

  const entries = fs.readdirSync(dir, { withFileTypes: true });

  entries.forEach(entry => {
    const fullPath = path.join(dir, entry.name);

    if (shouldIgnore(fullPath)) return;

    if (entry.isDirectory()) {
      results = results.concat(getFilesRecursive(fullPath));
    }

    if (entry.isFile()) {
      results.push(fullPath);
    }
  });

  return results;
}

let collectedFiles = [];

/*
|--------------------------------------------------------------------------
| APP
|--------------------------------------------------------------------------
*/

['Http', 'Jobs', 'Models', 'Providers'].forEach(folder => {
  const targetPath = path.join(appPath, folder);
  collectedFiles = collectedFiles.concat(getFilesRecursive(targetPath));
});

/*
|--------------------------------------------------------------------------
| ROUTES
|--------------------------------------------------------------------------
*/

collectedFiles = collectedFiles.concat(getFilesRecursive(routesPath));

/*
|--------------------------------------------------------------------------
| VIEWS
|--------------------------------------------------------------------------
*/

['layouts', 'multiscan', 'scans'].forEach(folder => {
  const targetPath = path.join(viewsPath, folder);
  collectedFiles = collectedFiles.concat(
    getFilesRecursive(targetPath).filter(f => f.endsWith('.blade.php'))
  );
});

/*
|--------------------------------------------------------------------------
| NODE SCANNER (komplett außer node_modules)
|--------------------------------------------------------------------------
*/

collectedFiles = collectedFiles.concat(getFilesRecursive(nodeScannerPath));

/*
|--------------------------------------------------------------------------
| FINALIZE
|--------------------------------------------------------------------------
*/

collectedFiles = [...new Set(collectedFiles)].sort();

const relativeFiles = collectedFiles.map(file =>
  path.relative(projectRoot, file)
);

console.log('--- FULL PROJECT EXPORT ---');
console.log('Anzahl Dateien:', relativeFiles.length);
console.log('');

relativeFiles.forEach(file => console.log(' - ' + file));

console.log('---------------------------');

const output = [
  '=== FULL PROJECT EXPORT ===',
  'Anzahl Dateien: ' + relativeFiles.length,
  '',
  'Dateistruktur:',
  ...relativeFiles.map(f => '- ' + f),
  '',
  '=============================',
  '',
  ...collectedFiles.map(file => {
    const content = fs.readFileSync(file, 'utf8');
    return `===== ${path.relative(projectRoot, file)} =====\n\n${content}\n`;
  })
].join('\n');

const filename = 'exportedFullProject.txt';
fs.writeFileSync(filename, output);

console.log('');
console.log('Projekt vollständig exportiert nach ' + filename);
