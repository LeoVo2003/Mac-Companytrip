import fs from "node:fs";
import path from "node:path";

const token = process.env.GH_TOKEN;
if (!token) {
  console.error("Set GH_TOKEN first: $env:GH_TOKEN = '<token>'");
  process.exit(1);
}

const repo = "LeoVo2003/Mac-Companytrip";
const tag = "v1.9.3";
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
  "- Bàn điều khiển công bố thêm nút Ẩn/Hiện điểm trên màn chiếu (che số bằng •••, màn trình chiếu tự đồng bộ ~1 giây).",
  "- Mở màn tung điểm dâng cao hơn (~19-41% cột thay vì ~11-28%).",
  "- Badge hạng xuất hiện trễ một nhịp: hạng lộ ở bước này thì bước kế tiếp mới gắn badge; bước quán quân gắn đủ badge.",
  "- Bước 03 top 2 bước lên: hạng 3-6 giữ nguyên badge, vị trí, màu sắc — không re-animation (render diff theo từng đội).",
  "- Quán quân giảm từ 10 ô (100%) xuống ~82% cho vừa khung.",
  "- Text mở màn mới: \"6 đội · 4 chặng đường · 1 ngôi vương duy nhất\".",
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
