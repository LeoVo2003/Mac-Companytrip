# HANDOFF — MAC Company Trip Voting Plugin

> Tài liệu bàn giao toàn bộ ngữ cảnh project để một AI/dev khác có thể tiếp tục làm việc ngay.
> Phiên bản hiện tại: **v1.8.13** (đã release & verify ngày 2026-08-20, release id 373653911, commit e07f4da) — redesign màn công bố kết quả theo bộ nhận diện "One Compass" (la bàn đồng cổ + biển đêm). Prototype v1.9.0 "Race to the Crown" đã gỡ khỏi source và release/tag v1.9.0 trên GitHub cũng đã bị xóa (code prototype vẫn còn trong lịch sử git tại commit 4adaa3a nếu muốn khôi phục).

---

## 1. Project là gì

Plugin WordPress nội bộ cho MAC Marketing: hệ thống chấm điểm Company Trip gồm 4 trụ cột:

| Trụ cột | Điểm | Ghi chú |
|---|---|---|
| Check-in | 4 trạm × tối đa 150đ | Điểm chốt khi đóng trạm, theo tỷ lệ có mặt |
| Trò chơi lớn | 3 game, hạng 1–6 nhận 50/40/30/20/10/0đ | |
| Văn nghệ | Quy đổi `ROUND(TB phiếu ÷ 150 × 200)` | Vote qua QR cá nhân, khóa vote team mình |
| Thi đua | Thang 50/40/30/20/10, không giới hạn | Hạng mục cũ = "lần thi đua" |

Các trang public do plugin tự tạo (rewrite slug):

- `/cham-diem-van-nghe/` — trang vote văn nghệ
- `/ket-qua-van-nghe/` — màn hình kết quả/trình chiếu
- `/company-trip-checkin/` — trang **Quét QR check-in** (BTC quét QR thành viên)
- `/company-trip-admin/` — dashboard admin (không dùng wp-admin)

---

## 2. Cấu trúc repo

```
Chấm Điểm Văn Nghệ/
├── mac-companytrip-voting/          # source plugin (đây là thứ được zip/release)
│   ├── mac-companytrip-voting.php   # bootstrap, define MAC_VOTING_VERSION
│   ├── includes/                    # 16 class PHP + template-admin-page.php (trang standalone)
│   ├── assets/                      # admin.js/css, checkin.js/css, public.js/css,
│   │                                # results.js/css, qrcode.bundle.js, jsqr.js, fonts
│   ├── readme.txt                   # changelog chính thức
│   └── uninstall.php
├── tools/
│   ├── check-plugin.mjs             # lint: parse PHP bằng php-parser, đếm file
│   ├── build-plugin.mjs             # zip vào dist/ bằng archiver
│   └── create-release.mjs           # tạo GitHub Release + upload asset
├── docs/superpowers/                # design docs (auto-close timers...)
├── .agents/skills/premium/          # UI skill bắt buộc cho mọi UI mới
├── AGENTS.md                        # design system rules (đọc trước khi làm UI)
├── package.json                     # scripts: check, build
└── mau-import-nhan-su.csv           # mẫu CSV import nhân sự
```

### Các class PHP chính (`includes/`)

- `class-mac-voting-db.php` — schema, bảng `{$prefix}mac_vote_*`, options, `maybe_upgrade()`, hằng số toàn cục (`DEFAULT_STAFF_PASSWORD = 'Mac-123'`, `STAFF_TEAM_NO = 7`, RANK_LADDER…)
- `class-mac-voting-admin.php` — AJAX dashboard (`mac_vote_overview`, person add/grant, staff…), render trang admin
- `class-mac-checkin.php` — logic trạm check-in: `open/close/reopen checkpoint`, `scan()`, `expire_active_checkpoint()`, exemptions, `ensure_staff_user()`
- `class-mac-checkin-rest.php` / `class-mac-voting-rest.php` — REST endpoints (bootstrap/scan/team, vote_state/submit)
- `class-mac-voting-qr.php` — ký/verify QR token (HMAC sha256 với `wp_salt('auth')`), có fallback chữ ký lệch environment kèm audit
- `class-mac-points.php` — điểm thi đua + audit log cộng điểm
- `class-mac-admin-public.php` — trang login dashboard + auth + **standalone template**: `/company-trip-admin/` render bằng `includes/template-admin-page.php` (không wp_head/footer → theme không can thiệp, giao diện khớp 100% với dashboard)
- `class-mac-admin-rest.php` — REST login (`/admin/login`, wp_signon)
- `class-mac-voting-auth.php` — rate limit login
- `class-mac-voting-updater.php` — tự cập nhật plugin từ GitHub Release
- `class-mac-checkin-public.php` / `class-mac-voting-public.php` — render trang public

