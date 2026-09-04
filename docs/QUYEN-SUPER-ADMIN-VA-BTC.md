# Phân quyền hệ thống Company Trip: Super Admin vs BTC vs HDV

> Cập nhật theo plugin bản 1.10.37. Nguồn: `class-mac-voting-admin.php` (guard levels), `class-mac-checkin.php` (roles/caps), `admin.js` (canWrite / canOperate).

## 1. Định danh tài khoản

| | Super Admin | BTC / Hoa tiêu | HDV Vietravel |
|---|---|---|---|
| Role WordPress | `mac_companytrip_super_admin` (cap `mac_manage_companytrip`) — hoặc mọi WP administrator (`manage_options`) | `mac_btc_checkin` (cap `mac_checkin`) | `mac_bus_guide` (cap `mac_bus_rollcall`) |
| Cách tạo | Nút "Cấp quyền" ở tab Nhân sự & QR, hoặc cột note khi import CSV | Cùng chỗ đó, chọn loại BTC | Bảng HDV VIETRAVEL trong tab Phân xe |
| Role trong dashboard JS | `super` (`canWrite() = true`) | `admin` (`canWrite() = false`, `canOperate() = true`) | `guide` |
| Vào dashboard Company Trip | ✓ | ✓ | ✓ (chỉ 2 tab) |
| Tab nhìn thấy | Tất cả | Tất cả | Check-in + Xe của tôi |

## 2. Việc CHỈ SUPER ADMIN làm được

Server chặn bằng `guard()` mức `super`; UI ẩn hẳn khối nút với role khác.

- **Đặt lại dữ liệu**: nút đặt lại từng tab (Check-in / Phân xe / Trò chơi lớn / Văn nghệ / Thi đua) và "Đặt lại sự kiện" (phải gõ `RESET`).
- **Xóa toàn bộ nhân sự** (phải gõ `XOA`) — xóa sạch nhân sự kèm phiếu, check-in, thành viên xe, miễn trừ, quyền vote lại.
- **Đánh dấu Tự túc / Bỏ tự túc** từng người (cờ đi xe riêng, phát sinh không cần import lại).
- **Nhân sự & QR**: import CSV, thêm người, gửi QR hàng loạt, xem & gửi / tạo lại QR từng người, cấp quyền tài khoản Super/BTC mới.
- **Cổng văn nghệ**: bật / tắt cổng chấm điểm; mở / đóng / mở lại vòng vote; thêm / sửa / xóa team.
- **Phân xe**: mở / đóng xe thủ công, gán tay người chưa phân xe, gán bảng TỰ TÚC, form chèn người vào xe, xuất tổng 5 xe gửi resort.
- **Tài khoản HDV**: thêm, đổi xe phụ trách, xóa tài khoản.

## 3. Việc BTC làm NGANG Super Admin (mức `operator`)

- **Thi đua**: thêm / đổi tên / xóa lần thi đua; cộng / xóa điểm team (`ajax_points`).
- **Trò chơi lớn**: xếp hạng 3 game (`ajax_games`).
- **Quét QR check-in** bằng tài khoản máy quét (cap `mac_checkin`); BTC được quét mọi team đang thi đấu.
- Xem toàn bộ tab còn lại (Tổng quan, Check-in, Phân xe, Văn nghệ, Nhân sự & QR) ở chế độ **chỉ xem** — bảng nhân sự hiện chú thích "Chỉ xem danh sách. Super admin mới gửi hoặc tạo lại QR."

## 4. Việc cả ba nhóm đều làm được

- Xem Tổng quan, bấm "Tải lại dữ liệu", xem bảng điểm / màn hình kết quả.
- Mọi thao tác ghi của Super Admin và BTC đều được ghi vào **audit log**.

## 5. Tóm tắt một câu

- **BTC = người vận hành đêm sự kiện**: chấm thi đua, xếp hạng game, quét check-in, xem mọi thứ nhưng không đụng dữ liệu gốc.
- **Super Admin = BTC + toàn quyền dữ liệu**: nhân sự, QR, cổng vote, phân xe, reset, tài khoản.
- **HDV = máy quét trên xe**: chỉ Check-in và điểm danh xe mình phụ trách.
