import fs from "node:fs";
import path from "node:path";

const token = process.env.GH_TOKEN;
if (!token) {
  console.error("Set GH_TOKEN first: $env:GH_TOKEN = '<token>'");
  process.exit(1);
}

const repo = "LeoVo2003/Mac-Companytrip";
const tag = "v1.9.11";
const assetPath = path.resolve("dist", `mac-companytrip-voting-${tag}.zip`);
if (!fs.existsSync(assetPath)) {
  console.error(`Missing ${assetPath} — run "npm run build" first.`);
  process.exit(1);
}

const headers = {
  Authorization: `Bearer ${token}`,
  Accept: "application/vnd.github+json",
  "User-Agent": "mac-release-script",
};

const notes = [
  "- Badge rút gọn còn \"KHUYẾN KHÍCH\".",
  "- Ẩn điểm: tên đội giãn xuống thế chỗ khối điểm, hết khoảng trống dưới đáy.",
  "- Thang độ cao mới: bước 02 hạng 4-5-6 cùng 80%; twist 1-2-3 dao động 45-60% còn 4-5-6 giữ 50%; hiện top 3: 4-5-6 về 30%, hạng 3 = 50%, 1-2 dao động 50-90%; quán quân 85% · nhì 65%.",
].join("\n");

let release;
let response = await fetch(`https://api.github.com/repos/${repo}/releases/tags/${tag}`, { headers });
if (response.ok) {
  release = await response.json();
  console.log(`Release ${tag} already exists (id ${release.id}).`);
} else {
  response = await fetch(`https://api.github.com/repos/${repo}/releases`, {
    method: "POST",
    headers: { ...headers, "Content-Type": "application/json" },
    body: JSON.stringify({ tag_name: tag, target_commitish: "main", name: tag, body: notes, draft: false, prerelease: false }),
  });
  if (!response.ok) throw new Error(`Create release failed: ${response.status} ${await response.text()}`);
  release = await response.json();
  console.log(`Created release ${tag} (id ${release.id}).`);
}

const assetName = path.basename(assetPath);
for (const asset of release.assets || []) {
  const isPluginZip = /^mac-companytrip-voting-v[\d.]+\.zip$/.test(asset.name);
  if (asset.name === assetName || (isPluginZip && asset.name !== `mac-companytrip-voting-${tag}.zip`)) {
    await fetch(`https://api.github.com/repos/${repo}/releases/assets/${asset.id}`, { method: "DELETE", headers });
    console.log(`Removed stale asset ${asset.name}.`);
  }
}

response = await fetch(`https://uploads.github.com/repos/${repo}/releases/${release.id}/assets?name=${encodeURIComponent(assetName)}`, {
  method: "POST",
  headers: { ...headers, "Content-Type": "application/zip" },
  body: fs.readFileSync(assetPath),
});
if (!response.ok) throw new Error(`Upload asset failed: ${response.status} ${await response.text()}`);
const uploaded = await response.json();
console.log(`Uploaded: ${uploaded.browser_download_url}`);
console.log(`Release page: https://github.com/${repo}/releases/tag/${tag}`);