---

## 3. Khái niệm & quy ước BẮT BUỘC

### Thuật ngữ (từ v1.8.8)

- Checkpoint gọi là **"Trạm"** (Trạm 1, Trạm 2… trạm check-in). KHÔNG dùng từ "mốc" ở bất kỳ chuỗi nào người dùng nhìn thấy.
- Trang/role quét QR gọi là **"Quét QR check-in"**. KHÔNG dùng "máy quét BTC".
- Câu desc chuẩn của khối scanner: *"Mở camera để quét mã QR và ghi nhận check-in cho team."*

### Tài khoản & phân quyền

- Role: `mac_btc_checkin` (BTC — quét team được gán) và `mac_companytrip_super_admin` (Super admin — toàn quyền). Admin xem-only cũng tồn tại.
- **Mật khẩu mặc định mọi tài khoản BTC/Super là `Mac-123`** (hằng số `MAC_Voting_DB::DEFAULT_STAFF_PASSWORD`). Upgrade lên 1.8.8 chạy `reset_dashboard_passwords()` một lần để đồng bộ tài khoản cũ.
- Team số 7 "Hoa tiêu" là team BTC (`STAFF_TEAM_NO`), không thi đấu.
- "Thêm người" ở tab Nhân sự & QR mặc định **chỉ thêm vào danh sách nhân sự, KHÔNG tạo tài khoản WordPress**. Muốn tạo tài khoản thì dùng nút "Cấp quyền" từng dòng (BTC/Super admin), email ảo tự sinh `@macusaone.com`.

### Quy tắc nghiệp vụ quan trọng

- Chỉ mở được **1 trạm tại một thời điểm**; mở trạm có thời gian tự đóng (mặc định 15 phút). Mỗi team có "cửa sổ" check-in bằng đúng số phút đó tính từ lượt quét đầu tiên.
- **Lazy expiry**: hết hạn chỉ được xử lý khi có request — `MAC_Checkin::expire_active_checkpoint()` + `MAC_Voting_DB::expire_open_round()`. Hiện đã gắn ở: REST checkin (bootstrap/scan/team), REST voting (vote_state/submit), và đầu hàm `overview()` của admin (v1.8.8) để admin + máy quét luôn đồng bộ. Nếu thêm luồng mới đọc trạng thái OPEN, nhớ gọi 2 hàm này trước.
- Vote văn nghệ: phải vote **cả 2 tiết mục mỗi lượt hoặc không vote**; khóa vote team mình; chống phiếu trùng; audit log đầy đủ.
- Mọi thời gian hiển thị theo **giờ Hà Nội UTC+7** (`MAC_Voting_DB::hanoi_time()`).
- Bật cổng văn nghệ (`mac_voting_public_enabled`) là điều kiện tiên quyết để mở lượt vote.

---

## 4. UI / Design system

- Đọc `AGENTS.md` + `.agents/skills/premium/SKILL.md` trước khi làm UI. KHÔNG tự thay đổi design system trừ khi user yêu cầu.
- Brand MAC Marketing (không dùng xanh/tím mặc định của Premium): đỏ `#E31E24`, cam `#FF6A2C`, gradient action chính đỏ→cam.
- Nền: page `#F5F5F7`, panel `#FFFFFF`, subtle `#FAFAFC`, text `#111827`, muted `#667085`. Font Inter (woff2 bundle sẵn trong assets/fonts
).
- Spacing scale 4/8/12/16/24/32; radius card 18–24px, control 12–14px; touch target ≥44px; mobile-first.
- Pattern UI đang dùng trong `admin.js`: `.ma-panel` > `header`, `.ma-board-table` (scroll ngang mobile, sticky cột đầu, biến thể `ma-pin-2`, `ma-no-sticky`), modal `.ma-modal`/`.ma-modal-card` với `bindModalClose` (Escape + click nền), `confirmDialog()` tùy chỉnh (không dùng `confirm()` native), `notify()`, `esc()` escape HTML.
- Nút chính luôn có class `ma-primary`.
- Linter PHP của IDE báo "Undefined function 'plugin_dir_path' / 'get_option' / 'wp_set_password'…" — **false positive** (hàm WordPress), cứ bỏ qua.

