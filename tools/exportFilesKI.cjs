const fs = require('fs');
const path = require('path');

const projectRoot = path.resolve(__dirname, '..');

const paths = {
  controllers: path.join(projectRoot, 'app', 'Http'),
  jobs: path.join(projectRoot, 'app', 'Jobs'),
  models: path.join(projectRoot, 'app', 'Models'),
  providers: path.join(projectRoot, 'app', 'Providers'),
  services: path.join(projectRoot, 'app', 'Services'),
  routes: path.join(projectRoot, 'routes'),
  views: path.join(projectRoot, 'resources', 'views'),
  css: path.join(projectRoot, 'resources', 'css'),
  js: path.join(projectRoot, 'resources', 'js'),
  node: path.join(projectRoot, 'node-scanner'),
  database: path.join(projectRoot, 'database'),
  tailwind: path.join(projectRoot, 'tailwind.config.js'),
  vite: path.join(projectRoot, 'vite.config.js')
};

function shouldIgnore(filePath) {
  const name = path.basename(filePath).toLowerCase();

  if (name === 'node_modules') return true;
  if (name === '.git') return true;
  if (name.startsWith('.env')) return true;

  if (name.endsWith('.log')) return true;
  if (name === 'pqcke.log') return true;

  if (name.endsWith('.json')) return true;
  if (name.endsWith('.svg')) return true;
  if (name.endsWith('.txt')) return true;

  if (name.includes('safelist')) return true;
  if (name.includes('save')) return true;
  if (name.includes('off')) return true;
  if (name.includes('copy')) return true;

  if (/\d/.test(name)) return true;

  return false;
}

function stripSvgBlocks(content) {
  return content.replace(/<svg[\s\S]*?<\/svg>/gi, '');
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

function buildStructure(files) {
  const tree = {};

  files.forEach(file => {
    const relative = path.relative(projectRoot, file);
    const parts = relative.split(path.sep);

    let current = tree;

    parts.forEach((part, index) => {
      if (!current[part]) {
        current[part] = index === parts.length - 1 ? null : {};
      }
      current = current[part] || {};
    });
  });

  function renderTree(obj, depth = 0) {
    let output = '';
    const indent = '  '.repeat(depth);

    Object.keys(obj).sort().forEach(key => {
      output += `${indent}${key}\n`;
      if (obj[key] !== null) {
        output += renderTree(obj[key], depth + 1);
      }
    });

    return output;
  }

  return renderTree(tree);
}

function buildModule(title, files) {
  if (!files.length) return '';

  return [
    `\n=== MODULE: ${title} ===\n`,
    ...files.map(file => {
      let content = fs.readFileSync(file, 'utf8');
      content = stripSvgBlocks(content);
      return `\n----- ${path.relative(projectRoot, file)} -----\n\n${content}\n`;
    })
  ].join('\n');
}

function splitAndWriteOutput(content, maxLines = 2500) {
  const lines = content.split('\n');
  let fileIndex = 1;

  for (let i = 0; i < lines.length; i += maxLines) {
    const chunk = lines.slice(i, i + maxLines).join('\n');
    const fileName = `KI_EXPORT_V6_PART_${fileIndex}.txt`;
    fs.writeFileSync(fileName, chunk);
    fileIndex++;
  }

  return fileIndex - 1;
}

const modules = {
  'HTTP LAYER': getFilesRecursive(paths.controllers),
  'JOB LAYER': getFilesRecursive(paths.jobs),
  'MODEL LAYER': getFilesRecursive(paths.models),
  'PROVIDER LAYER': getFilesRecursive(paths.providers),
  'SERVICE LAYER': getFilesRecursive(paths.services),
  'ROUTES': getFilesRecursive(paths.routes),
  'VIEWS (BLADE)': getFilesRecursive(paths.views).filter(f => f.endsWith('.blade.php')),
  'FRONTEND CSS': getFilesRecursive(paths.css),
  'FRONTEND JS': getFilesRecursive(paths.js),
  'NODE SCANNER (FULL)': getFilesRecursive(paths.node),
  'DATABASE': getFilesRecursive(paths.database),
  'TAILWIND CONFIG': fs.existsSync(paths.tailwind) ? [paths.tailwind] : [],
  'VITE CONFIG': fs.existsSync(paths.vite) ? [paths.vite] : []
};

let allFiles = [];

Object.values(modules).forEach(files => {
  allFiles = allFiles.concat(files);
});

allFiles = [...new Set(allFiles)];

const fileCount = allFiles.length;
const structureOutput = buildStructure(allFiles);

const projectContext = `
=== PROJECT CONTEXT ===

Projekt: Laravel SEO Scanner
Architektur: Laravel Backend + Node (Puppeteer)
Build-System: Vite
CSS Framework: TailwindCSS
Frontend: Blade Templates
Kommunikation: Laravel Job → Node Process → JSON Files
Persistenz: storage/scans/{scanId}

Scan Lifecycle:
queued → running → done → aborted → failed
`;

let output = `
=== EXPORT META ===

Total Files Exported: ${fileCount}

=== FILE STRUCTURE ===

${structureOutput}
`;

output += projectContext;

Object.entries(modules).forEach(([title, files]) => {
  output += buildModule(title, files);
});

output += `
=== DEPENDENCY MAP ===

ScanController
→ RunCrawl Job
→ storage/scans/{scanId}

RunCrawl
→ node-scanner/core/crawler.js

RunLocalSeo
→ node-scanner/core/localSEOScanner.js

localSEOScanner.js
→ checks/*
→ utils/*

Blade Views
→ Vite
→ Tailwind
`;

const totalParts = splitAndWriteOutput(output);

console.log(`KI-kompatibler Export V6 erstellt in ${totalParts} Datei(en)`);
