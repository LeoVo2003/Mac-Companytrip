import fs from "node:fs";
import path from "node:path";

const token = process.env.GH_TOKEN;
if (!token) {
  console.error("Set GH_TOKEN first: $env:GH_TOKEN = '<token>'");
  process.exit(1);
}

const repo = "LeoVo2003/Mac-Companytrip";
const tag = "v1.8.1";
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
  "- Bỏ tự reload 5 giây ở dashboard: kéo bảng không bị giật về, đang gõ không mất chữ, inspect element không bị render lại.",
  "- Thanh tab trên tablet/mobile giờ dính cứng khi cuộn, tab đang chọn luôn tự cuộn ra giữa thanh.",
  "- Mobile: bảng tổng kết bỏ cột hạng chỉ còn cột đội; cột đội của các bảng chồng nhau cùng rộng 180px cho thẳng hàng.",
  "- Tìm nhanh trong miễn check-in giữ nguyên từ khóa sau mỗi lần tải lại dữ liệu.",
  "- Ô nhập RESET hết nhảy chữ khi gõ tiếng Việt (không ghi đè ô nhập lúc đang gõ, bỏ autocapitalize).",
  "- Nút Màn hình trình chiếu trên mobile xuống hàng full-width như nút Mở máy quét / Xuất CSV.",
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