---

## 5. Build & Release pipeline (QUAN TRỌNG — làm đúng thứ tự)

```powershell
# 1. Bump version ở 4 chỗ (luôn cùng nhau):
#    - package.json            "version"
#    - mac-companytrip-voting/mac-companytrip-voting.php  header "Version" + MAC_VOTING_VERSION
#    - mac-companytrip-voting/readme.txt  "Stable tag" + thêm mục == Changelog ==
#    - tools/create-release.mjs  const tag + notes

# 2. Build (check PHP/JS/CSS rồi zip vào dist/)
npm run build

# 3. Commit + push TRƯỚC khi tạo release (tránh tag dính commit cũ)
git add -A
git -c user.name="LeoVo2003" -c user.email="LeoVo2003@users.noreply.github.com" commit -m "vX.Y.Z: ..."
git push origin main

# 4. Tạo GitHub Release + upload asset
$env:GH_TOKEN = "<token GitHub có quyền repo — lấy từ user, KHÔNG commit vào repo>"
node tools/create-release.mjs

# 5. Verify zip đã đăng (bắt buộc): download asset từ GitHub, Expand-Archive,
#    Select-String các marker vừa sửa trong dist\vXXX\* rồi xóa thư mục temp.
```

Chi tiết:

- Repo GitHub: `LeoVo2003/Mac-Companytrip`, branch `main`. Commit identity là `LeoVo2003` (dùng cờ `-c user.name/-c user.email` như trên, KHÔNG sửa git config global).
- Asset release đặt tên `mac-companytrip-voting-vX.Y.Z.zip`; `class-mac-voting-updater.php` match asset theo pattern này để tự update.
- `create-release.mjs` tự xóa asset zip cũ trùng tên trước khi upload; nếu release tag đã tồn tại nó sẽ reuse — muốn ghi chú mới thì xóa release trên GitHub trước.
- PowerShell **không hỗ trợ `&&`** — dùng `;` ngăn cách.
- Nếu thêm migration theo version: thêm điều kiện `version_compare(get_option('mac_voting_plugin_version','0'), 'X.Y.Z', '<')` bên trong `maybe_upgrade()` (pattern đã dùng cho reset password 1.8.8).

---

## 6. Lịch sử gần đây & trạng thái hiện tại

### v1.8.13 (2026-08-20, commit e07f4da, release id 373653911) — mới nhất

