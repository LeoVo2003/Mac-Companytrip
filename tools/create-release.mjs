import fs from "node:fs";
import path from "node:path";

const token = process.env.GH_TOKEN;
if (!token) {
  console.error("Set GH_TOKEN first: $env:GH_TOKEN = '<token>'");
  process.exit(1);
}

const repo = "LeoVo2003/Mac-Companytrip";
const tag = "v1.9.0";
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
  "- Màn công bố tổng điểm chung cuộc \"Race to the Crown\" tại /ket-qua-tong/: màn chiếu dark toàn màn hình, đồng bộ ~1 giây.",
  "- Kịch bản 13 trạng thái: khóa snapshot → mở dần 4 trụ cột → khóa → loại dần → Top 3 → hạng ba → FINAL DUEL chạy số → á quân → quán quân → bảng tổng sắp.",
  "- Hỗ trợ đồng hạng theo nhóm hạng (1,1,3): phase thiếu nhóm hạng tự bỏ qua, quán quân có thể đồng hạng; LÙI 1 bước + đặt lại kèm xác nhận.",
  "- Tổng quan thêm sub-tab Chung kết cho super admin điều khiển; đặt lại sự kiện xóa luôn snapshot màn tổng.",
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
