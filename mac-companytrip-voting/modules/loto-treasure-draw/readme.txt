=== Lô Tô Kho Báu - Lucky Draw ===
Contributors: 
Tags: lucky draw, lo to, team building, quay so trung thuong
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Plugin bốc thăm trúng thưởng chủ đề hải trình / cướp biển cho chương trình lô tô - team building.

== Mô tả ==

Dành cho phần lô tô trong chương trình team building chủ đề hải trình:

1. Người chơi thắng lô tô như bình thường.
2. MC mở trang "Điều khiển" trên điện thoại, nhập tên người thắng, bấm "Dò la bàn".
3. Màn hình LED (mở trên máy tính/trình chiếu) tự động chạy hiệu ứng: thuyền buồm đi trên bản đồ kho báu tới một điểm ngẫu nhiên, rương kho báu mở ra và hiện hình ảnh phần quà cùng tên người thắng.

Toàn bộ phần thưởng (tên, hình ảnh, số lượng) được quản lý trong trang "Kho tàng", upload hình ảnh bằng thư viện Media có sẵn của WordPress.

== Cài đặt ==

1. Vào Plugins > Add New > Upload Plugin, chọn file zip này.
2. Kích hoạt plugin.
3. Vào menu "🏴‍☠️ Lô Tô Kho Báu" > "💰 Kho tàng" để thêm các phần thưởng kèm hình ảnh và số lượng.
4. Vào "📺 Màn hình LED" để lấy link, mở link đó trên máy tính nối với màn hình LED / máy chiếu, bật toàn màn hình.
5. Vào "🧭 Điều khiển" trên điện thoại của MC để bắt đầu quay thưởng trong chương trình.

== Changelog ==

= 1.0.0 =
* Phát hành đầu tiên.

= 2.1.0 =
* Nâng cấp toàn bộ giao diện màn hình LED (/?ltr_display=1) theo phong cách "hải trình kho báu" điện ảnh.
* Sửa 3 vấn đề cốt lõi:
  - Thuyền luôn quay mũi đúng hướng di chuyển (vẽ lại SVG với mũi tàu chỉa +X, khớp quy ước góc 0° = phải).
  - Tốc độ thuyền đồng đều giữa các tuyến (thời lượng tính theo chiều dài path thật, không gán cứng).
  - 6 vị trí kho báu rải đều toàn bản đồ, không còn chồng lấn (toạ độ mới + path tương ứng).
* 6 đảo kho báu khác biệt (cây dừa, mỏm đá đầu lâu, hang động, xác tàu đắm, hải đăng, núi lửa) thay vì 6 dấu X trùng nhau.
* Nền biển có sóng parallax + mây trôi + vệt sáng; hiệu ứng camera zoom nhẹ và rung màn hình lúc mở rương.
* Thẻ phần thưởng có animation "thở"; badge số quà còn lại + dải chữ chạy lịch sử 3 lượt gần nhất.
* Dùng font Pirata One cho tiêu đề/tên phần thưởng; hỗ trợ reduced-motion; thuần vanilla JS/CSS, không thêm dependency.
