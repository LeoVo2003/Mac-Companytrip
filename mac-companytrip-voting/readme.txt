=== MAC Company Trip Voting ===
Contributors: macmarketing
Tags: voting, company trip, scoring, event
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.5.0

Hệ thống chấm điểm văn nghệ nội bộ với danh sách team linh hoạt cho MAC Marketing.

== Cài đặt ==

1. Vào WordPress Admin > Plugins > Add New > Upload Plugin.
2. Tải lên file mac-companytrip-voting-v1.5.0.zip và Activate. Sau bản này WordPress tự cập nhật từ GitHub Releases.
3. Plugin tự tạo trang Chấm Điểm Văn Nghệ [mac_companytrip_vote], trang Kết Quả Văn Nghệ [mac_companytrip_results] và trang Check-in [mac_companytrip_checkin].
4. Vào menu MAC Company Trip để import nhân sự, gửi QR cá nhân qua email và xếp lịch.
5. Gán tài khoản BTC ở tab Check-in, mở /company-trip-checkin/ để quét QR.
6. Cổng văn nghệ mặc định tắt. Bật khi bắt đầu chấm điểm.
7. Nếu link vẫn có dạng ?page_id=..., vào Settings > Permalinks, chọn Post name rồi Save Changes.

== Import nhân sự ==

Tải CSV mẫu trong trang Nhân sự & dữ liệu. Các cột bắt buộc:

- Họ tên
- Team
- Email

Cột tùy chọn: Mã NV, Trạng thái. File phải lưu dạng CSV UTF-8.

Email có thể ghi đầy đủ hoặc chỉ username. Hệ thống tự nối và chỉ chấp nhận tên miền @macusaone.com.

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

- Tab Tổng điểm: dashboard 6 team, thêm hạng mục và cộng/trừ điểm từng đội.

== Changelog ==

= 1.5.0 =
- Tự cập nhật từ GitHub Releases, không cần upload zip lại sau lần cài đầu.
