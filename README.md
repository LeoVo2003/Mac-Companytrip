# MAC Company Trip Voting — WordPress Plugin

Source plugin nằm tại `mac-companytrip-voting/`. File cài lần đầu: `npm run build` → `dist/mac-companytrip-voting-v1.5.0.zip`.

Từ bản 1.5.0, WordPress tự kéo bản mới từ GitHub Releases. Cursor không đẩy file thẳng lên WP; luồng đúng là **Cursor → GitHub Release → WP tự update**.

## Cài vào WordPress

1. Source đã gắn sẵn repo `LeoVo2003/Mac-Companytrip`. Chạy `npm run build`.
2. WordPress Admin → Plugins → Add New → Upload Plugin.
3. Chọn file `dist/mac-companytrip-voting-v1.5.0.zip`, cài và Activate. Đây là lần upload tay **duy nhất**.
4. Các bản sau: tăng `Version` trong file PHP, `MAC_VOTING_VERSION` và `package.json` cho cùng số, commit, rồi `git tag v1.5.1` và `git push origin main --tags`. GitHub Actions tạo Release; WordPress tự cài (hoặc bấm **Kiểm tra cập nhật**).
5. Plugin tự tạo trang chấm điểm `[mac_companytrip_vote]`, trang công bố `[mac_companytrip_results]` và trang check-in `[mac_companytrip_checkin]`.
6. Nếu WordPress đang trả link dạng `?page_id=...`, vào **Settings → Permalinks**, chọn **Post name / Tên bài viết** rồi lưu để dùng `/cham-diem-van-nghe/` và `/company-trip-checkin/`.
7. Vào menu **MAC Company Trip** trong WP Admin.
8. Tải CSV mẫu, điền nhân sự rồi import.
9. Đổi tên/thêm team nếu cần, xếp 6 team biểu diễn vào 6 slot rồi mở từng lượt.
10. Vào **Nhân sự & QR** để xem QR cá nhân và gửi email cho từng người/team.
11. Gán tài khoản BTC ở tab **Check-in**, rồi mở `/company-trip-checkin/` trên điện thoại để quét.
12. Cổng văn nghệ mặc định TẮT. Bật trên tab Tổng quan khi đến giờ chấm điểm.

## Dữ liệu import

CSV UTF-8 gồm `Họ tên, Mã NV, Team, Email, Trạng thái`. Ba cột bắt buộc là Họ tên, Team, Email. Email có thể ghi đầy đủ hoặc chỉ username; hệ thống luôn chuẩn hóa về tên miền `@macusaone.com`.

## Phase 1

- Login username email công ty và tự mapping đúng nhân sự/team.
- Session bền trên điện thoại và giới hạn thử sai.
- Khi mở lượt, người dùng chọn riêng từng tiết mục được phép chấm; quay lại danh sách không tạo điểm 0.
- Mỗi tiêu chí chọn 1–5 sao, tương ứng 10–50 điểm.
- 3 lượt × 2 tiết mục; admin đổi slot khi lượt còn DRAFT.
- Chấm tuần tự từng tiết mục, bắt buộc đủ 3 tiêu chí.
- Chặn team mình ở server và unique index chống vote trùng.
- Trung bình trên số phiếu hợp lệ; phiếu rỗng là “Chưa có lượt vote”.
- Hủy phiếu và cấp vote lại riêng, luôn có audit log.
- Xuất CSV kết quả và chi tiết phiếu.

## Phase 2

- Trang trình chiếu `/ket-qua-van-nghe/` tự đồng bộ với admin.
- Sáu cột tung điểm liên tục khi bắt đầu màn công bố.
- Cú lừa đưa ba đội thấp điểm nhất lên trước nhưng vẫn dùng đúng điểm thật của họ.
- Admin bấm riêng ba lần để công bố hạng 3, hạng 2 và quán quân; màn hình không tự chạy qua các hạng.
- Hiệu ứng pháo sáng và confetti dành cho quán quân; tự giảm hiệu ứng khi thiết bị bật Reduced Motion.
- Endpoint chỉ trả điểm của các đội đã đến lượt công bố; mỗi lần bấm mở thêm đúng thứ hạng tương ứng.

## Phase 3

- QR cá nhân HMAC, gửi qua email, regenerate làm mất hiệu lực QR cũ.
- Cổng văn nghệ ON/OFF; mặc định tắt; check-in không bị ảnh hưởng.
- 4 mốc check-in, máy quét BTC, chống quét trùng/sai team; điểm mỗi mốc tối đa 150đ theo tỷ lệ có mặt.

## Phase 4

- Bảng điểm 4 trụ cột: **Check-in** (4 mốc × 150đ = 600đ), **Trò chơi lớn** (3 game, hạng 1–6 nhận 50/40/30/20/10/0đ = 150đ), **Văn nghệ** (quy đổi `ROUND(TB phiếu hợp lệ ÷ 150 × 200)`) và **Thi đua** (thang 50/40/30/20/10, không giới hạn).
- Mỗi team có cửa sổ 15 phút cho mỗi mốc check-in (đồng hồ server), bắt đầu từ lượt quét đầu; hết giờ máy quét trả lỗi `window_locked`.
- Miễn check-in theo từng mốc kèm lý do; người miễn bị trừ khỏi mẫu số và ẩn khỏi danh sách "CÒN THIẾU".
- "Hạng mục" cũ thành "lần thi đua": bảng `point_categories` tự đổi tên thành `mac_vote_thidua_rounds`, giữ nguyên dữ liệu; ledger ghi nguồn `THIDUA` và vẫn đọc nguồn cũ `CATEGORY`.
