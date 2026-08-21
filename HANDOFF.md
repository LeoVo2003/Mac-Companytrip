# HANDOFF — MAC Company Trip Voting Plugin

> Tài liệu bàn giao toàn bộ ngữ cảnh project để một AI/dev khác có thể tiếp tục làm việc ngay.
> Phiên bản hiện tại: **v1.9.2** (đã release & verify ngày 2026-08-21, release id 374173359, commit 4146baa) — sửa layout màn công bố (khối mr-chart-lines còn sót trong markup results.js chiếm ô grid sau khi CSS bị bỏ ở 1.9.1), bỏ tag #số đội trên màn trình chiếu, đổi nhãn "KỊCH BẢN MC" → "TÍN HIỆU TỔNG KẾT". Trước đó v1.9.1: sửa lỗi snapshot "Cần đủ 6 đội", bàn điều khiển thành tab "Công bố", bỏ vạch 10 ô + mr-chart-lines CSS. Trước đó v1.9.0: BIG UPDATE màn trình chiếu chuyển thành màn công bố ĐIỂM TỔNG Company Trip với kịch bản 6 step (MC bấm nút trên admin). Logic công bố văn nghệ cũ giữ nguyên trong code để tái sử dụng cho màn đua thuyền sau này. Trước đó nữa v1.8.20 nâng spacing đáy + điểm hạng 3 màu copper. Prototype "Race to the Crown" từng gắn tag v1.9.0 cũ đã gỡ khỏi source và xóa release/tag trên GitHub.

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

### v1.9.2 (2026-08-21, commit 4146baa, release id 374173359) — mới nhất · sửa layout + bỏ tag #N

- **Layout màn công bố**: bản 1.9.1 bỏ CSS `.mr-chart-lines` nhưng markup `shell()` trong results.js vẫn render `<div class="mr-chart-lines">` → mất `position:absolute` nên nó thành grid item, chiếm hàng 64px và đẩy `.mr-header` vào hàng 1fr (header giãn nửa màn hình như screenshot user báo). Đã bỏ khối div này khỏi markup; grid chỉ còn header (64px) + main (1fr).
- **Bỏ tag `#số đội`** (`<b>#${team.number}</b>`) trên tên đội ở màn trình chiếu; `.mr-team-name` chuyển `align-content: center` để tên cân giữa ô 64px; xóa CSS chết `.mr-team-name b` + padding-top mobile.
- **Nhãn admin**: `KỊCH BẢN MC` → `TÍN HIỆU TỔNG KẾT`.
- `check-plugin.mjs`: ban-list mở rộng sang results.js (`mr-chart-lines`, `<b>#${team.number}</b>`).

### v1.9.1 (2026-08-21, commit 58876aa, release id 374170342) — sửa lỗi + dọn màn tổng kết

- **Sửa lỗi "Cần đủ 6 đội"**: `MAC_Points::dashboard()` trả về `{categories, teams, history}`; snapshot ở `ajax_reveal_total` và fallback của `results_total` trước đó lặp nhầm cấp ngoài (3 khóa) nên `count < 6` luôn đúng. Nay cả hai đọc `dashboard()['teams']`.
- **Bàn điều khiển công bố thành tab riêng**: subnav Tổng quan nay là `Tổng điểm | Công bố | Lịch sử`; panel không còn nằm trên cùng stack Tổng điểm. Header đổi `LIVE REVEAL / Tín hiệu MC` → `TỔNG KẾT COMPANY TRIP / Bàn điều khiển công bố` + khối nút `KỊCH BẢN MC / Các bước công bố tổng kết`; bỏ tiền tố `#số đội` trong bảng "Tổng điểm thật".
- **Màn tổng kết gọn mắt**: bỏ vạch ngang chia 10 ô (`.mr-column::after`) và toàn bộ `.mr-chart-lines` (CSS chết từ v1.9.0) — thang ô chỉ còn trong logic `LADDER_LEVELS`.
- `check-plugin.mjs`: invariant vạch 10 ô thay bằng ban-list (`mr-chart-lines`, `mr-horizon`, `.mr-column::after`, `repeating-linear-gradient(to top`) + invariant mới (tab `["reveal", "Công bố"]`, `dashboard()['teams']` ở cả admin lẫn rest).

