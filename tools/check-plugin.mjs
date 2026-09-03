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
const artRaceCss = fs.readFileSync(path.join(pluginRoot, "assets/art-race.css"), "utf8");
for (const stylesheet of [resultsCss, artRaceCss]) {
  for (const invariant of ['font-family: "Prata"', 'font-family: "Bricolage Grotesque"', 'font-feature-settings: "lnum" 1, "tnum" 1', "font-style: normal;", "font-weight: 400;"]) {
    if (!stylesheet.includes(invariant)) throw new Error(`Event results must use bundled Prata + Bricolage Grotesque typography: ${invariant}`);
  }
  for (const banned of ["Cormorant Garamond", "Fraunces", "Newsreader", "Manrope", "Plus Jakarta Sans", "fonts.googleapis.com", "font-family: Inter"]) {
    if (stylesheet.includes(banned)) throw new Error(`Event results must not retain the replaced font dependency: ${banned}`);
  }
}
for (const invariant of ['font-family: "Prata"']) {
  if (!resultsCss.includes(invariant) && !artRaceCss.includes(invariant)) throw new Error(`Missing bundled Prata face: ${invariant}`);
}
const totalHeadingStyle = resultsCss.match(/\.mr-heading h1\s*\{[^}]+\}/s)?.[0] || "";
if (!totalHeadingStyle.includes('font-family: "Bricolage Grotesque"') || !totalHeadingStyle.includes("font-weight: 500")) {
  throw new Error("Total-results featured team name must use Bricolage Grotesque 500.");
}
const totalRankStyle = resultsCss.match(/\.mr-rank\s*\{[^}]+\}/s)?.[0] || "";
if (!totalRankStyle.includes('font-family: "Bricolage Grotesque"') || !totalRankStyle.includes("font-weight: 600") || !totalRankStyle.includes("text-transform: none")) {
  throw new Error("Total-results rank badges must use Bricolage Grotesque 600 in normal case.");
}
for (const invariant of [".ar-team-copy b {", 'font-family: "Bricolage Grotesque"', '.ar-shell[data-stage="final"] .ar-title h1']) {
  if (!artRaceCss.includes(invariant)) throw new Error(`Art results must keep scores/ranks readable while reserving Prata for display: ${invariant}`);
}
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
  "assets/fonts/prata-latin-400-normal.woff2",
  "assets/fonts/prata-vietnamese-400-normal.woff2",
  "assets/fonts/PRATA-LICENSE.txt",
  "assets/fonts/bricolage-grotesque-latin.woff2",
  "assets/fonts/bricolage-grotesque-latin-ext.woff2",
  "assets/fonts/bricolage-grotesque-vietnamese.woff2",
  "assets/fonts/BRICOLAGE-GROTESQUE-LICENSE.txt",
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
const publicJs = fs.readFileSync(path.join(pluginRoot, "assets/public.js"), "utf8");
const adminJs = fs.readFileSync(path.join(pluginRoot, "assets/admin.js"), "utf8");
const adminCss = fs.readFileSync(path.join(pluginRoot, "assets/admin.css"), "utf8");
const checkinCss = fs.readFileSync(path.join(pluginRoot, "assets/checkin.css"), "utf8");
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
for (const invariant of ["$wpdb->delete($windows", "resetTeamWindows"]) {
  if (!checkinFile.includes(invariant)) throw new Error(`Reopening a checkpoint must reset stale team windows: ${invariant}`);
}
for (const invariant of ["applyPointsPayload", "applyCheckpointPayload", "ma-people-panel"]) {
  if (!adminJs.includes(invariant)) throw new Error(`Missing responsive or lightweight admin update flow: ${invariant}`);
}
for (const invariant of [".ma-people-table tr", ".ma-thidua-summary-copy", ".ma-award-step + .ma-award-step"]) {
  if (!adminCss.includes(invariant)) throw new Error(`Missing Thi dua or personnel responsive styles: ${invariant}`);
}
for (const invariant of ["button.mc-back", "font-size: 14px", "font-weight: 600"]) {
  if (!checkinCss.includes(invariant)) throw new Error(`Check-in back controls must keep shared typography: ${invariant}`);
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
for (const invariant of ["$target_rank", "$revealed_ids", "$current_ids", "(int) $row['rank'] >= $target_rank", "in_array((int) $row['team_id'], $current_ids, true)"]) {
  if (!restFile.includes(invariant)) throw new Error(`Missing tied-rank spotlight reveal rule: ${invariant}`);
}
if (!restFile.includes("WHERE t.team_no<>%d")) throw new Error("The Spotlight must exclude the Hoa tiêu staff team.");
for (const invariant of ["art_reveal_plan", "'nextStage' => $next_stage", "'rankCounts' => $rank_counts", "$plan['nextStage'] !== $next", "artRevealPlan"]) {
  if (!adminFile.includes(invariant)) throw new Error(`Missing dynamic tied-rank spotlight transition: ${invariant}`);
}
for (const invariant of ["revealRankButton", "is-skipped", "Đội đồng điểm được công bố cùng lúc"] ) {
  if (!adminJs.includes(invariant)) throw new Error(`Missing tied-rank reveal admin UI: ${invariant}`);
}
if (!resultsJs.includes('["RANK65", "TEASE43", "RANK43", "RANK12", "TWIST", "REVEAL3", "SECOND", "FINAL"].includes(state.stage)')) {
  throw new Error("Total reveal must render from eight explicit admin stages without an automatic timer.");
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
for (const invariant of ["'RANK65' => 'TEASE43'", "'TEASE43' => 'RANK43'", "'RANK43' => 'TWIST'", "'TWIST' => 'REVEAL3'", "'REVEAL3' => 'SECOND'", "'SECOND' => 'FINAL'", "'RANK12' => 'TWIST'", "RESULTS_TOTAL_REVEAL_"]) {
  if (!adminFile.includes(invariant)) throw new Error(`Missing total reveal admin transition: ${invariant}`);
}
if (!restFile.includes("/results-total") || !restFile.includes("function results_total")) {
  throw new Error("Missing public total-results endpoint.");
}
if (!adminJs.includes("data-total-reveal-stage") || !adminJs.includes("mac_vote_reveal_total")) {
  throw new Error("Missing total reveal MC controls on the dashboard.");
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

// Màn văn nghệ phải công bố theo nhóm hạng tồn tại, bỏ qua mọi hạng bị khuyết.
const ART_STAGE_BY_RANK = { 6: "SIXTH", 5: "FIFTH", 4: "FOURTH", 3: "THIRD", 2: "SECOND", 1: "FINAL" };
for (const tc of TIE_CASES) {
  const ranks = tieRanks(tc.totals);
  const availableRanks = [...new Set(ranks)].sort((a, b) => b - a);
  const stages = availableRanks.map((rank) => ART_STAGE_BY_RANK[rank]);
  for (const rank of availableRanks) {
    const tiedCount = ranks.filter((value) => value === rank).length;
    const currentCount = ranks.filter((value) => value === rank).length;
    const revealedCount = ranks.filter((value) => value >= rank).length;
    if (currentCount !== tiedCount) throw new Error(`${tc.name}: hạng ${rank} phải sáng cùng lúc đủ ${tiedCount} đội.`);
    if (revealedCount < tiedCount) throw new Error(`${tc.name}: hạng ${rank} không được lộ thiếu đội đồng hạng.`);
  }
  for (const rank of [6, 5, 4, 3, 2, 1]) {
    if (!availableRanks.includes(rank) && stages.includes(ART_STAGE_BY_RANK[rank])) {
      throw new Error(`${tc.name}: hạng ${rank} bị khuyết phải được bỏ qua.`);
    }
  }
  if (ranks[0] === 1 && ranks[1] === 1 && !availableRanks.includes(2)) {
    const finalIndex = stages.indexOf("FINAL");
    if (finalIndex < 0 || stages[finalIndex - 1] === "SECOND") throw new Error(`${tc.name}: đồng quán quân phải bỏ qua hạng nhì và đi thẳng FINAL.`);
  }
}

// --- Thi đua = trung bình các hạng mục có ít nhất một team tham gia (0-50), không còn SUM ---
// Team không có record trong hạng mục đang tính được xem là không tham gia và nhận 0đ.
for (const invariant of ["clear_award", "thiduaCompletedRounds", "backfill_legacy_zeros", "isComplete"]) {
  if (!pointsFile.includes(invariant)) throw new Error(`Missing thidua average logic in MAC_Points: ${invariant}`);
}
if (pointsFile.includes("hasDuplicateRanks")) {
  throw new Error("Duplicate ranks must no longer block a thidua category from completing.");
}
if (!adminFile.includes("MAC_Points::clear_award") || !adminFile.includes("'operation' => 'clear'") && !adminFile.includes("$operation === 'clear'")) {
  throw new Error("Admin ajax must expose a dedicated clear operation for thidua scores.");
}
for (const invariant of ["recomputeThidua", "refreshCategoryMeta", "Điểm Thi đua", "operation: \"clear\""]) {
  if (!adminJs.includes(invariant)) throw new Error(`Admin UI must mirror the thidua average logic: ${invariant}`);
}
if (adminJs.includes("thi đua không giới hạn")) {
  throw new Error("Old unlimited thidua copy must be removed from the overview scoreboard.");
}

// --- v1.9.15: test case logic Thi đua (mirror MAC_Points::dashboard) ---
const thiduaOfficial = (categories, teamIds) => {
  const completed = categories.filter((cat) => {
    const values = teamIds.map((id) => cat[id]).filter((v) => v !== undefined);
    return values.length > 0;
  });
  const result = {};
  teamIds.forEach((id) => {
    const raw = completed.reduce((sum, cat) => sum + (cat[id] ?? 0), 0);
    result[id] = completed.length ? Math.max(0, Math.min(50, Math.round(raw / completed.length))) : 0;
  });
  return { result, completedCount: completed.length };
};
const T = [1, 2, 3, 4, 5, 6];
const LADDER_CAT = (m) => ({ 1: m[0], 2: m[1], 3: m[2], 4: m[3], 5: m[4], 6: m[5] });
// Case 1: chưa có hạng mục hoàn tất -> 0/50, không chia cho 0.
if (thiduaOfficial([], T).result[1] !== 0) throw new Error("Thidua case 1 failed.");
// Case 2: 1 hạng mục hoàn tất -> đúng điểm từng team.
{
  const r = thiduaOfficial([LADDER_CAT([50, 40, 30, 20, 10, 0])], T).result;
  if (r[1] !== 50 || r[6] !== 0) throw new Error("Thidua case 2 failed.");
}
// Case 3: 2 hạng mục hoàn tất -> trung bình.
{
  const r = thiduaOfficial([LADDER_CAT([50, 40, 30, 20, 10, 0]), LADDER_CAT([40, 20, 50, 30, 0, 10])], T).result;
  if (r[1] !== 45 || r[2] !== 30) throw new Error("Thidua case 3 failed.");
}
// Case 4 + 5: 10 hạng mục nhưng chỉ 2 có người tham gia -> mẫu số 2; hạng mục trống không kéo điểm.
{
  const cats = [LADDER_CAT([50, 40, 30, 20, 10, 0]), LADDER_CAT([40, 20, 50, 30, 0, 10])];
  for (let i = 0; i < 8; i += 1) cats.push({});
  const out = thiduaOfficial(cats, T);
  if (out.completedCount !== 2 || out.result[1] !== 45) throw new Error("Thidua case 4/5 failed.");
}
// Case 6: 0đ explicit vẫn tính là đã tham gia -> hạng mục được tính.
if (thiduaOfficial([LADDER_CAT([50, 40, 30, 20, 10, 0])], T).completedCount !== 1) throw new Error("Thidua case 6 failed.");
// Case 7 + 8: thiếu record = không tham gia 0đ; hạng mục vẫn được tính cho các team còn lại.
{
  const partial = { 1: 50, 2: 40, 3: 30, 4: 20, 5: 10 };
  const half = { 1: 50, 2: 40, 3: 30 };
  const partialOut = thiduaOfficial([partial], T);
  const halfOut = thiduaOfficial([half], T);
  if (partialOut.completedCount !== 1 || partialOut.result[6] !== 0 || halfOut.completedCount !== 1 || halfOut.result[4] !== 0) throw new Error("Thidua case 7/8 failed.");
}
// Case 9: trùng hạng vẫn được tính và tính trung bình bình thường.
if (thiduaOfficial([{ 1: 50, 2: 50, 3: 40, 4: 30, 5: 20, 6: 0 }], T).completedCount !== 1) throw new Error("Thidua case 9 failed.");
// Case 10: dữ liệu như bảng thực tế 5/6 ở hạng mục thứ ba; team 3 trống nhận 0đ, cả hạng mục vẫn tính.
{
  const out = thiduaOfficial([
    LADDER_CAT([50, 20, 40, 30, 10, 0]),
    LADDER_CAT([40, 30, 20, 10, 50, 0]),
    { 1: 0, 2: 10, 4: 30, 5: 40, 6: 50 },
  ], T);
  if (out.completedCount !== 3 || out.result[1] !== 30 || out.result[3] !== 20 || out.result[6] !== 17) throw new Error("Thidua case 10 non-participant failed.");
}
// Case 11: điểm thi đua luôn kẹp 0-50 (6 record cùng 50 -> trung bình 50, không vượt trần).
if (thiduaOfficial([LADDER_CAT([50, 50, 50, 50, 50, 50])], T).result[1] !== 50) throw new Error("Thidua case 11 clamp failed.");

// --- Màn The Spotlight văn nghệ: tách trang /ket-qua-tong và /ket-qua-van-nghe ---
const publicFile = fs.readFileSync(path.join(pluginRoot, "includes/class-mac-voting-public.php"), "utf8");
const artRaceJs = fs.readFileSync(path.join(pluginRoot, "assets/art-race.js"), "utf8");
for (const invariant of ["mac_companytrip_art_race", "ket-qua-tong", "ket-qua-van-nghe", "mac_companytrip_total_results", "mac_companytrip_art_results"]) {
  if (!publicFile.includes(invariant)) throw new Error(`Missing art-race page wiring in public class: ${invariant}`);
}
for (const invariant of ["mac_voting_art_results_page_id", "ket-qua-tong", "art_results_page_url", "migrate_split_pages"]) {
  if (!databaseFile.includes(invariant)) throw new Error(`Missing art-race page storage in DB class: ${invariant}`);
}
if (!adminFile.includes("artResultsUrl") || !adminJs.includes("artResultsUrl")) {
  throw new Error("Art reveal panel must link to the /ket-qua-van-nghe race screen.");
}
for (const invariant of ["THE SPOTLIGHT", "startSpotlightSearch", "5000 - elapsed", "team.current", "team.revealed", "aimSpots", "ar-dust", "is-decel", "is-light", "Đồng quán quân", "const currents = state.teams.filter"]) {
  if (!artRaceJs.includes(invariant)) throw new Error(`Missing sequential spotlight screen behavior: ${invariant}`);
}
for (const invariant of [".ar-curtain", ".ar-beam", ".ar-team.is-searching", "mac-art-race-page", ".ar-spot", ".ar-offline-overlay", ".ar-floor-ring", ".mac-art-race-app.is-light"]) {
  if (!artRaceCss.includes(invariant)) throw new Error(`Missing spotlight stage style: ${invariant}`);
}
if ((artRaceJs.match(/class="ar-spot"/g) || []).length !== 1) {
  throw new Error("Art reveal must render exactly one moving spotlight.");
}
if (!artRaceJs.includes("dustMarkup") || !artRaceJs.includes("is-search-active") || !artRaceCss.includes(".ar-team.is-searching .ar-dust") || !artRaceCss.includes("@keyframes ar-dust-float")) {
  throw new Error("Each art reveal team must own a dust field controlled by the active spotlight state.");
}
const spotStyle = artRaceCss.match(/\.ar-spot\s*\{[^}]+\}/s)?.[0] || "";
if (!spotStyle.includes("radial-gradient") || !spotStyle.includes("clip-path") || !spotStyle.includes("mask-image")) {
  throw new Error("Moving spotlight must use a masked theatrical cone with softened edges.");
}
const titleStyle = artRaceCss.match(/\.ar-title h1\s*\{[^}]+\}/s)?.[0] || "";
if (!titleStyle.includes("text-shadow: none")) {
  throw new Error("Art reveal title must not use the old red text shadow.");
}
const titleKickerStyle = artRaceCss.match(/\.ar-title p\s*\{[^}]+\}/s)?.[0] || "";
if (!titleKickerStyle.includes("text-shadow: none")) {
  throw new Error("Art reveal searching headline must not use a text shadow.");
}
for (const invariant of ["--ocean-deep", "--wood", "--bronze", "Tone biển sáng", "mask-image: linear-gradient(180deg, transparent, #000 15%, #000)"]) {
  if (!artRaceCss.includes(invariant)) throw new Error(`Missing ocean-voyage stage treatment: ${invariant}`);
}
const runwayStyle = artRaceCss.match(/\.ar-runway\s*\{[^}]+\}/s)?.[0] || "";
for (const invariant of ["border-top", "border-radius: 50% 50% 0 0", "repeating-radial-gradient", "height: 42vh"]) {
  if (!runwayStyle.includes(invariant)) throw new Error(`Missing restored oval stage floor: ${invariant}`);
}
for (const banned of [".ar-stage-world::after", "@keyframes ar-backdrop-breathe"]) {
  if (artRaceCss.includes(banned)) throw new Error(`Restored oval stage must omit gala-stage treatment: ${banned}`);
}
for (const invariant of ["clamp(34px, 3.8vw, 68px)", "clamp(21px, 1.55vw, 30px)", "clamp(34px, 2.7vw, 52px)", "clamp(15px, 1.25vw, 22px)"]) {
  if (!artRaceCss.includes(invariant)) throw new Error(`Missing large-venue typography scale: ${invariant}`);
}
for (const invariant of ["--black: #050403", "rgba(255, 106, 44, 0.58)", "linear-gradient(120deg, var(--red), var(--orange))", "rgba(5, 4, 3, 0.72)"]) {
  if (!artRaceCss.includes(invariant)) throw new Error(`Dark spotlight theme must retain MAC styling: ${invariant}`);
}
for (const invariant of [".ar-team.is-revealed .ar-beam { opacity: 0.54; }", ".ar-team.is-revealed .ar-dust", "Ánh sáng đang gọi tên", "Number(current.rank) !== 2 && searchPool().length", "window.setTimeout(startSpotlightSearch, 3000)", "#000 0 91%", ".ar-team.is-name-long", ".ar-team.is-name-xlong", ".ar-shell.has-connection-alert .ar-offline-overlay", "failedPolls >= 6"]) {
  if (!artRaceCss.includes(invariant) && !artRaceJs.includes(invariant)) throw new Error(`Missing persistent revealed-team lighting behavior: ${invariant}`);
}
const busPhp = fs.readFileSync(path.join(pluginRoot, "includes/class-mac-bus.php"), "utf8");
for (const invariant of ["mac_bus_guide", "bus_rollcall_marks", "BUS_MEMBER_MOVED", "can_rollcall", "FIRST_CHECKPOINT_ID"]) {
  if (!busPhp.includes(invariant)) throw new Error(`Missing bus module behavior: ${invariant}`);
}
for (const invariant of ["one_voter_bus", "one_mark (rollcall_id,bus_member_id)", "CREATE TABLE $buses"]) {
  if (!databaseFile.includes(invariant)) throw new Error(`Missing bus schema: ${invariant}`);
}
const checkinJs = fs.readFileSync(path.join(pluginRoot, "assets/checkin.js"), "utf8");
if (checkinJs.includes("Chọn team để quét")) throw new Error("Scanner must open camera immediately without a team-picking step.");
for (const invariant of ["busAssignment", "mc-accordion", "busAssignmentEnabled"]) {
  if (!checkinJs.includes(invariant)) throw new Error(`Missing scanner bus/accordion behavior: ${invariant}`);
}
for (const invariant of ["Phân xe", "Xe của tôi", "mac_vote_rollcall", "mac_vote_bus_capacity", "mac_vote_bus_close"]) {
  if (!adminJs.includes(invariant)) throw new Error(`Missing bus admin UI behavior: ${invariant}`);
}
for (const invariant of [".ma-reveal-actions button.is-skipped:disabled", "opacity: 0.34", ".ar-shell[data-stage=\"final\"] .ar-rays { opacity: 0.34; }", "mix-blend-mode: screen"]) {
  if (!adminCss.includes(invariant) && !artRaceCss.includes(invariant)) throw new Error(`Missing tied-rank or champion-ray presentation: ${invariant}`);
}
for (const banned of ["class=\"ar-grain\"", ".ar-grain {", "overflow-wrap: anywhere"]) {
  if (artRaceJs.includes(banned) || artRaceCss.includes(banned)) throw new Error(`Projector-only spotlight must omit obsolete visual treatment: ${banned}`);
}
for (const invariant of ["ar-edge ar-edge-left", "ar-edge ar-edge-right", "height: 64%", "rotate(-10deg)", "rotate(10deg)", "linear-gradient(180deg, transparent, var(--red), var(--orange), transparent)"]) {
  if (!artRaceJs.includes(invariant) && !artRaceCss.includes(invariant)) throw new Error(`Missing original red-orange edge line: ${invariant}`);
}
for (const banned of [".ar-edge::before", ".ar-edge::after", "transform-origin: 50% 0"]) {
  if (artRaceCss.includes(banned)) throw new Error(`Original edge line must not include the redesigned lamp ornaments: ${banned}`);
}
if (artRaceJs.includes("SPOTLIGHT ĐANG TÌM KIẾM")) {
  throw new Error("Old searching headline must be replaced with the new reveal copy.");
}

