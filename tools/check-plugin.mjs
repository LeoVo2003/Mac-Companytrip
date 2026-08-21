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
for (const invariant of ["grid-template-rows: minmax(0, 1fr) 64px 40px;", "flex: 0 0 auto;", "margin: 0 0 8px;"]) {
  if (!resultsCss.includes(invariant)) throw new Error(`Podium label must remain in flow above the bar: ${invariant}`);
}
for (const invariant of ["grid-row: 3;", "grid-row: 2;", "grid-row: 1;"]) {
  if (!resultsCss.includes(invariant)) throw new Error(`Score row must stay pinned to the bottom of each column: ${invariant}`);
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
  "includes/class-mac-games.php",
  "includes/class-mac-checkin-rest.php",
  "includes/class-mac-checkin-public.php",
  "includes/class-mac-voting-rest.php",
  "includes/class-mac-voting-public.php",
  "includes/class-mac-voting-admin.php",
  "includes/class-mac-admin-public.php",
  "includes/class-mac-admin-rest.php",
  "includes/class-mac-voting-updater.php",
  "includes/template-admin-page.php",
  "assets/admin-login.js",
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
const checkinFile = fs.readFileSync(path.join(pluginRoot, "includes/class-mac-checkin.php"), "utf8");
const restFile = fs.readFileSync(path.join(pluginRoot, "includes/class-mac-voting-rest.php"), "utf8");
const checkinRest = fs.readFileSync(path.join(pluginRoot, "includes/class-mac-checkin-rest.php"), "utf8");
const qrFile = fs.readFileSync(path.join(pluginRoot, "includes/class-mac-voting-qr.php"), "utf8");
const adminFile = fs.readFileSync(path.join(pluginRoot, "includes/class-mac-voting-admin.php"), "utf8");
const publicFile = fs.readFileSync(path.join(pluginRoot, "includes/class-mac-voting-public.php"), "utf8");
const publicJs = fs.readFileSync(path.join(pluginRoot, "assets/public.js"), "utf8");
const adminJs = fs.readFileSync(path.join(pluginRoot, "assets/admin.js"), "utf8");
const resultsJs = fs.readFileSync(path.join(pluginRoot, "assets/results.js"), "utf8");
const pointsFile = fs.readFileSync(path.join(pluginRoot, "includes/class-mac-points.php"), "utf8");
const gamesFile = fs.readFileSync(path.join(pluginRoot, "includes/class-mac-games.php"), "utf8");
for (const invariant of ["one_valid_ballot", "email varchar(190) NULL", "UNIQUE KEY email", "qr_version", "revote_grants", "audit", "table('checkpoints')", "one_checkin", "table('thidua_rounds')", "table('checkpoint_windows')", "table('exemptions')", "table('games')"]) {
  if (!databaseFile.includes(invariant)) throw new Error(`Missing database invariant: ${invariant}`);
}
for (const invariant of ["CHECKIN_MAX_PER_CHECKPOINT = 150", "CHECKIN_WINDOW_MINUTES = 15", "RANK_LADDER = array(50, 40, 30, 20, 10, 0)"]) {
  if (!databaseFile.includes(invariant)) throw new Error(`Missing scoring constant invariant: ${invariant}`);
}
for (const invariant of ["window_locked", "WINDOW_LOCKED", "set_exemption", "team_window"]) {
  if (!checkinFile.includes(invariant)) throw new Error(`Missing 15-minute window or exemption invariant: ${invariant}`);
}
for (const invariant of ["SOURCE = 'GAME'", "RANK_LADDER", "GAME_RANK_SET", "function board"]) {
  if (!gamesFile.includes(invariant)) throw new Error(`Missing big-game ranking invariant: ${invariant}`);
}
for (const invariant of ["max_points int(11) unsigned NOT NULL DEFAULT 0", "CHECKIN_PROPORTIONAL", "round(($max_points * $row['checkedIn']) / $row['eligible'])"]) {
  if (!databaseFile.includes(invariant) && !checkinFile?.includes(invariant)) throw new Error(`Missing proportional check-in scoring invariant: ${invariant}`);
}
for (const invariant of ["COMPANY_EMAIL_DOMAIN = 'macusaone.com'", "normalize_company_email", "WHERE v.email=%s"]) {
  if (!databaseFile.includes(invariant) && !restFile.includes(invariant)) throw new Error(`Missing company email login rule: ${invariant}`);
}
for (const invariant of ["@macusaone.com", "yesoffice.vn", "macmarketing.vn", "JSON.stringify({ username, domain })", "autocomplete=\"username\""]) {
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
for (const invariant of ['["Email", ["email", "mail", "email cong ty"]]', "email phải thuộc @macusaone.com, @yesoffice.vn hoặc @macmarketing.vn", "macmarketing\\.vn"]) {
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
if (!resultsJs.includes('["RANK65", "TEASE43", "RANK43", "RANK12", "TWIST", "REVEAL3", "FINAL"].includes(state.stage)')) {
  throw new Error("Total reveal must render from seven explicit admin stages without an automatic timer.");
}
for (const invariant of ["RANK65: { 6: 8, 5: 8 }", "TEASE43: { 6: 8, 5: 8 }", "RANK43: { 6: 8, 5: 8, 4: 8 }", "TWIST: { 6: 5, 5: 5, 4: 5 }", "REVEAL3: { 6: 3, 5: 3, 4: 3, 3: 5 }", "FINAL: { 6: 3, 5: 3, 4: 3, 3: 5, 2: 6.5, 1: 8.5 }", "KHUYẾN KHÍCH"]) {
  if (!resultsJs.includes(invariant)) throw new Error(`Missing total reveal ladder invariant: ${invariant}`);
}
for (const banned of ["mr-chart-lines", "mr-horizon", ".mr-column::after", "repeating-linear-gradient(to top"]) {
  if (resultsCss.includes(banned)) throw new Error(`Banned results-screen decoration must stay removed: ${banned}`);
}
if (resultsJs.includes("mr-chart-lines")) {
  throw new Error("Banned results-screen decoration must stay removed: mr-chart-lines markup in results.js.");
}
if (resultsJs.includes("<b>#${team.number}</b>")) {
  throw new Error("Team number tags must stay removed from the results screen.");
}
for (const invariant of ["is-score-hero", "is-scores-hidden", "setTimeout(() => { pyroStop = startPyro(); }, 3000)"]) {
  if (!resultsJs.includes(invariant)) throw new Error(`Missing results-screen refinement: ${invariant}`);
}
if (!resultsCss.includes(".mac-results-app.is-scores-hidden .mr-score") || !resultsCss.includes(".mr-team.is-score-hero .mr-score span")) {
  throw new Error("Missing growth-score or hidden-score styles in results.css.");
}
if (!adminJs.includes('["reveal", "Công bố"]') || !adminJs.includes("Bàn điều khiển công bố")) {
  throw new Error("Total reveal control must live in its own Công bố tab of the overview.");
}
if (!adminFile.includes("dashboard()['teams']") || !restFile.includes("dashboard()['teams']")) {
  throw new Error("Total reveal snapshot must read the teams slice of the dashboard board.");
}
for (const invariant of ["'RANK65' => 'TEASE43'", "'TEASE43' => 'RANK43'", "'RANK12' => 'TWIST'", "'TWIST' => 'REVEAL3'", "'REVEAL3' => 'FINAL'", "RESULTS_TOTAL_REVEAL_"]) {
  if (!adminFile.includes(invariant)) throw new Error(`Missing total reveal admin transition: ${invariant}`);
}
if (!restFile.includes("/results-total") || !restFile.includes("function results_total")) {
  throw new Error("Missing public total-results endpoint.");
}
if (!adminJs.includes('data-total-reveal-stage') || !adminJs.includes("mac_vote_reveal_total")) {
  throw new Error("Missing total reveal MC controls on the dashboard.");
}
// v1.9.12: /ket-qua-tong = màn tổng kết, /ket-qua-van-nghe = màn văn nghệ (đua thuyền).
for (const invariant of ["mac_companytrip_total_results", "mac_companytrip_art_results", "ket-qua-tong", "total_page_url"]) {
  if (!publicFile.includes(invariant) && !databaseFile.includes(invariant)) {
    throw new Error(`Missing split results-page invariant: ${invariant}`);
  }
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
if (!adminJs.includes("mac_vote_gate") || !adminJs.includes("data-checkpoint") || !adminJs.includes("data-qr-view") || !adminJs.includes("mac_vote_points") || !adminJs.includes("data-tab=\"overview\"") || !adminJs.includes("data-tab=\"art\"") || !adminJs.includes("data-award-points") || !adminJs.includes("data-overview-tab") || !adminJs.includes("canWrite") || !adminJs.includes("Quét QR check-in")) {
  throw new Error("Missing admin controls for voting gate, checkpoints, personal QR, total points, or role-based dashboard.");
}
if (!pointsFile.includes("function history") || !pointsFile.includes("CHECKPOINT_POINTS_FINALIZED") || !pointsFile.includes("function reset_history") || !pointsFile.includes("SOURCE = 'THIDUA'") || !pointsFile.includes("table('thidua_rounds')")) {
  throw new Error("Missing total-points history ledger.");
}

const adminPublic = fs.readFileSync(path.join(pluginRoot, "includes/class-mac-admin-public.php"), "utf8");
const adminRest = fs.readFileSync(path.join(pluginRoot, "includes/class-mac-admin-rest.php"), "utf8");
const adminLoginJs = fs.readFileSync(path.join(pluginRoot, "assets/admin-login.js"), "utf8");
for (const invariant of ["company-trip-admin", "mac_companytrip_admin", "redirect_staff_from_wp_admin", "standalone_template"]) {
  if (!databaseFile.includes(invariant) && !adminPublic.includes(invariant)) {
    throw new Error(`Missing public admin dashboard invariant: ${invariant}`);
  }
}
for (const invariant of ["/admin/login", "wp_signon", "MAC_Checkin::CAP"]) {
  if (!adminRest.includes(invariant)) throw new Error(`Missing admin login route: ${invariant}`);
}
if (!adminLoginJs.includes("ma-login-form") || !mainFile.includes("MAC_Admin_REST::init()") || !mainFile.includes("MAC_Admin_Public::init()")) {
  throw new Error("Public admin login must be wired outside wp-admin.");
}
if (!adminFile.includes("csv_staff_kind") || !adminFile.includes("ensure_staff_user")) {
  throw new Error("CSV import must create BTC and super-admin staff accounts.");
}
for (const invariant of ["SUPER_ROLE", "SUPER_CAP", "add_role(self::SUPER_ROLE"]) {
  if (!checkinFile.includes(invariant)) {
    throw new Error(`Missing CSV super-admin role invariant: ${invariant}`);
  }
}
if (checkinFile.includes("$user->set_role('administrator')")) {
  throw new Error("CSV super admin must not be promoted to the WordPress administrator role.");
}

const unexpected = relativeFiles.filter((file) => /(^|\/)(node_modules|src|dist|\.git)(\/|$)/.test(file));
if (unexpected.length) throw new Error(`Unexpected package content: ${unexpected.join(", ")}`);

// --- Bộ test 12 trường hợp trùng điểm tổng (mirror thuật toán rank + lộ hạng của results_total) ---
for (const invariant of ["&& (int) $row['rank'] >= $protect_rank;", "'RANK43' => 3,", "'RANK43' => 4,", "'TWIST' => 4,", "'REVEAL3' => 4,"]) {
  if (!restFile.includes(invariant)) throw new Error(`Missing twist-script tie rule in results_total: ${invariant}`);
}
for (const [file, invariant] of [[adminFile, "total_tie_warnings"], [adminJs, "totalTieWarnings"], [resultsJs, "rankHeadline"]]) {
  if (!file.includes(invariant)) throw new Error(`Missing tie-handling feature: ${invariant}`);
}
const tieRanks = (totals) => {
  const sorted = [...totals].sort((a, b) => b - a);
  const ranks = [];
  let rank = 0;
  let previous = null;
  sorted.forEach((total, index) => {
    if (previous === null || previous !== total) rank = index + 1;
    ranks.push(rank);
    previous = total;
  });
  return ranks;
};
const tieRevealed = (totals, stage) => {
  const sorted = [...totals].sort((a, b) => b - a);
  const ranks = tieRanks(totals);
  const count = sorted.length;
  const fromBottom = { RANK65: 2, TEASE43: 2, RANK43: 3, RANK12: 4, TWIST: 3, REVEAL3: 4, FINAL: count }[stage] ?? 0;
  if (!fromBottom) return [];
  const threshold = sorted[Math.max(0, count - Math.min(fromBottom, count))];
  const protectRank = { RANK65: 3, TEASE43: 3, RANK43: 4, RANK12: 3, TWIST: 4, REVEAL3: 3, FINAL: 1 }[stage] ?? 99;
  return sorted.flatMap((total, index) => (total <= threshold && ranks[index] >= protectRank ? [index] : []));
};
const TIE_CASES = [
  { name: "TC01", totals: [980, 940, 900, 850, 800, 750], ranks: [1, 2, 3, 4, 5, 6] },
  { name: "TC02", totals: [950, 950, 900, 850, 800, 750], ranks: [1, 1, 3, 4, 5, 6] },
  { name: "TC03", totals: [950, 950, 950, 850, 800, 750], ranks: [1, 1, 1, 4, 5, 6] },
  { name: "TC04", totals: [950, 950, 950, 950, 800, 750], ranks: [1, 1, 1, 1, 5, 6] },
  { name: "TC05", totals: [950, 950, 950, 950, 950, 750], ranks: [1, 1, 1, 1, 1, 6] },
  { name: "TC06", totals: [900, 900, 900, 900, 900, 900], ranks: [1, 1, 1, 1, 1, 1] },
  { name: "TC07", totals: [1000, 900, 900, 850, 800, 750], ranks: [1, 2, 2, 4, 5, 6] },
  { name: "TC08", totals: [1000, 950, 900, 850, 800, 800], ranks: [1, 2, 3, 4, 5, 5] },
  { name: "TC09", totals: [1000, 900, 900, 900, 800, 700], ranks: [1, 2, 2, 2, 5, 6] },
  { name: "TC10", totals: [1000, 1000, 900, 900, 800, 800], ranks: [1, 1, 3, 3, 5, 5] },
  { name: "TC11", totals: [1000, 1000, 900, 800, 800, 800], ranks: [1, 1, 3, 4, 4, 4] },
  { name: "TC12", totals: [1000, 900, 900, 800, 800, 800], ranks: [1, 2, 2, 4, 4, 4] },
];
const REVEAL_EXPECT = {
  TC01: { RANK65: 2, RANK43: 3, TWIST: 3, REVEAL3: 4 },
  TC02: { RANK65: 2, RANK43: 3, TWIST: 3, REVEAL3: 4 },
  TC03: { RANK65: 2, RANK43: 3, TWIST: 3, REVEAL3: 3 },
  TC04: { RANK65: 2, RANK43: 2, TWIST: 2, REVEAL3: 2 },
  TC05: { RANK65: 1, RANK43: 1, TWIST: 1, REVEAL3: 1 },
  TC06: { RANK65: 0, RANK43: 0, TWIST: 0, REVEAL3: 0 },
  TC07: { RANK65: 2, RANK43: 3, TWIST: 3, REVEAL3: 3 },
  TC08: { RANK65: 2, RANK43: 3, TWIST: 3, REVEAL3: 4 },
  TC09: { RANK65: 2, RANK43: 2, TWIST: 2, REVEAL3: 2 },
  TC10: { RANK65: 2, RANK43: 2, TWIST: 2, REVEAL3: 4 },
  TC11: { RANK65: 3, RANK43: 3, TWIST: 3, REVEAL3: 4 },
  TC12: { RANK65: 3, RANK43: 3, TWIST: 3, REVEAL3: 3 },
};
for (const tc of TIE_CASES) {
  const ranks = tieRanks(tc.totals);
  if (ranks.join(",") !== tc.ranks.join(",")) {
    throw new Error(`${tc.name}: tính hạng sai — được ${ranks.join(",")}, mong đợi ${tc.ranks.join(",")}.`);
  }
  const minRankByStage = { RANK65: 3, TEASE43: 3, RANK43: 4, RANK12: 3, TWIST: 4, REVEAL3: 3 };
  for (const [stage, minimumRank] of Object.entries(minRankByStage)) {
    for (const index of tieRevealed(tc.totals, stage)) {
      if (ranks[index] < minimumRank) throw new Error(`${tc.name}/${stage}: hạng ${ranks[index]} bị lộ sớm hơn bước cho phép (tối thiểu hạng ${minimumRank}).`);
    }
  }
  if (tieRevealed(tc.totals, "FINAL").length !== tc.totals.length) {
    throw new Error(`${tc.name}/FINAL: phải lộ đủ ${tc.totals.length} đội.`);
  }
  for (const [stage, expected] of Object.entries(REVEAL_EXPECT[tc.name])) {
    const revealed = tieRevealed(tc.totals, stage).length;
    if (revealed !== expected) throw new Error(`${tc.name}/${stage}: lộ ${revealed} đội, mong đợi ${expected}.`);
  }
}

const totalBytes = files.reduce((total, file) => total + fs.statSync(file).size, 0);
console.log(`Plugin source OK: ${phpFiles.length} PHP, ${jsFiles.length} JS, ${cssFiles.length} CSS, ${totalBytes} bytes. Tie tests: ${TIE_CASES.length} cases passed.`);
