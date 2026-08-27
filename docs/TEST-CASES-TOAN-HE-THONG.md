# BẢNG TEST CASE TOÀN HỆ THỐNG — MAC COMPANY TRIP VOTING

> Nguồn chạy thật: `npm run check` (tools/check-plugin.mjs). Mọi case dưới đây đều được code kiểm tra tự động mỗi lần build; nhóm TC và BUS là test mô phỏng có tên, các nhóm còn lại là invariant trên source.
> Ký hiệu: ✅ = assert bắt buộc phải đúng, nếu sai build fail kèm tên case.

---

## A. SOURCE & PACKAGE (SYS)

| Mã | Case | Kỳ vọng |
|---|---|---|
| SYS-01 | PHP không chứa BOM | 17+ file PHP sạch BOM |
| SYS-02 | PHP 7.4 compatible | Không `str_contains()`, không union type `true\|false\|array...` |
| SYS-03 | JS parse được | Mọi file JS hợp lệ cú pháp |
| SYS-04 | CSS cân bằng ngoặc | Số `{` == số `}` từng file |
| SYS-05 | Đủ file plugin | Danh sách `required` (PHP classes, assets, fonts + license) đủ |
| SYS-06 | Header plugin hợp lệ | `Plugin Name / Version / Requires at least 6.0 / Requires PHP 7.4`, UTF-8 tiếng Việt |
| SYS-07 | Upgrade hook | `add_action('init', 'mac_voting_maybe_upgrade', 5)` |
| SYS-08 | Đồng bộ version | package.json == header == `MAC_VOTING_VERSION` |
| SYS-09 | GitHub updater | updater đủ transient + `releases/latest` + zip name |
| SYS-10 | Không rác package | Không node_modules/src/dist/.git lọt zip |

## B. DATABASE & SCORING (DB)

| Mã | Case | Kỳ vọng |
|---|---|---|
| DB-01 | Schema core | `one_valid_ballot`, email unique, `qr_version`, `revote_grants`, `audit`, checkpoints, `one_checkin`, thidua_rounds, windows, exemptions, games |
| DB-02 | Hằng số điểm | 150đ/trạm check-in, cửa sổ 15 phút, ladder 50-40-30-20-10-0 |
| DB-03 | Điểm check-in tỷ lệ | `round(max_points * checkedIn / eligible)` |
| DB-04 | Schema bus | `buses`, `bus_members` (unique `one_voter_bus`), `bus_rollcalls`, `bus_rollcall_marks` (unique `one_mark`) |
| DB-05 | Email công ty | `macusaone.com` + normalize; login chỉ nhận 3 domain |

## C. CHECK-IN & QR (CHK)

| Mã | Case | Kỳ vọng |
|---|---|---|
| CHK-01 | Routes | `/checkin/scan` + `/checkin/bootstrap` tồn tại |
| CHK-02 | QR cá nhân | `token_for_voter` + URL `company-trip/q/` |
| CHK-03 | Cửa sổ 15 phút | `window_locked`/`WINDOW_LOCKED` + `team_window` + `set_exemption` |
| CHK-04 | Mở lại trạm reset cửa sổ | `$wpdb->delete($windows` + `resetTeamWindows` |
| CHK-05 | **Không còn WRONG_TEAM** | class-mac-checkin.php không chặn khác team; mọi scanner quét full 6 team |
| CHK-06 | Scanner không chọn team | checkin.js không còn "Chọn team để quét"; camera mở ngay |
| CHK-07 | Scanner bus | có `busAssignment`, `busAssignmentEnabled`, accordion `mc-accordion` |

## D. VOTING VĂN NGHỆ (VOTE)

| Mã | Case | Kỳ vọng |
|---|---|---|
| VOTE-01 | Cổng vote | `is_voting_enabled`/`voting_disabled` + lockedView public |
| VOTE-02 | Login public | username + domain select, không còn login tên/sĐT cũ |
| VOTE-03 | UI chấm | tab team + sao `/10` |
| VOTE-04 | Chống gian lận | không chấm team mình, 1 phiếu VALID (`active_key`), revoke/revote |
| VOTE-05 | CSV nhân sự | bắt buộc email 3 domain; tạo tài khoản BTC/Super từ CSV |
| VOTE-06 | Super admin CSV | không nâng lên `administrator` WordPress |