- **Redesign toàn bộ `results.css`** theo mood "la bàn đồng cổ trên biển đêm sâu" (spec `prompt-qwen-redesign-ket-qua-van-nghe.md`): palette mới `--abyss/--deep-sea/--sea-teal/--sea-glint` + brass `--brass-dark/--brass/--brass-light/--brass-highlight/--copper` + sunset `--sunset-red/--sunset-orange/--sunset-gold` + silver `--silver-*`; bỏ hẳn bộ `--sky/--foam/--sea/--deep/--gold` cũ. Nền gradient biển đêm + glow hoàng hôn mỏng mép trên; h1 + `.mr-event strong` dùng serif display **Cormorant Garamond** (Google Fonts `@import` đầu file) kiểu khắc bảng đồng; cột điểm mặc định gradient brass.
- **La bàn SVG watermark** thêm vào `shell()` trong `results.js` (khối `.mr-compass` ngay sau `.mr-aurora`, aria-hidden, line-art `currentColor`): vòng chia độ, tick dasharray, chữ N/E/S/W, hoa 8 cánh, kim riêng `.mr-needle` quay **thuần CSS** theo `data-stage`: idle đung đưa → rolling spin 0.9s → decoy lưỡng lự alternate → third/second settle forwards → final lock 345° (vượt đích + rung nhẹ) kèm glow vàng đồng trên la bàn. Reduced-motion block cũ tự tắt hết.
- Thứ hạng: hạng ba = đồng tối `--copper`, hạng nhì = bạc pewter `--silver-*` (không còn chrome), quán quân = vàng đồng hoàng hôn `--sunset-gold → --brass-light → --brass`. Pháo hoa đổi palette kim tuyến vàng đồng `["#ffe9ad", "#e8c17a", "#b8823f", "#ffcf7d", "#fff6e0"]`.
- **Yêu cầu thêm của user**: `.mr-footer` ẩn hẳn (`display: none`, DOM giữ nguyên cho JS set text); logo header thu nhỏ `clamp(58px, 5.6vw, 104px)` (mobile 52px); nới khoảng cách `.mr-heading` ↔ `.mr-chart` lên `clamp(42px, 7vh, 84px)` (mobile 28–48px); bonus fix badge `.mr-rank` gọn hơn ở ≤600px (font 7px, padding 3px 6px) để 6 cột không tràn nhau.
- `results.js` chỉ đụng đúng 2 chỗ cho phép (khối compass + dòng colors), logic/state machine/polling giữ 100%.

### v1.8.12 (2026-08-20, commit bb5ac56, release id 373631036)

- **Nút "Áp dữ liệu demo"** đặt kín cuối `.ma-side-links` sidebar, chỉ hiện khi `canWrite()` (super admin), style trầm opacity 0.5. Backend `ajax_seed_demo()` + `seed_demo_data()` trong `class-mac-voting-admin.php` (guard super, transaction, rollback khi lỗi): dọn bộ demo cũ theo email `demo.*@macusaone.com` (kèm ballots/grants/checkins/exemptions), ghi 48 nhân sự ảo (8/team), 240 phiếu hợp lệ (mỗi người chấm 5 đội khác, tổng điểm bám mục tiêu TB 132/121/141/108/127/114 thang 150 nhờ mảng nhiễu tổng 0 chu kỳ 40), điểm CHECKIN 4 trạm theo ma trận, GAME theo hạng 3 game (source `GAME_{id}`), THIDUA 2 vòng mặc định; audit `DEMO_DATA_SEEDED`. Bấm lại chỉ ghi đè, không nhân bản; "Đặt lại sự kiện" xóa phiếu/điểm nhưng giữ nhân sự demo.
- Kịch bản điểm demo: chung cuộc T5 Sao Bắc Cực (969) > T1 La Bàn (906) > T2 Hải Đồ (896) > T3 Đèn Hiệu (888) > T6 Hải Đăng (707) > T4 Viking (659).
- **Màn công bố kết quả đổi chủ đề hải trình** (results.css/js): nền sky `#b7dcf8` → deep sea `#061f4e` kèm god rays trắng; h1 chrome bạc bằng `background-clip: text`; kicker vàng nhạt kẹp ✦ hai bên; cột điểm gradient xanh đại dương `#a8d4f7 → #4a8fdc → #123f8f`; featured = trắng-băng pulse; á quân/hạng ba = chrome bạc; quán quân = vàng kim duy nhất; pháo hoa đổi palette trắng/vàng/biển; copy "COMPANY TRIP · ONE COMPASS", "6 đội · 1 hải trình".
- Gỡ prototype final-reveal v1.9.0 khỏi source (final.css/js, class-mac-final-reveal.php, template-final-page.php, ensure_final_page/shortcode) theo working tree của user.

### v1.8.11 (2026-08-20, commit 1fff36a, release id 373515064)

- Sửa loader bánh xe: spoke giờ là `inset: 0` xoay nguyên bánh, bi nằm trên vành khuyên (`top: 0; left: 50%`), keyframes chỉ pulse scale/opacity tuần tự; PHP `loading_markup()` đổi `--rot` từ `i*20` sang `i*40` để 9 bi phủ đủ vòng tròn (bản 1.8.10 bi bay từ tâm ra nên tụm một chỗ).
- Sửa hardening mục 21 `admin.css`: nhóm nút trung tính `!important` của 1.8.10 đè mất active của tab sidebar/subnav, thêm border trắng lên tab desktop và biến nút "Đặt lại sự kiện" thành nút trung tính. Giờ nhóm trung tính loại `.ma-side nav button`/`.ma-subnav button`/`.ma-reset-trigger`; thêm guard riêng: tab trong suốt không border, active `#fff4f0` + ring `#fed7cc` + chữ `#e31e24`, reset trigger danger `#b42318`/`#fecdca` — tất cả `!important` để theme không đè.