// --- Module Phân xe: port state machine từ MAC_Bus (PHP) để chạy test case thật ---
const busFile = fs.readFileSync(path.join(pluginRoot, "includes/class-mac-bus.php"), "utf8");
for (const invariant of ["function sync_boarding", "function save_capacity", "function close_bus", "function open_bus", "'capacity' => (int) $bus['capacity']"]) {
  if (!busFile.includes(invariant)) throw new Error(`Missing bus capacity or auto-advance invariant: ${invariant}`);
}
for (const invariant of ["mac_vote_bus_capacity", "data-bus-capacity", "data-bus-close", "data-bus-open"]) {
  if (!adminJs.includes(invariant)) throw new Error(`Missing bus capacity input or manual open/close UI: ${invariant}`);
}
if (adminJs.includes("mac_vote_bus_advance")) throw new Error("Legacy manual advance action must stay removed from the admin UI.");
if (!databaseFile.includes("capacity smallint(5) unsigned NOT NULL DEFAULT 40")) throw new Error("Buses table must carry a per-bus capacity column.");
const busModel = (teamOf) => {
  const buses = [1, 2, 3, 4, 5].map((id) => ({ id, status: "WAITING", capacity: 2 }));
  const members = [];
  let memberSeq = 0;
  const boarding = () => buses.find((b) => b.status === "BOARDING") || null;
  const enabled = (checkpoint1Open) => checkpoint1Open && buses.some((b) => b.status !== "CLOSED");
  const count = (busId) => members.filter((m) => m.busId === busId).length;
  // sync_boarding (PHP): xe chạm sức chứa tự chốt; không còn xe nhận thì mở xe WAITING đầu tiên.
  const sync = (checkpoint1Open) => {
    if (!enabled(checkpoint1Open)) return;
    buses.forEach((b) => { if (b.status !== "CLOSED" && count(b.id) >= b.capacity) b.status = "CLOSED"; });
    if (!boarding()) {
      const first = buses.find((b) => b.status === "WAITING");
      if (first) first.status = "BOARDING";
    }
  };
  return {
    buses,
    members,
    boarding,
    sync,
    setCapacity(busId, capacity, checkpoint1Open) {
      const bus = buses.find((b) => b.id === busId);
      if (!bus) return { error: "not_found" };
      bus.capacity = Math.max(1, Math.min(500, capacity));
      sync(checkpoint1Open);
      return { ok: true };
    },
    closeBus(busId, checkpoint1Open) {
      const bus = buses.find((b) => b.id === busId);
      if (!bus) return { error: "not_found" };
      if (bus.status !== "CLOSED") bus.status = "CLOSED";
      sync(checkpoint1Open);
      return { ok: true };
    },
    openBus(busId, checkpoint1Open) {
      const bus = buses.find((b) => b.id === busId);
      if (!bus) return { error: "not_found" };
      if (bus.status === "WAITING") {
        const current = boarding();
        if (current && current.id !== busId) current.status = "WAITING";
        bus.status = "BOARDING";
      } else if (bus.status === "CLOSED") {
        bus.status = "WAITING";
      }
      sync(checkpoint1Open);
      return { ok: true };
    },
    autoAssign(voterId, checkpointId, checkpoint1Open) {
      if (checkpointId !== 1 || !enabled(checkpoint1Open)) return null;
      const existing = members.find((m) => m.voterId === voterId);
      if (existing) return { assigned: true, busId: existing.busId };
      sync(checkpoint1Open);
      const bus = boarding();
      if (!bus) return { assigned: false };
      members.push({ id: ++memberSeq, busId: bus.id, voterId, memberType: "EMPLOYEE" });
      sync(checkpoint1Open);
      return { assigned: true, busId: bus.id };
    },
    assign(voterId, busId, actor, checkpoint1Open = true) {
      if (actor.role === "btc" && teamOf(voterId) !== 7) return { error: "forbidden_staff_only" };
      if (members.some((m) => m.voterId === voterId)) return { error: "already_assigned" };
      members.push({ id: ++memberSeq, busId, voterId, memberType: teamOf(voterId) === 7 ? "STAFF" : "EMPLOYEE" });
      sync(checkpoint1Open);
      return { ok: true };
    },
    moveMember(memberId, toBus, checkpoint1Open = true) {
      const member = members.find((m) => m.id === memberId);
      if (!member) return { error: "not_found" };
      member.busId = toBus;
      sync(checkpoint1Open);
      return { ok: true };
    },
    reset() {
      buses.forEach((b) => { b.status = "WAITING"; });
      members.length = 0;
    },
    canRollcall(busId, actor) {
      if (actor.role === "super") return true;
      if (actor.role === "guide") return actor.busId === busId;
      if (actor.role === "btc") return true;
      return false;
    },
  };
};
const busTeamOf = (voterId) => (voterId >= 700 ? 7 : (voterId % 6) + 1);
const BUS_CASES = [
  { name: "BUS-01 lượt quét đầu tiên tự mở Xe 1", run: (m) => { const r = m.autoAssign(101, 1, true); return r.assigned === true && r.busId === 1 && m.buses[0].status === "BOARDING"; } },
  { name: "BUS-02 đủ sức chứa tự chốt xe và mở xe kế", run: (m) => { m.autoAssign(101, 1, true); m.autoAssign(102, 1, true); return m.buses[0].status === "CLOSED" && m.boarding()?.id === 2; } },
  { name: "BUS-03 người tiếp theo rơi vào xe kế", run: (m) => { m.autoAssign(101, 1, true); m.autoAssign(102, 1, true); return m.autoAssign(103, 1, true).busId === 2; } },
  { name: "BUS-04 chốt đến xe 5 thì hoàn tất, tắt phân xe", run: (m) => { for (let i = 1; i <= 5; i += 1) m.setCapacity(i, 1, true); for (let v = 101; v <= 105; v += 1) m.autoAssign(v, 1, true); return m.buses.every((b) => b.status === "CLOSED") && m.autoAssign(106, 1, true) === null; } },
  { name: "BUS-05 auto-assign chỉ chạy ở Trạm 1", run: (m) => m.autoAssign(101, 2, true) === null && m.autoAssign(101, 1, true).assigned === true },
  { name: "BUS-06 trạm chưa mở → không phân xe, không tự mở xe", run: (m) => m.autoAssign(101, 1, false) === null && m.buses.every((b) => b.status === "WAITING") },
  { name: "BUS-07 quét trùng không tạo member thứ hai", run: (m) => { m.autoAssign(101, 1, true); const again = m.autoAssign(101, 1, true); return again.assigned === true && m.members.filter((x) => x.voterId === 101).length === 1; } },
  { name: "BUS-08 mỗi người chỉ ở một xe", run: (m) => { m.autoAssign(102, 1, true); return m.assign(102, 2, { role: "super" }).error === "already_assigned"; } },
  { name: "BUS-09 thêm thủ công cũng đếm vào sức chứa", run: (m) => { m.autoAssign(101, 1, true); m.assign(701, 1, { role: "super" }); return m.buses[0].status === "CLOSED" && m.boarding()?.id === 2; } },
  { name: "BUS-10 thành viên thủ công chuyển xe được", run: (m) => { m.autoAssign(101, 1, true); const id = m.members.find((x) => x.voterId === 101).id; return m.moveMember(id, 3).ok === true && m.members.find((x) => x.id === id).busId === 3; } },
  { name: "BUS-11 BTC chỉ thêm người team 7, super thêm mọi team", run: (m) => m.assign(101, 1, { role: "btc" }).error === "forbidden_staff_only" && m.assign(701, 1, { role: "btc" }).ok === true && m.assign(102, 2, { role: "super" }).ok === true },
  { name: "BUS-12 reset đưa 5 xe về WAITING và xóa member", run: (m) => { m.autoAssign(101, 1, true); m.reset(); return m.buses.every((b) => b.status === "WAITING") && m.members.length === 0; } },
  { name: "BUS-13 HDV chỉ điểm danh đúng xe mình", run: (m) => m.canRollcall(2, { role: "guide", busId: 2 }) === true && m.canRollcall(3, { role: "guide", busId: 2 }) === false },
  { name: "BUS-14 BTC/Super điểm danh mọi xe", run: (m) => m.canRollcall(4, { role: "btc" }) === true && m.canRollcall(5, { role: "super" }) === true },
  { name: "BUS-15 đóng/mở xe thủ công: chốt sớm tự mở xe kế, mở xe khác trả xe đang nhận về hàng chờ", run: (m) => { m.autoAssign(101, 1, true); m.closeBus(1, true); const closed = m.buses[0].status === "CLOSED" && m.boarding()?.id === 2; m.openBus(3, true); return closed && m.boarding()?.id === 3 && m.buses[1].status === "WAITING"; } },
  { name: "BUS-16 hạ sức chứa giữa chừng → xe đầy tự chốt", run: (m) => { m.setCapacity(1, 5, true); m.autoAssign(101, 1, true); m.autoAssign(102, 1, true); const still = m.buses[0].status === "BOARDING"; m.setCapacity(1, 2, true); return still && m.buses[0].status === "CLOSED" && m.boarding()?.id === 2; } },
];
for (const tc of BUS_CASES) {
  if (!tc.run(busModel(busTeamOf))) throw new Error(`${tc.name}: failed.`);
}

const totalBytes = files.reduce((total, file) => total + fs.statSync(file).size, 0);
console.log(`Plugin source OK: ${phpFiles.length} PHP, ${jsFiles.length} JS, ${cssFiles.length} CSS, ${totalBytes} bytes. Tie tests: ${TIE_CASES.length} cases passed. Bus tests: ${BUS_CASES.length} cases passed (${BUS_CASES.map((tc) => tc.name.split(" ")[0]).join(", ")}).`);