## E. KẾT QUẢ TỔNG (RES)

| Mã | Case | Kỳ vọng |
|---|---|---|
| RES-01 | 7 bước explicit | RANK65→TEASE43→RANK43→RANK12→TWIST→REVEAL3→FINAL, không auto-timer |
| RES-02 | Thang cột | LADDER_LEVELS đúng từng bước (80% / 50% / 60% / 50-80 / 85-65) |
| RES-03 | Badge | `KHUYẾN KHÍCH` cho 4-5-6 đúng bước; top3 đúng bước |
| RES-04 | Luật đồng hạng server | `rank >= target_rank` + current_ids; protect rank từng bước (`RANK43=>3/4`, `TWIST=>4`, `REVEAL3=>4`) |
| RES-05 | Cảnh báo trùng điểm | `total_tie_warnings` admin + `totalTieWarnings` UI + `rankHeadline` màn chiếu |
| RES-06 | Trang tách | `/ket-qua-tong` + `/ket-qua-van-nghe` + endpoint `/results-total` |
| RES-07 | Trang trí cấm | không `mr-chart-lines`, `mr-horizon`, số team `#n` |
| RES-08 | Điểm ẩn/hero | `is-scores-hidden`, `is-score-hero`, pháo hoa sau 3s |
| RES-09 | Bàn công bố | tab "Công bố" riêng, nút `data-total-reveal-stage`, transition map PHP |

## F. KẾT QUẢ VĂN NGHỆ / THE SPOTLIGHT (ART)

| Mã | Case | Kỳ vọng |
|---|---|---|
| ART-01 | Công bố tuần tự | `startSpotlightSearch`, `5000 - elapsed`, `team.current/revealed`, `aimSpots`, `is-decel` |
| ART-02 | Đúng 1 spotlight trượt | chỉ 1 `class="ar-spot"` |
| ART-03 | Bụi theo đội | `dustMarkup` + `is-search-active` + keyframes float |
| ART-04 | Cone mềm | `.ar-spot` có radial-gradient + clip-path + mask-image |
| ART-05 | Không text-shadow đỏ | title h1/p `text-shadow: none` |
| ART-06 | Theme đại dương | `--ocean-deep/--wood/--bronze`, tone biển sáng, mask |
| ART-07 | Nền oval | runway `border-radius 50% 50% 0 0` + repeating-radial + 42vh; cấm gala-stage |
| ART-08 | Typography màn lớn | clamp(34/3.8vw/68) title, clamp(15/1.25vw/22) bục... |
| ART-09 | Line đỏ-cam nguyên bản | `ar-edge-left/right` 64% ±10°; cấm ornament đèn |
| ART-10 | Copy mới | không còn "SPOTLIGHT ĐANG TÌM KIẾM" |
| ART-11 | Loại Hoa tiêu | `WHERE t.team_no<>%d` |
| ART-12 | Kế hoạch đồng hạng | `art_reveal_plan` nextStage/rankCounts + nút `is-skipped` |

## G. 12 TEST TRÙNG ĐIỂM TỔNG (TC — mô phỏng thật)

| Mã | Dữ liệu (tổng điểm) | Hạng mong đợi | Ý nghĩa |
|---|---|---|---|
| TC01 | 980 940 900 850 800 750 | 1 2 3 4 5 6 | Không trùng |
| TC02 | 950 950 900 … | 1 1 3 4 5 6 | Trùng ngôi đầu |
| TC03 | 950×3 | 1 1 1 4 5 6 | Ba đội đồng nhất |
| TC04 | 950×4 | 1×4 5 6 | Bốn đội đồng nhất |
| TC05 | 950×5 | 1×5 6 | Năm đội đồng nhất |
| TC06 | 900×6 | 1×6 | Tất cả đồng điểm |
| TC07-08 | (theo code) | — | Protect rank giữa các bước không lộ sớm |
| TC09-10 | (theo code) | — | Lộ theo nhóm hạng tồn tại, bỏ hạng khuyết |
| TC11-12 | (theo code) | — | Đồng hạng công bố cùng lúc, không xé nhóm |

