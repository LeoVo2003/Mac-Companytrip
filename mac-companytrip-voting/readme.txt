=== MAC Company Trip Voting ===
Contributors: macmarketing
Tags: voting, company trip, scoring, event
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.8.0

Hệ thống chấm điểm văn nghệ nội bộ với danh sách team linh hoạt cho MAC Marketing.

== Cài đặt ==

1. Vào WordPress Admin > Plugins > Add New > Upload Plugin.
2. Tải lên file mac-companytrip-voting-v1.7.1.zip và Activate. Sau bản này WordPress tự cập nhật từ GitHub Releases.
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
- Mỗi team có cửa sổ check-in bằng đúng số phút cài khi mở mốc, bắt đầu từ lượt quét đầu tiên; hết giờ máy quét khóa và báo lỗi.
- Admin miễn check-in từng mốc kèm lý do; người miễn không tính vào mẫu số và ẩn khỏi danh sách còn thiếu.
- Hạng mục cũ chuyển thành "lần thi đua", giữ nguyên dữ liệu cũ.

== Changelog ==

= 1.8.0 =
- QR: máy quét tự bóc token ngay trên điện thoại trước khi gửi, server thêm nhiều lớp bóc link dự phòng và chẩn đoán chi tiết hơn khi lỗi — hết cảnh "QR không đúng định dạng" khi quét link đầy đủ.
- Bảng Tổng quan & Check-in trên mobile kéo ngang được, cột đội ghim chặt bên trái khi cuộn.
- Máy quét BTC trên mobile gọn lại: khung camera vuông vừa màn hình, ẩn thêm các khối thừa của theme.
- Nút "Xuất CSV check-in" và các nút trên header panel xuống dòng gọn trên mobile.
- Miễn check-in có ô tìm nhanh: gõ tên là lọc ra người cần miễn trong danh sách 200 người.
- Chấm điểm thi đua và xếp hạng game cập nhật ngay lập tức trên màn hình rồi mới lưu ngầm, hết cảnh chờ 15-30 giây.

= 1.7.9 =
- Màn hình đăng nhập dashboard: ô username hết dính viền đen/nền trắng của theme, focus đỏ nhạt nhất quán; nút "Đăng nhập" dùng gradient đỏ-cam chuẩn như hành động chính.
- Tên nhân sự hiển thị Title Case (Đào Ngọc Trâm) ở danh sách Nhân sự & QR, máy quét check-in và thông báo quét; import CSV mới cũng lưu chuẩn Title Case.

= 1.7.8 =
- QR ngắn còn ~72 ký tự (trước 142): mắt QR thưa hơn nên camera điện thoại đọc chắc hơn hẳn; QR dài cũ vẫn quét được bình thường.
- Tự flush rewrite rules khi nâng phiên bản: link QR /company-trip/q/... mở bằng camera mặc định sẽ vào thẳng trang vote thay vì văng về trang chủ.
- Lỗi quét kèm đoạn mã dài hơn (120 ký tự) để bắt lỗi nhanh nếu còn QR khó đọc.

= 1.7.7 =
- Máy quét check-in nhận QR ngay cả khi chữ ký khác environment (fallback có audit QR_SIGNATURE_FALLBACK), hết lỗi "QR không hợp lệ" khi dashboard và máy quét khác miền/salt.
- Lỗi QR nói rõ lý do (sai định dạng / sai chữ ký / QR cũ / không ACTIVE) và kèm đoạn mã đã quét để bắt lỗi nhanh.

= 1.7.6 =
- Tổng quan: hiện lại tên đội dưới mỗi cột biểu đồ (phần điểm trùng lặp vẫn đã bỏ).
- Làm tròn số: TB phiếu và điểm trực tiếp không còn ".0"/".00" (hiện 150 thay vì 150.00).
- Bảng tài khoản máy quét: tên và email tách hai dòng rõ ràng, hết dính chữ.
- Thang hạng game: hạng 4/5/6 có màu riêng (xanh dương / xanh lục / xám nhạt) thay vì giống nhau.