### v1.8.10 (2026-08-20, commit e8104f0, release id 373504956)

- Loader bánh xe thương hiệu: `MAC_Voting_Admin::loading_markup()` (9 spoke, biến `--rot`/`--delay`), CSS `.mac-admin-loading`/`.mac-loader-wheel`/`.mac-loader-spoke`/`.mac-loader-ball` trong `admin.css` — dùng chung cả 3 nơi dựng dashboard (wp-admin, shortcode, standalone).
- Hardening `body.mac-admin-public-page`: nút trung tính/nguy hiểm/gradient, bảng, ô nhập, link sidebar dùng `!important` (mục 21 trong `admin.css`); form login có block `#ma-admin-login` riêng.
- **Refactor CSS toàn bộ (2026-08-20)**: `assets/admin.css` viết lại theo cấu trúc tokens → layout → components → login → loader → hardening → responsive (breakpoint gom 1 khối giảm dần 1180→430). Gộp toàn bộ rule trùng do append theo version; thang chữ thống nhất: size 10/11/12/13/14/16/18/20/24/30px, weight 500/600/700/800 (bỏ 650/720/750/820/850). Token màu/bóng/focus dùng CSS custom properties trên `.mac-admin-app`. `admin-qr.css` bỏ rule `.ma-data` trùng; `checkin.css`/`public.css`/`results.css` đồng bộ weight. KHÔNG đổi tên selector (JS/PHP hook hết rồi).
- **Fix mobile (2026-08-20)**: (1) Cột team các bảng tổng quan thẳng hàng — breakpoint 700px dùng `width: 1px` + `white-space: nowrap` (shrink-to-fit) cho cột đầu/`.ma-pin-2` cột 2 để cột team luôn đúng bằng tên đội dài nhất, khoảng trống dồn cho cột số. (2) Chuyển tab không giật về đầu — `render()` trong `admin.js` tìm đúng phần tử cuộn ngang (`.ma-side` trên mobile, không phải `nav`) rồi `scrollTo` đưa tab active vào giữa.

### v1.8.9 (2026-08-20, commit ecc1899, release id 373465418)

- `/company-trip-admin/` chuyển sang **standalone template** (`template_include` → `includes/template-admin-page.php`): tự xuất HTML đầy đủ, chỉ nạp asset của plugin, bỏ hoàn toàn header/footer/CSS của theme nên giao diện khớp 100% với dashboard. Shortcode `mac_companytrip_admin` vẫn giữ cho backward compat.

### v1.8.8 (2026-08-20, commit 27527a0, release id 373460018)

1. Fix auto-close: `overview()` của admin gọi expiry để trạm hết hạn đóng đồng bộ cả trong dashboard CHECK-IN (trước chỉ có phía scanner).
2. Đổi tên toàn bộ "mốc" → "Trạm", "máy quét BTC" → "Quét QR check-in" (JS + PHP + trang login).
3. Bảng điểm: TB phiếu văn nghệ (x/150) xuống dòng riêng (`.ma-cell-sub`).
4. Khối Tài khoản Quét QR check-in: grid 3 cột desktop / 2 cột mobile; bảng BTC bỏ sticky (`ma-no-sticky`).
5. Header bảng tiến độ: "Trạm N" + tên trạm làm dòng desc nhỏ (`thead th small`).
6. Mật khẩu mặc định BTC/Super = `Mac-123` + migration reset tài khoản cũ.
7. Nút "Gửi QR cho danh sách đang lọc" gắn lại `ma-primary`.

### v1.8.7 (commit 1f72cd0)