Mỗi TC còn chạy vòng kiểm tra: nhóm đồng điểm phải lộ **cùng lúc** và hạng dưới ngưỡng protect **không** lộ sớm ở từng bước RANK65…REVEAL3.

## H. 15 TEST PHÂN XE (BUS — mô phỏng thật)

| Mã | Case | Kỳ vọng |
|---|---|---|
| BUS-01 | Mở Xe 1 khi WAITING | ok, boarding = Xe 1 |
| BUS-02 | Mở chồng | báo `bus_already_boarding` |
| BUS-03 | Chốt 1 mở 2 atomic | ≤ 1 xe BOARDING, xe 1 CLOSED |
| BUS-04 | Chốt đến xe 5 | hoàn tất, `autoAssign` tắt |
| BUS-05 | Auto-assign chỉ Trạm 1 | Trạm 2 → null |
| BUS-06 | Chưa xe mở | check-in ok, `assigned:false`, không member |
| BUS-07 | Trạm chưa mở | không phân xe |
| BUS-08 | Quét trùng | không tạo member thứ 2 |
| BUS-09 | 1 người 1 xe | assign lần 2 → `already_assigned` |
| BUS-10 | Manual chuyển xe | moveMember ok |
| BUS-11 | Quyền thêm | BTC chỉ team 7; Super mọi team |
| BUS-12 | Reset | 5 xe WAITING, member xóa |
| BUS-13 | HDV roll-call | chỉ đúng xe mình |
| BUS-14 | BTC/Super roll-call | mọi xe |
| BUS-15 | Đổi xe giữa chừng | scan sau nhận xe mới (server quyết) |

## I. ADMIN & PHÂN QUYỀN (ADM)

| Mã | Case | Kỳ vọng |
|---|---|---|
| ADM-01 | Dashboard ngoài wp-admin | `company-trip-admin`, standalone template, redirect staff |
| ADM-02 | Login route | `/admin/login` + `wp_signon` + check `MAC_Checkin::CAP` |
| ADM-03 | Role-based nav | `canWrite`, tab overview/art, "Quét QR check-in" |
| ADM-04 | Controls đủ | gate, checkpoint, QR cá nhân, điểm, thi đua |
| ADM-05 | Sổ điểm lịch sử | `function history` + `CHECKPOINT_POINTS_FINALIZED` + reset |
| ADM-06 | Bus admin | `mac_vote_rollcall`, `mac_vote_bus_advance`, tab "Phân xe", "Xe của tôi" |
| ADM-07 | HDV chỉ 2 tab | nav guide = Check-in + Xe của tôi |

## J. TYPOGRAPHY & UI (UI)

| Mã | Case | Kỳ vọng |
|---|---|---|
| UI-01 | Font bundle | Prata + Bricolage (latin/ext/vietnamese) + license trong package |
| UI-02 | Cấm font cũ | không Cormorant/Fraunces/Newsreader/Manrope/Plus Jakarta/Google Fonts/Inter |
| UI-03 | Màn tổng | h1 Bricolage 500; badge `.mr-rank` Bricolage 600, `text-transform: none` |
| UI-04 | Podium label | nằm trong flow trên cột (không absolute chồng chéo) |
| UI-05 | Điểm bám đáy | grid-row 1/2/3 đúng tầng |
| UI-06 | Nút Phân xe | 14px/700/44px đồng bộ hệ thống |
| UI-07 | Nhịp khối thêm xe | heading/ô tên/nút margin-top 24px |
| UI-08 | Manifest mobile | ẩn cột Team/Loại, giữ họ tên + tick + chuyển xe + xóa |

---

## CÁCH CHẠY & ĐỌC KẾT QUẢ

```bash
npm run check
# Plugin source OK: 18 PHP, 8 JS, 7 CSS, … bytes.
# Tie tests: 12 cases passed.
# Bus tests: 15 cases passed (BUS-01 … BUS-15).
```

- Thêm case mới: nối vào `TIE_CASES` / `BUS_CASES` (có tên mô tả tiếng Việt) hoặc thêm invariant vào đúng nhóm.
- Mọi case fail đều ném `Error("<mã/tên>: …")` → build dừng, không sinh zip.