### v1.9.0 (2026-08-21, commit b0e5faf, release id 374166344) — BIG UPDATE màn công bố ĐIỂM TỔNG

- **Màn trình chiếu (`/ket-qua-van-nghe/`) nay chiếu ĐIỂM TỔNG**: shortcode trỏ endpoint mới `mac-voting/v1/results-total`; giữ nguyên layout hải trình (seascape + la bàn + chart-lines, không có mr-horizon).
- **State machine mới** `mac_voting_total_reveal_state` (option): IDLE → ROLLING → RANK65 → RANK43 → RANK12 → TWIST → FINAL, chuyển step nghiêm ngặt, lưu kèm snapshot tổng điểm (đóng băng lúc bấm Mở màn từ `MAC_Points::dashboard()`). API `MAC_Voting_DB::total_reveal_state()` / `set_total_reveal_state()` trong `class-mac-voting-db.php`; admin ajax `mac_vote_reveal_total` (`ajax_reveal_total` trong `class-mac-voting-admin.php`); REST `results_total()` trong `class-mac-voting-rest.php` (rank tính theo tổng điểm snapshot, lộ hạng: RANK65 mở hạng 5-6, RANK43 mở tới hạng 3, FINAL mở hết; RANK12/TWIST giấu hạng 1-2 + điểm để giữ twist; trả kèm `topTwo`).
- **Thang 10 ô** trong `results.js`: `LADDER_LEVELS` — RANK65 hạng 6-5 = 3 ô; RANK43 hạng 4-5-6 = 4 ô, hạng 3 = 5 ô; RANK12/TWIST top 2 = 6 ô; FINAL quán quân = 10 ô. Vạch chia 10 ô vẽ bằng `.mr-column::after` (repeating-linear-gradient mỗi 10%). Roll step 1 lượn sine nhẹ (rAF) + số đi bộ ngẫu nhiên, không giật; TWIST cho top 2 dao động đối pha quanh 60%; FINAL: quán quân nhảy 100% + pop + glow.
- **Text mới**: header "TỔNG KẾT COMPANY TRIP", kicker idle "KẾT QUẢ CHUNG CUỘC"; các step: "HẠNG 6 & HẠNG 5", "HẠNG 4 & HẠNG 3", "HẠNG 2 & HẠNG 1", "KHOẢNH KHẮC QUYẾT ĐỊNH", final "QUÁN QUÂN COMPANY TRIP" + mô tả "CHÚC MỪNG ... · N điểm · Nhà vô địch Company Trip".
- **Pháo hoa tưng bừng hơn**: 5 cột fountain 26 hạt/80ms trong 8,2s, burst 132 hạt/460ms trong 9,4s, thêm màu cam/đỏ, kết thúc sau 12,5s.
- **Admin UI**: panel "Công bố điểm tổng Company Trip" đặt đầu tab Tổng quan (6 nút đánh số 00-05 + Đặt lại, dùng class `ma-reveal-*` có sẵn), kèm bảng tổng điểm thật chỉ admin thấy. Logic + panel công bố văn nghệ cũ giữ nguyên (tab VĂN NGHỆ) để lát nữa tái sử dụng cho đua thuyền.
- `tools/check-plugin.mjs`: invariant cũ `["THIRD", "SECOND", "FINAL"]` của results.js thay bằng bộ invariant tổng kết (stage RANK65→FINAL, ladder, vạch 10 ô, transitions, endpoint, nút admin).

### v1.8.20 (2026-08-21, commit 7f25efb, release id 374162282)

