const fs = require('fs');
const path = require('path');
const glob = require('glob');

// Blade-Dateien finden
const bladePaths = glob.sync('../resources/views/**/*.blade.php');
console.log(`📁 Durchsuche ${bladePaths.length} Blade-Dateien`);

const classSet = new Set();

bladePaths.forEach(file => {
  const content = fs.readFileSync(file, 'utf8');
  const matches = content.match(/class\s*=\s*["']([^"']+)["']/gi);
  if (!matches) return;

  matches.forEach(match => {
    const raw = match.replace(/class\s*=\s*["']/, '').replace(/["']$/, '');
    const classList = raw.split(/\s+/).map(cls => cls.trim());

    classList.forEach(cls => {
      if (
        cls.length <= 1 ||
        cls.startsWith('@') ||
        cls.startsWith('-') ||
        cls.includes(':') ||
        cls.includes('(') || cls.includes(')') ||
        cls.includes('{{') || cls.includes('}}') ||
        !/^[a-z0-9\-\[\]\/]+$/i.test(cls)
      ) return;

      classSet.add(cls);
    });
  });
});

// Ergebnis als JS-Array speichern (für Tailwind safelist)
const sorted = Array.from(classSet).sort();
const output = `safelist: [\n  '${sorted.join("',\n  '")}'\n]`;

const outputPath = path.join(__dirname, 'safelist.txt');
fs.writeFileSync(outputPath, output, 'utf8');

console.log(`✅ ${sorted.length} Klassen im Safelist-Format geschrieben nach: ${outputPath}`);