- Modal "+ Thêm người" (họ tên, email ảo @macusaone.com, vai trò, team, mk) — mặc định chỉ thêm nhân sự, không tạo WP account.
- Nút "Cấp quyền" từng dòng trong danh sách Gửi QR qua email (`ajax_person` op `grant`, `grant_person_role()`, `apply_dashboard_role()`, `create_dashboard_account()` trong class-mac-voting-admin.php).

### v1.8.6 trở về trước

- Giờ Hà Nội UTC+7 toàn bộ; mobile tối ưu bảng điểm (sticky cột, table-layout auto); luồng scanner 2 bước (chọn team → quét); QR fallback chữ ký; xem chi tiết `readme.txt`.

### Không còn việc tồn đọng

Mọi yêu cầu tới 1.8.13 đã xong, build pass (17 PHP / 7 JS / 6 CSS), release đã verify (tải zip từ GitHub về grep marker: `mr-compass` + `#ffe9ad` trong results.js, `--brass-dark`/`Cormorant`/`mr-needle-lock` trong results.css, Version 1.8.13 đều có). Release/tag v1.9.0 (prototype đã gỡ) đã bị xóa khỏi GitHub — v1.8.13 là release mới nhất, updater sẽ không kéo ngược bản cũ.

---

## 7. Bài học / bẫy đã gặp (đọc để khỏi lặp lại)

1. **Tag-before-push race**: tạo release trước khi push → tag dính commit cũ, zip sai. Luôn push trước.
2. **TDZ ReferenceError trong admin.js**: wiring event cho nút phải đặt SAU định nghĩa hàm const (vụ modal 1.8.7).
3. **IME tiếng Việt**: không rewrite `input.value` trong event `input` khi đang composition — hỏng gõ tiếng Việt.
4. **Timer MySQL UTC**: chuỗi datetime MySQL không có `T`/`+Z` bị JS parse sai múi giờ — phải normalize trước khi countdown.
5. **Lazy expiry**: mọi nơi đọc trạng thái OPEN (trạm/lượt vote) phải gọi expiry trước, nếu không admin và scanner lệch nhau.
6. **`scan()` phải bọc `recalculate_checkpoint()` trong transaction** — sai là lệch điểm.
7. **QR full URL**: token extraction từng fail khi quét URL đầy đủ — đã hardening nhiều lớp bóc link.
8. **SearchReplace của agent không sửa được file ngoài workspace**; file zip temp nên tạo trong `dist/`.
9. Linter báo undefined các hàm WordPress (`plugin_dir_path`, `get_option`…) — false positive, bỏ qua.
10. **GitHub push protection**: KHÔNG bao giờ ghi token (GH_TOKEN…) vào file được commit — push sẽ bị chặn (đã bị với HANDOFF.md). Token chỉ truyền qua biến môi trường.
11. **Hardening `!important` đè luôn state của mình** (vụ 1.8.10→1.8.11): nhóm nút trung tính scope rộng đè mất `.active` của tab, thêm border lên tab desktop và neutralize nút danger. Khi hardening phải `:not()` loại các class state (`.active`, `.ma-reset-trigger`, `.danger`, `.ma-primary`) và thêm guard riêng cho từng state.

---

## 8. Environment

- Windows (25H2), shell `WindowsPowerShell v1.0` (không có `&&`).
- Node dùng để chạy tools (không cần server local; site WordPress chạy ở chỗ khác, plugin update trực tiếp từ GitHub Release).
- `npm run check` validate: 17 PHP, 7 JS, 6 CSS phải đủ — thiếu file là build fail.

---

## 9. Nếu cần tiếp tục: việc thường gặp

- **Thêm tính năng dashboard**: sửa `assets/admin.js` (render bằng template string từ `overview()` AJAX), style trong `assets/admin.css`, dữ liệu từ `overview()` trong `class-mac-voting-admin.php`. Luôn escape bằng `esc()`.
- **Đổi text người dùng**: nhớ quy ước "Trạm" / "Quét QR check-in"; chuỗi nằm rải ở admin.js, checkin.js, public.js và các class PHP (WP_Error messages).
- **Release**: làm đúng 5 bước mục 5, và luôn verify zip sau khi upload.
- **UI mới**: theo AGENTS.md + premium skill; mobile-first; touch ≥44px.
