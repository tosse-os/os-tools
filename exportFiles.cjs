// exportFiles.js
const fs = require('fs');
const path = require('path');

const files = [
  'resources/views/multiscan/live.blade.php',
  'app/Http/Controllers/ScanController.php',
  'app/Jobs/RunScan.php',
  'routes/web.php',
  'node-scanner/multiScanner.js'
];

const output = files.map(file => {
  const content = fs.readFileSync(file, 'utf-8');
  return `===== ${file} =====\n\n${content}\n`;
}).join('\n\n');

fs.writeFileSync('exported-files.txt', output);
console.log('Dateien exportiert nach exported-files.txt');