= 1.7.5 =
- Máy quét check-in: số phút cửa sổ lấy đúng theo số phút cài khi mở mốc, không còn hard-code 15'.
- Nút Thoát trên máy quét thay bằng "← Quay lại dashboard" về thẳng dashboard BTC.
- Sửa hover/active trên máy quét: chữ không mờ trắng, nút team và link không còn dính màu tím hồng của theme.
- Quét QR nhân sự báo đúng nguyên nhân: QR đã cũ (khác phiên bản) hoặc nhân sự không còn ACTIVE, thay vì chỉ "QR không hợp lệ".

= 1.7.4 =
- Trang vote công khai: đồng hồ đếm ngược chạy từng giây và tự làm mới khi lượt đóng.
- Rule mới: mỗi lượt phải gửi phiếu đủ cả 2 tiết mục hoặc không chấm; nháp giữ tự động, REST chặn phiếu lẻ.
- Tổng quan: bỏ caption dưới cột biểu đồ; bảng có khoảng hở phía trên và cột đều nhau (table-layout fixed).
- Khoảng hở giữa các panel: máy quét QR vs mốc check-in, thang hạng vs ma trận vs thẻ game.
- Chọn tài khoản máy quét: bỏ chữ WordPress, dropdown và nút lưu làm lại, tên — email tách rõ.
- Popup đẹp cho thêm/sửa/xóa hạng mục thi đua (đổi tên “lần thi đua” → “hạng mục thi đua”) và hủy phiếu/cho vote lại.
- Sửa lỗi hover mất chữ ở nút chọn team thi đua và nút Xuất CSV kết quả.
- Popup xác nhận admin gọn đẹp đồng bộ với popup trang vote.

= 1.7.3 =
- Thay toàn bộ alert/confirm mặc định của trình duyệt bằng popup xác nhận đồng bộ thiết kế (mở mốc, đóng mốc, bật/tắt cổng văn nghệ, mở/đóng lượt vote, xóa team, tạo lại QR…).
- Làm lại nút Tắt/Bật cổng văn nghệ: 44px, bo góc, trạng thái đỏ cảnh báo khi tắt.
- Hàng điều khiển lượt vote: tên lượt bên trái, ô "tự đóng sau" rồi nút Mở vote/Mở lại bên phải.
- Tên team dài trong biểu đồ tổng quan tự xuống dòng, không còn cột lệch rộng.
- Sửa màu hover trên toàn bộ nút, select và ô nhập; bảng có khoảng hở với mép panel giống bảng QR cá nhân.
- Tối ưu mobile/tablet: biểu đồ 3 cột ≤700px, 2 cột ≤430px, thẻ mốc check-in 1 cột, nút lượt vote dàn đều.

= 1.7.2 =
- Updater ưu tiên tải đúng zip trùng phiên bản tag, không nhặt nhầm zip cũ do workflow GitHub đính kèm vào release.
- Script tạo release tự xóa zip thừa khác phiên bản.

= 1.7.1 =
- Sidebar 6 tab: Tổng quan, Check-in, Trò chơi lớn, Văn nghệ, Thi đua, Nhân sự & QR. Tổng quan chỉ còn Tổng điểm và Lịch sử.
- Tổng quan xếp lại: biểu đồ cột → bảng 4 trụ cột → check-in → trò chơi → văn nghệ → thi đua.
- Mở mốc check-in và mở vote chỉ hỏi xác nhận; thời gian tự đóng (15 phút / 5 phút) nằm ở ô nhập ngay cạnh nút.
- Bảng điểm theo tỷ lệ có mặt gom thành 1 ma trận: đội × mốc, mỗi ô hiện số người/điểm.
- Tab Trò chơi lớn làm lại: thang hạng trực quan, ma trận tổng và thẻ chấm hạng từng game.
- Đồng bộ hover, màu chữ và bảng trên toàn dashboard.

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
