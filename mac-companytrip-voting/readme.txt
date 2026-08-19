=== MAC Company Trip Voting ===
Contributors: macmarketing
Tags: voting, company trip, scoring, event
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.7.0

Hệ thống chấm điểm văn nghệ nội bộ với danh sách team linh hoạt cho MAC Marketing.

== Cài đặt ==

1. Vào WordPress Admin > Plugins > Add New > Upload Plugin.
2. Tải lên file mac-companytrip-voting-v1.7.0.zip và Activate. Sau bản này WordPress tự cập nhật từ GitHub Releases.
3. Plugin tự tạo trang Chấm Điểm Văn Nghệ [mac_companytrip_vote], trang Kết Quả Văn Nghệ [mac_companytrip_results], trang Check-in [mac_companytrip_checkin] và dashboard [mac_companytrip_admin].
4. Mở /company-trip-admin/ để đăng nhập dashboard. Không cần vào wp-admin.
5. Super admin thao tác toàn bộ. BTC xem dashboard và vào máy quét /company-trip-checkin/. Cả hai role được tạo từ CSV và không có quyền wp-admin.
6. Cổng văn nghệ mặc định tắt. Bật khi bắt đầu chấm điểm.
7. Nếu link vẫn có dạng ?page_id=..., vào Settings > Permalinks, chọn Post name rồi Save Changes.

== Import nhân sự ==

Tải CSV mẫu trong trang Nhân sự & dữ liệu. Các cột bắt buộc:

- Họ tên
- Team
- Email

Cột tùy chọn: Mã NV, Trạng thái, Vai trò, Mật khẩu. File phải lưu dạng CSV UTF-8.

Email có thể ghi đầy đủ hoặc chỉ username. Hệ thống chấp nhận @macusaone.com, @yesoffice.vn và @macmarketing.vn; khi chỉ ghi username, mặc định là @macusaone.com.

Cột Vai trò ghi BTC hoặc Super admin sẽ tạo tài khoản dashboard riêng. BTC/Super admin luôn thuộc Team #7 Hoa tiêu, không tham gia chấm điểm hoặc các lần thi đua. Cột Mật khẩu để trống sẽ dùng mặc định MAC-Trip2026.

== Quy tắc ==

- Mỗi người chỉ chấm một phiếu hợp lệ cho mỗi tiết mục.
- Thành viên không thể chấm team mình.
- Mỗi phiếu phải có đủ 3 tiêu chí, thang 10/20/30/40/50.
- Người dùng chọn từng tiết mục trước khi chấm; quay lại danh sách không tạo điểm 0.
- 1–5 sao tương ứng 10–50 điểm cho mỗi tiêu chí.
- Chỉ một lượt được mở tại một thời điểm.
- Lượt đã đóng có thể được admin mở lại khi không có lượt khác đang mở; phiếu cũ được giữ và chỉ người chưa vote được tiếp tục.
- Phiếu hủy không tự được vote lại; admin phải cấp quyền riêng có lý do.
- Quyền vote lại có hiệu lực ngay cho đúng người và đúng tiết mục, kể cả khi lượt đã đóng.
- Admin có thể đổi tên, thêm team; chỉ xóa được team không còn nhân sự, phiếu và không nằm trong lịch.
- Đặt lại sự kiện xóa toàn bộ phiếu/quyền vote lại và đưa các lượt về DRAFT, nhưng giữ nhân sự, team, lịch và audit log.
- Điểm trung bình bằng tổng điểm ba tiêu chí của phiếu hợp lệ chia số phiếu hợp lệ.
- Không có phiếu hiển thị Chưa có lượt vote, không tính 0.

== Phase 2 ==

- Trang /ket-qua-van-nghe/ hiển thị biểu đồ 6 đội và tự đồng bộ với admin.
- Cú lừa dùng ba đội thấp điểm nhất nhưng vẫn hiển thị đúng điểm thật của họ; admin bấm riêng ba lần để công bố hạng 3, hạng 2 và quán quân.
- Hiệu ứng pháo sáng/confetti dành cho quán quân và có chế độ giảm chuyển động.

== Phase 3 ==

- Mỗi nhân sự có một QR cá nhân, gửi qua email, dùng chung cho check-in và login văn nghệ.
- Cổng văn nghệ bật/tắt độc lập với check-in.
- 4 mốc check-in, máy quét BTC, lưu dữ liệu và xếp hạng khi đóng mốc.

== Phase 4 ==

- Bảng điểm 4 trụ cột: Check-in (4 mốc x 150đ), Trò chơi lớn (3 game, hạng 1-6 nhận 50/40/30/20/10/0đ), Văn nghệ (quy đổi ROUND(TB phiếu ÷ 150 × 200)) và Thi đua (thang 50/40/30/20/10, không giới hạn).
- Mỗi team có cửa sổ 15 phút cho mỗi mốc check-in, bắt đầu từ lượt quét đầu tiên; hết giờ máy quét khóa và báo lỗi.
- Admin miễn check-in từng mốc kèm lý do; người miễn không tính vào mẫu số và ẩn khỏi danh sách còn thiếu.
- Hạng mục cũ chuyển thành "lần thi đua", giữ nguyên dữ liệu cũ.

== Changelog ==

= 1.7.0 =
- Bảng điểm 4 trụ cột: Check-in 600đ, Trò chơi lớn 150đ, Văn nghệ 200đ quy đổi từ TB phiếu hợp lệ, Thi đua không giới hạn.
- Cửa sổ check-in 15 phút cho từng team mỗi mốc, đồng hồ server, hết giờ khóa quét.
- Miễn check-in theo mốc kèm lý do, trừ khỏi mẫu số điểm.
- Xếp hạng 3 trò chơi lớn theo thang 50/40/30/20/10/0đ.
- Điểm thi đua chỉ nhận thang 50/40/30/20/10/0; hạng mục cũ tự chuyển thành lần thi đua và giữ dữ liệu.
- Đặt lại sự kiện xóa thêm cửa sổ, danh sách miễn và điểm game/thi đua.

= 1.6.3 =
- Thêm domain email @macmarketing.vn cho login và import CSV.

= 1.6.2 =
- Preview import CSV chấp nhận email @yesoffice.vn, không chỉ @macusaone.com.

= 1.6.1 =
- Hỗ trợ @yesoffice.vn trong đăng nhập và CSV, mặc định vẫn là @macusaone.com.
- CSV tạo BTC hoặc Super admin với mật khẩu mặc định MAC-Trip2026; Team #7 Hoa tiêu được loại khỏi mọi bảng điểm.

= 1.6.0 =
- Dashboard web app tại /company-trip-admin/, login riêng, không cần wp-admin.
- Super admin thao tác toàn bộ. Admin xem Tổng quan, Văn nghệ, Nhân sự & QR; Check-in xem tiến độ và vào máy quét BTC.
- Import CSV có cột Vai trò (BTC) để cấp tài khoản dashboard.

= 1.5.0 =
- Tự cập nhật từ GitHub Releases, không cần upload zip lại sau lần cài đầu.
