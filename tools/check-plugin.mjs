import fs from "node:fs";
import path from "node:path";
import PhpParser from "php-parser";

const pluginRoot = path.resolve("mac-companytrip-voting");
const parser = new PhpParser({
  parser: { php7: true, suppressErrors: false },
  ast: { withPositions: true },
});

function walk(directory) {
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const target = path.join(directory, entry.name);
    return entry.isDirectory() ? walk(target) : [target];
  });
}

if (!fs.existsSync(pluginRoot)) throw new Error("Missing mac-companytrip-voting source folder.");
const files = walk(pluginRoot);
const relativeFiles = files.map((file) => path.relative(pluginRoot, file).replaceAll("\\", "/"));
const phpFiles = files.filter((file) => file.endsWith(".php"));
const jsFiles = files.filter((file) => file.endsWith(".js"));
const cssFiles = files.filter((file) => file.endsWith(".css"));

for (const file of phpFiles) {
  const source = fs.readFileSync(file, "utf8");
  if (source.charCodeAt(0) === 0xfeff) throw new Error(`PHP file contains a BOM: ${file}`);
  parser.parseCode(source, file);
  if (/str_contains\s*\(|\b(?:true|false|array|WP_Error|WP_REST_Response)\s*\|/.test(source)) {
    throw new Error(`PHP 8-only syntax/API found in PHP 7.4 package: ${file}`);
  }
}
for (const file of jsFiles) new Function(fs.readFileSync(file, "utf8"));
for (const file of cssFiles) {
  const source = fs.readFileSync(file, "utf8");
  const opens = (source.match(/{/g) || []).length;
  const closes = (source.match(/}/g) || []).length;
  if (opens !== closes) throw new Error(`Unbalanced CSS braces: ${file}`);
}

const resultsCss = fs.readFileSync(path.join(pluginRoot, "assets/results.css"), "utf8");
for (const invariant of ["grid-template-rows: 52px minmax(0, 1fr) 58px;", "flex: 0 0 auto;", "margin: 0 0 8px;"]) {
  if (!resultsCss.includes(invariant)) throw new Error(`Podium label must remain in flow above the bar: ${invariant}`);
}
if (resultsCss.includes("top: max(18px, calc(100% - var(--bar-level) - 34px));")) {
  throw new Error("Podium label must not use the overlapping absolute position.");
}

const required = [
  "mac-companytrip-voting.php",
  "readme.txt",
  "uninstall.php",
  "assets/public.js",
  "assets/public.css",
  "assets/results.js",
  "assets/results.css",
  "assets/admin.js",
  "assets/admin.css",
  "assets/admin-qr.css",
  "assets/ui-refinements.css",
  "assets/qrcode.bundle.js",
  "assets/qrcode.LICENSE.txt",
  "assets/jsqr.js",
  "assets/jsqr.LICENSE.txt",
  "assets/checkin.js",
  "assets/checkin.css",
  "assets/mac-marketing-logo.png",
  "assets/fonts/inter-latin.woff2",
  "assets/fonts/inter-vietnamese.woff2",
  "assets/fonts/INTER-LICENSE.txt",
  "includes/class-mac-voting-db.php",
  "includes/class-mac-voting-auth.php",
  "includes/class-mac-voting-qr.php",
  "includes/class-mac-checkin.php",
  "includes/class-mac-points.php",
  "includes/class-mac-checkin-rest.php",
  "includes/class-mac-checkin-public.php",
  "includes/class-mac-voting-rest.php",
  "includes/class-mac-voting-public.php",
  "includes/class-mac-voting-admin.php",
  "includes/class-mac-voting-updater.php",
];
for (const item of required) {
  if (!relativeFiles.includes(item)) throw new Error(`Missing plugin file: ${item}`);
}

const mainFile = fs.readFileSync(path.join(pluginRoot, "mac-companytrip-voting.php"), "utf8");
for (const header of ["Plugin Name:", "Version:", "Requires at least: 6.0", "Requires PHP: 7.4"]) {
  if (!mainFile.includes(header)) throw new Error(`Missing or invalid plugin header: ${header}`);
}
if (!mainFile.includes("Hệ thống chấm điểm")) throw new Error("Main plugin header is not valid UTF-8 Vietnamese.");
if (!mainFile.includes("add_action('init', 'mac_voting_maybe_upgrade', 5)")) {
  throw new Error("Database upgrade must run on init after WP_Rewrite is available.");
}
if (!mainFile.includes("MAC_VOTING_GITHUB_REPO") || !mainFile.includes("MAC_Voting_Updater::init()")) {
  throw new Error("GitHub auto-update must be wired in the main plugin file.");
}

const packageJson = JSON.parse(fs.readFileSync(path.resolve("package.json"), "utf8"));
const versionMatch = mainFile.match(/\*\s*Version:\s*([0-9.]+)/);
if (!versionMatch) throw new Error("Missing Version header.");
if (packageJson.version !== versionMatch[1] || !mainFile.includes(`define('MAC_VOTING_VERSION', '${versionMatch[1]}')`)) {
  throw new Error("package.json, plugin header, and MAC_VOTING_VERSION must stay in sync.");
}

const updaterFile = fs.readFileSync(path.join(pluginRoot, "includes/class-mac-voting-updater.php"), "utf8");
for (const invariant of ["pre_set_site_transient_update_plugins", "auto_update_plugin", "releases/latest", "mac-companytrip-voting-v", "mac_voting_github_repo"]) {
  if (!updaterFile.includes(invariant)) throw new Error(`Missing GitHub updater invariant: ${invariant}`);
}

const databaseFile = fs.readFileSync(path.join(pluginRoot, "includes/class-mac-voting-db.php"), "utf8");
const restFile = fs.readFileSync(path.join(pluginRoot, "includes/class-mac-voting-rest.php"), "utf8");
const checkinRest = fs.readFileSync(path.join(pluginRoot, "includes/class-mac-checkin-rest.php"), "utf8");
const qrFile = fs.readFileSync(path.join(pluginRoot, "includes/class-mac-voting-qr.php"), "utf8");
const adminFile = fs.readFileSync(path.join(pluginRoot, "includes/class-mac-voting-admin.php"), "utf8");
const publicJs = fs.readFileSync(path.join(pluginRoot, "assets/public.js"), "utf8");
const adminJs = fs.readFileSync(path.join(pluginRoot, "assets/admin.js"), "utf8");
const resultsJs = fs.readFileSync(path.join(pluginRoot, "assets/results.js"), "utf8");
for (const invariant of ["one_valid_ballot", "email varchar(190) NULL", "UNIQUE KEY email", "qr_version", "revote_grants", "audit", "table('checkpoints')", "one_checkin", "table('point_categories')"]) {
  if (!databaseFile.includes(invariant)) throw new Error(`Missing database invariant: ${invariant}`);
}
for (const invariant of ["COMPANY_EMAIL_DOMAIN = 'macusaone.com'", "normalize_company_email", "WHERE v.email=%s"]) {
  if (!databaseFile.includes(invariant) && !restFile.includes(invariant)) throw new Error(`Missing company email login rule: ${invariant}`);
}
for (const invariant of ["@macusaone.com", "JSON.stringify({ username })", "autocomplete=\"username\""]) {
  if (!publicJs.includes(invariant)) throw new Error(`Missing simplified email login UI: ${invariant}`);
}
if (/phoneLast4|search\?q=|mv-phone|mv-name/.test(publicJs)) {
  throw new Error("Legacy name and phone login must not remain in the public UI.");
}
for (const invariant of ["selectedPerformanceId", "mv-team-tabs", "mv-star-rating", "score / 10"]) {
  if (!publicJs.includes(invariant)) throw new Error(`Missing team selection or star score UI: ${invariant}`);
}
const publicCss = fs.readFileSync(path.join(pluginRoot, "assets/public.css"), "utf8");
for (const invariant of [".mv-team-tabs", ".mv-star", ".mv-star.is-active i"]) {
  if (!publicCss.includes(invariant)) throw new Error(`Missing team selection or star score styles: ${invariant}`);
}
for (const invariant of ['["Email", ["email", "mail", "email cong ty"]]', "email phải thuộc @macusaone.com"]) {
  if (!adminJs.includes(invariant)) throw new Error(`Missing email CSV validation: ${invariant}`);
}
for (const invariant of ["Bạn không thể chấm tiết mục của team mình", "status='VALID'", "active_key", "round_status"]) {
  if (!restFile.includes(invariant)) throw new Error(`Missing vote protection: ${invariant}`);
}
for (const invariant of ["$is_decoy_featured", "$minimum_revealed_rank", "$show_score = $is_decoy_featured || $is_rank_revealed"]) {
  if (!restFile.includes(invariant)) throw new Error(`Missing reveal score rule: ${invariant}`);
}
for (const invariant of ["'DECOY' => 'THIRD'", "'THIRD' => 'SECOND'", "'SECOND' => 'FINAL'"]) {
  if (!adminFile.includes(invariant)) throw new Error(`Missing manual podium transition: ${invariant}`);
}
if (!resultsJs.includes('["THIRD", "SECOND", "FINAL"].includes(state.stage)') || resultsJs.includes("finalFrame")) {
  throw new Error("Podium ranks must render from three explicit admin stages without an automatic timer.");
}
for (const invariant of ["is_voting_enabled", "voting_disabled"]) {
  if (!databaseFile.includes(invariant) && !restFile.includes(invariant)) throw new Error(`Missing voting gate: ${invariant}`);
}
for (const invariant of ["/checkin/scan", "/checkin/bootstrap"]) {
  if (!checkinRest.includes(invariant)) throw new Error(`Missing check-in route: ${invariant}`);
}
for (const invariant of ["token_for_voter", "company-trip/q/"]) {
  if (!qrFile.includes(invariant)) throw new Error(`Missing personal QR helper: ${invariant}`);
}
if (!publicJs.includes("lockedView") || !publicJs.includes("enabled === false") || !publicJs.includes('get("from") === "qr"')) {
  throw new Error("Missing locked voting gate or QR confirm login UI.");
}
if (!adminJs.includes("mac_vote_gate") || !adminJs.includes("data-checkpoint") || !adminJs.includes("data-qr-view") || !adminJs.includes("mac_vote_points") || !adminJs.includes("data-tab=\"overview\"") || !adminJs.includes("data-tab=\"art\"") || !adminJs.includes("data-award-points")) {
  throw new Error("Missing admin controls for voting gate, checkpoints, personal QR, or total points.");
}

const unexpected = relativeFiles.filter((file) => /(^|\/)(node_modules|src|dist|\.git)(\/|$)/.test(file));
if (unexpected.length) throw new Error(`Unexpected package content: ${unexpected.join(", ")}`);

const totalBytes = files.reduce((total, file) => total + fs.statSync(file).size, 0);
console.log(`Plugin source OK: ${phpFiles.length} PHP, ${jsFiles.length} JS, ${cssFiles.length} CSS, ${totalBytes} bytes.`);
