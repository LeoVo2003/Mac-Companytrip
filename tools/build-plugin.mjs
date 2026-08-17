import fs from "node:fs";
import path from "node:path";
import archiver from "archiver";

const source = path.resolve("mac-companytrip-voting");
const mainFile = fs.readFileSync(path.join(source, "mac-companytrip-voting.php"), "utf8");
const versionMatch = mainFile.match(/\*\s*Version:\s*([0-9.]+)/);
if (!versionMatch) throw new Error("Missing Version header in mac-companytrip-voting.php");
const version = versionMatch[1];
const outputDirectory = path.resolve("dist");
const outputFile = path.join(outputDirectory, `mac-companytrip-voting-v${version}.zip`);

fs.mkdirSync(outputDirectory, { recursive: true });
for (const file of fs.readdirSync(outputDirectory)) {
  if (/^mac-companytrip-voting-v[\d.]+\.zip$/.test(file)) fs.rmSync(path.join(outputDirectory, file));
}
if (fs.existsSync(outputFile)) fs.rmSync(outputFile);
const legacyOutput = path.join(outputDirectory, "mac-companytrip-voting.zip");
if (fs.existsSync(legacyOutput)) fs.rmSync(legacyOutput);

const stream = fs.createWriteStream(outputFile);
const archive = archiver("zip", { zlib: { level: 9 } });
const finished = new Promise((resolve, reject) => {
  stream.on("close", resolve);
  stream.on("error", reject);
  archive.on("error", reject);
  archive.on("warning", (error) => {
    if (error.code !== "ENOENT") reject(error);
  });
});

archive.pipe(stream);
archive.directory(source, "mac-companytrip-voting", (entry) => {
  entry.name = entry.name.replaceAll("\\", "/");
  return entry;
});
await archive.finalize();
await finished;

console.log(`Built ${outputFile} (${archive.pointer()} bytes).`);
