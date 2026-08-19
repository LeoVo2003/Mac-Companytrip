import fs from "node:fs";
import path from "node:path";

const token = process.env.GH_TOKEN;
if (!token) {
  console.error("Set GH_TOKEN first: $env:GH_TOKEN = '<token>'");
  process.exit(1);
}

const repo = "LeoVo2003/Mac-Companytrip";
const tag = "v1.7.1";
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
  "- Sidebar 6 tab: Tổng quan, Check-in, Trò chơi lớn, Văn nghệ, Thi đua, Nhân sự & QR; Tổng quan chỉ còn Tổng điểm + Lịch sử.",
  "- Thứ tự Tổng quan: biểu đồ cột → bảng 4 trụ cột → check-in → trò chơi → văn nghệ → thi đua.",
  "- Mở mốc / mở vote chỉ xác nhận một lần; thời gian tự đóng (15' / 5') sửa ở ô nhập cạnh nút.",
  "- Bảng tỷ lệ có mặt gom thành 1 ma trận đội × mốc (số người + điểm).",
  "- Tab Trò chơi lớn làm lại: thang hạng, ma trận tổng, thẻ chấm hạng từng game.",
  "- Đồng bộ button, hover, màu chữ và bảng trên toàn dashboard.",
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
  if (asset.name === assetName) {
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
