// Copies the deployable game files into dist/<name>/, renaming "terraformingmars" to <name>
// in file names and file contents, so the game can be uploaded to a BGA Studio project with a different name.
// Usage: node misc/other/stage.js <bga_project_name>
const fs = require("fs");
const path = require("path");

const ORIGINAL = "terraformingmars";
const name = process.argv[2];
if (!name || !/^[a-z0-9]+$/.test(name)) {
  console.error("Usage: node misc/other/stage.js <bga_project_name>  (lowercase letters and digits only)");
  process.exit(1);
}

const root = path.resolve(__dirname, "../..");
const out = path.join(root, "dist", name);

// keep in sync with the ignore list in .vscode/sftp.json
const ignore = new Set([".git", ".settings", ".project", "node_modules", "modules/tests", "tests", ".vscode", "dist"]);
const textExt = new Set([".php", ".js", ".ts", ".css", ".scss", ".tpl", ".json", ".sql", ".csv", ".md", ".txt", ".html"]);

function copyDir(rel) {
  for (const entry of fs.readdirSync(path.join(root, rel), { withFileTypes: true })) {
    const srcRel = rel ? `${rel}/${entry.name}` : entry.name;
    if (ignore.has(srcRel)) continue;
    const dstRel = srcRel.split(ORIGINAL).join(name);
    const src = path.join(root, srcRel);
    const dst = path.join(out, dstRel);
    if (entry.isDirectory()) {
      fs.mkdirSync(dst, { recursive: true });
      copyDir(srcRel);
    } else if (textExt.has(path.extname(entry.name).toLowerCase())) {
      fs.writeFileSync(dst, fs.readFileSync(src, "utf8").split(ORIGINAL).join(name));
    } else {
      fs.copyFileSync(src, dst);
    }
  }
}

fs.rmSync(out, { recursive: true, force: true });
fs.mkdirSync(out, { recursive: true });
copyDir("");
console.log(`Staged ${ORIGINAL} as ${name} in ${path.relative(root, out)}`);