- Nâng bottom padding `.mr-shell > main` lên `clamp(48px, 9vh, 120px)` (mobile `clamp(36px, 8vh, 84px)`) để hàng tên đội + điểm nằm cao, chiếu màn sân khấu lớn không bị người che sát mặt đất.
- `.mr-team.is-third .mr-score { color: #f0bd91; }` — điểm hạng 3 màu copper, tách khỏi màu mặc định của hạng 4-5-6.

### v1.8.19 (2026-08-21, commit ba686c7, release id 374154410)

- Phóng to tên đội + điểm dưới chân cột: `.mr-score span` clamp(18px,1.7vw,30px), `.mr-score small` clamp(7px,0.6vw,10px), `.mr-team-name b` clamp(10px,0.8vw,14px), `.mr-team-name strong` clamp(12px,1.1vw,20px) (mobile: score clamp(14px,4.2vw,20px), strong 11px); nới hàng lưới `.mr-team` desktop `minmax(0,1fr) 64px 40px` / mobile `52px 30px`, horizon `.mr-chart::after` dời về `bottom: 104px` / mobile `82px`; cập nhật invariant trong `tools/check-plugin.mjs`.
- Bỏ hẳn `.mr-horizon` (đường kẻ chân trời) khỏi JS + CSS theo ý user.

### v1.8.18 (2026-08-21, commit 297a3d8, release id 374148401)

- User so sánh ảnh thực tế với ảnh bản tham chiếu và chốt **hình 2 (bản tham chiếu) là cái muốn**: trả palette biển về gốc (`--deep-sea #0a2338`, `--sea-teal #123a52`, `--sea-glint #2c5b74`, glow 0.28/0.12), `.mr-sun` về vệt mờ rộng 58vw×18vh blur 54px (bỏ đĩa nắng to), khôi phục `.mr-horizon` (đường chân trời 16%) và `.mr-chart-lines` (2 tuyến chéo + 3 chấm) trong cả JS lẫn CSS.
- `.mr-compass` bỏ `rotate(-7deg)` → `translate(-50%, -50%) rotate(0deg)` để kim đỏ đứng yên chỉ đúng 12h.
- `.mr-shell::after` (vòng sóng đáy): mở rộng `height 96% / bottom -52%`, bỏ lớp linear-gradient tối, thêm `mask-image: radial-gradient(ellipse at 50% 0%, #000 0 50%, transparent 75%)` — 50% đầu giữ nguyên, 51–75% mờ dần rồi tan biến.

### v1.8.17 (2026-08-21, commit 28be285, release id 374139051)

- Dọn seascape theo feedback user: bỏ `<i class="mr-horizon">` và cả khối `.mr-chart-lines` (JS + CSS) vì vô nghĩa; `.mr-sun` vẽ lại thành đĩa nắng hoàng hôn rõ hơn (radial-gradient vàng→cam→đỏ, blur 12px, opacity 0.8); token biển đậm hơn (`--deep-sea #0a2a45`, `--sea-teal #12466b`, `--sea-glint #2e6a8e`) + glow mép trên mạnh hơn.
- La bàn: to hơn (`min(66vw, 820px)`, mobile 84vw/100vw) nhưng mờ hơn (opacity 0.12, mobile 0.1); **mặt số SVG xoay tròn mượt liên tục** (`mr-dial-spin 90s linear infinite` trên svg) như la bàn thật, **kim đứng yên** `rotate(0)` với đầu bắc đỏ `var(--sunset-red)` luôn chỉ 12h; gỡ toàn bộ animation kim theo stage + 6 keyframe mr-needle-*.
- Logo header to hơn `clamp(72px, 6.5vw, 100px)` (height auto, mobile 64px); bỏ `.mr-compass-mark` cạnh logo (JS + CSS).

### v1.8.16 (2026-08-21, commit c0465af, release id 374130888)

- User đưa 2 file tham chiếu từ thư mục "Chấm Điểm Văn Nghệ - Copy" và yêu cầu làm lại màn công bố theo style đó, **chỉ giữ font chữ/nội dung hiện tại**. Port toàn bộ phần nhìn: seascape (`.mr-sun/.mr-horizon/.mr-wake`), `.mr-chart-lines`, la bàn SVG lớn viewBox 400 (3 vòng + cross + rose + minor + labels N/E/S/W/NE/SE/SW/NW) với `.mr-needle` vẽ bằng CSS quay theo stage (`mr-needle-drift/spin/search/third/second/lock`), header `.mr-brand-lockup` (logo + `.mr-compass-mark`), bar kim loại nhiều lớp, stage final đổi nền + h1 vàng.
- **Điểm số dời xuống dưới cùng** theo ảnh user gửi: DOM giữ nguyên `.mr-score → .mr-column → .mr-team-name` nhưng grid reorder `.mr-column { grid-row: 1 }`, `.mr-team-name { grid-row: 2 }`, `.mr-score { grid-row: 3 }`; `.mr-team { grid-template-rows: minmax(0,1fr) 56px 32px }` (mobile `44px 24px`); horizon `.mr-chart::after` dời về `bottom: 88px` (mobile 68px) cho khớp chân cột.
- Giữ nguyên: Inter cho UI/điểm số, Cormorant Garamond cho h1, h1 `line-height: 1.2`, mọi chuỗi copy tiếng Việt hiện tại, header "COMPANY TRIP - One Direction" (theo note user sửa tay trong create-release.mjs), `.mr-heading > span:empty { display:none }`.
- `tools/check-plugin.mjs`: invariant layout cập nhật sang row template mới + thêm check `grid-row: 1/2/3` giữ điểm ở đáy cột.

### v1.8.15 (2026-08-20, commit 85b0c92, release id 373664494)

- La bàn SVG vẽ lại line-art mảnh hơn (stroke 0.35–0.6, bỏ vòng r=70, chữ N/E/S/W nhỏ hơn) và mờ hơn (opacity 0.1, final 0.18); kim `.mr-needle` **luôn đung đưa nhẹ** (`mr-needle-idle` 7s infinite ở mọi stage) — gỡ các animation quay/khóa theo stage và 4 keyframe thừa.
- Bỏ dòng mô tả màn chờ "6 đội · 1 hải trình · 1 ngôi vị cao nhất" (user dành câu đó cho mục đích khác): `shell()`/`renderIdle()` set description rỗng + CSS `.mr-heading > span:empty { display: none }` (các stage khác vẫn hiện mô tả riêng).
- Header `.mr-event span` đổi "COMPANY TRIP · ONE COMPASS" → "company trip - One Direction".

### v1.8.14 (2026-08-20, commit 5acac3c, release id 373658136)

- Kicker `.mr-heading p` màn chờ đổi "MAC MARKETING" → "KẾT QUẢ VĂN NGHỆ" (cả markup `shell()` lẫn `renderIdle()`).
- `.mr-heading h1` nâng `line-height` từ 1.04 lên 1.2 cho thoáng chữ có dấu tiếng Việt.

### v1.8.13 (2026-08-20, commit e07f4da, release id 373653911)

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

Mọi yêu cầu tới 1.9.0 đã xong, build pass (17 PHP / 7 JS / 6 CSS), release đã verify (tải zip từ GitHub về grep marker: route /results-total, total_reveal_state, ajax_reveal_total, data-total-reveal-stage, endpoint mới trong shortcode, RANK65 + ladder 1:10 trong results.js, vạch 10 ô + mr-champion-pop trong results.css, Version 1.9.0 đều có). Việc tiếp theo do user hẹn: làm lại màn công bố văn nghệ theo chủ đề ĐUA THUYỀN, tái sử dụng logic reveal văn nghệ cũ (REST `/results` + `reveal_state()` + panel tab VĂN NGHỆ vẫn còn nguyên trong code).

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
