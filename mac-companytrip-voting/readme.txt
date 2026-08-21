=== MAC Company Trip Voting ===
Contributors: macmarketing
Tags: voting, company trip, scoring, event
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.8.19

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

= 1.8.19 =
- Tên đội và điểm số dưới chân cột phóng to hơn (desktop lẫn mobile), nới hàng lưới cho khớp.
- Bỏ hẳn đường kẻ mr-horizon.

= 1.8.18 =
- Trả seascape về đúng màu bản tham chiếu: mr-sun mờ rộng nhẹ, khôi phục mr-horizon + mr-chart-lines, palette biển gốc.
- La bàn bỏ rotate(-7deg) để kim đỏ chỉ đúng 12h; vòng sóng .mr-shell::after mở rộng tới 70-75% rồi mờ dần tan biến.

= 1.8.17 =
- Dọn seascape: bỏ mr-horizon và mr-chart-lines vô nghĩa; mr-sun vẽ lại thành đĩa nắng hoàng hôn rõ hơn; màu biển đậm hơn.
- La bàn to hơn nhưng mờ hơn; mặt số (vòng hướng) xoay tròn mượt liên tục, kim đứng yên với đầu đỏ (bắc) luôn chỉ 12h.
- Logo header to hơn (~100px), bỏ huy hiệu la bàn nhỏ cạnh logo.

= 1.8.16 =
- Làm lại giao diện màn công bố theo style "hải trình" (seascape, la bàn lớn với kim quay theo stage, header brand lockup) — giữ nguyên font chữ và nội dung hiện tại.
- Điểm số chuyển xuống dưới cùng mỗi cột (dưới tên đội) như layout mới.

= 1.8.15 =
- La bàn vẽ lại nét mảnh và mờ hơn (line-art clean), kim luôn đung đưa nhẹ ở mọi giai đoạn thay vì quay/khóa theo stage.
- Bỏ dòng mô tả màn chờ "6 đội · 1 hải trình · 1 ngôi vị cao nhất" (dành cho mục đích khác); header đổi "COMPANY TRIP · ONE COMPASS" → "company trip - One Direction".

= 1.8.14 =
- Kicker màn chờ đổi từ "MAC MARKETING" sang "KẾT QUẢ VĂN NGHỆ".
- Tiêu đề lớn (h1) nâng line-height lên 1.2 cho thoáng chữ có dấu tiếng Việt.

= 1.8.13 =
- Redesign màn hình công bố kết quả theo bộ nhận diện "One Compass": nền biển đêm sâu + la bàn đồng cổ, tiêu đề serif display kiểu khắc bảng đồng.
- La bàn SVG lớn làm watermark sau biểu đồ, kim quay thuần CSS theo từng giai đoạn công bố (đung đưa → quay loạn → lưỡng lự → khóa hướng, bùng glow vàng đồng lúc quán quân).
- Cột điểm mặc định đồng brass, hạng nhì bạc pewter, hạng ba đồng tối, quán quân vàng đồng hoàng hôn; pháo hoa chuyển thành dải kim tuyến vàng đồng.
- Ẩn footer, thu nhỏ logo header, nới khoảng cách tiêu đề ↔ biểu đồ; badge hạng gọn hơn trên mobile để 6 cột không tràn nhau.

= 1.8.12 =
- Thêm nút "Áp dữ liệu demo" đặt kín ở sidebar (chỉ super admin): 1 click ghi bộ dữ liệu diễn tập vào database — 48 nhân sự ảo, 240 phiếu hợp lệ, điểm check-in · trò chơi · thi đua theo kịch bản.
- Bấm lại chỉ ghi đè bộ demo, không nhân bản; dữ liệu lưu trong database cho tới khi "Đặt lại sự kiện".
- Màn hình công bố kết quả chuyển sang chủ đề hải trình: bầu trời powder-blue đổ xuống biển sâu, tiêu đề chrome bạc, cột điểm xanh đại dương, á quân/hạng ba chrome bạc và vàng kim chỉ dành cho quán quân.
- Pháo hoa quán quân và copy màn hình đổi theo tông biển (One Compass, ✦ la bàn).

= 1.8.11 =
- Sửa loader bánh xe: 9 bi nằm đều trên vành khuyên, pulse đuổi tuần tự (bản trước bi bay từ tâm ra nên tụm lại xấu).
- Khôi phục trạng thái active của tab sidebar và subnav trên trang WP (bản trước bị nhóm hardening đè mất màu đỏ).
- Desktop: tab sidebar không còn border trắng, trở lại nền trong suốt như bản gốc.
- Nút "Đặt lại sự kiện" nổi bật màu danger đỏ đậm như bản gốc, không còn giống nút trung tính.

= 1.8.10 =
- Thay màn hình chờ bằng loader bánh xe phối màu thương hiệu MAC (cảm hứng lucky-hound-44) cho cả 3 nơi dựng dashboard.
- Hardening toàn bộ giao diện /company-trip-admin/ chống theme đè: nút, bảng, ô nhập liệu, link sidebar dùng !important scope theo body.mac-admin-public-page.
- Refactor toàn bộ CSS dashboard: gộp các rule trùng lặp/đè nhau giữa các phiên bản, thống nhất thang font-size (10–30px) và font-weight (500/600/700/800), gom responsive breakpoint về một khối; đồng bộ font-weight trong CSS check-in, vote và kết quả.
- Mobile: cột team trong các bảng điểm tổng quan co đúng bằng tên đội dài nhất nên mọi bảng thẳng hàng nhau, hết cảnh bảng rộng bảng hẹp.
- Mobile: chuyển tab xong thanh tab tự kéo tab đang chọn vào giữa tầm nhìn, không còn giật về vị trí đầu rồi phải vuốt lại.

= 1.8.9 =
- /company-trip-admin/ render toàn trang bằng template riêng của plugin (không wp_head/footer), bỏ hoàn toàn header/footer/CSS của theme nên giao diện khớp 100% với dashboard.

= 1.8.8 =
- Trạm check-in hết hạn tự đóng hiển thị đồng bộ trong cả dashboard admin lẫn trang Quét QR check-in.
- Đổi tên toàn bộ "mốc" thành "Trạm" và "Máy quét BTC" thành "Quét QR check-in".
- Bảng điểm: TB phiếu văn nghệ (x/150) xuống dòng riêng dưới điểm cột Văn nghệ.
- Khối Tài khoản Quét QR check-in: desktop 3 cột, mobile 2 cột, bỏ sticky cột BTC; bảng tiến độ hiện "Trạm N" kèm tên trạm làm mô tả nhỏ.
- Mật khẩu mặc định mọi tài khoản BTC/Super admin là Mac-123; nâng cấp lên 1.8.8 tự đồng bộ tài khoản cũ.
- Nút "Gửi QR cho danh sách đang lọc" đồng bộ style nút chính.

= 1.8.7 =
- Nút "+ Thêm người" mở popup thêm nhân sự: họ tên, email ảo tự sinh @macusaone.com (sửa được), team, vai trò, mật khẩu; mặc định chỉ thêm vào danh sách nhân sự, không tạo tài khoản WordPress.
- Danh sách "Gửi QR qua email" thêm nút "Cấp quyền" từng người: gán vai trò BTC hoặc Super admin cho bất kỳ ai có email, tạo tài khoản máy quét tại chỗ; thông tin đăng nhập hiện một lần.

= 1.8.6 =
- Toàn bộ thời gian hiển thị (lịch sử cộng điểm, thời gian phiếu, giờ check-in, file CSV) quy về giờ Hà Nội UTC+7 thay vì UTC.
- Màn hình chờ vote: brand xếp dọc căn giữa (logo trên, chữ dưới) và bỏ dòng chữ "MAC COMPANY TRIP" thừa phía trên tiêu đề.

= 1.8.5 =
- Mobile: cột team trong các bảng điểm không còn cứng 180px mà tự co vừa tên đội dài nhất (table-layout auto + nowrap); các bảng cùng nội dung team nên vẫn thẳng hàng.
- Tab Nhân sự & QR thêm khối "Thêm nhân sự · tạo tài khoản BTC": nhập họ tên, chọn team, email tùy chọn (để trống hoặc thuộc bất kỳ domain nào — hỗ trợ tạo tài khoản cho agency), vai trò BTC hoặc Super admin.
- Vai trò BTC/Super admin tạo tài khoản WordPress đăng nhập được máy quét; username tự sinh từ email hoặc họ tên khi không có email; mật khẩu tự tạo nếu để trống; thông tin đăng nhập chỉ hiện một lần.

= 1.8.4 =
- Bỏ dòng thông báo "Đã giữ nháp... Chấm tiếp... rồi gửi cùng một lần" ở màn vote.
- Sau khi chấm xong một đội, thẻ đội đó trong màn chọn tiết mục tự mờ đi kèm nhãn "Đã chấm · sửa →"; chạm vào vẫn mở lại phiếu để sửa như hiện tại.

= 1.8.3 =
- Đồng hồ đếm ngược ở trang vote văn nghệ chạy đúng: parse thời gian UTC chuẩn (thêm T/Z) thay vì để trình duyệt tự đoán chuỗi MySQL, hết cảnh đứng im hoặc lệch 7 giờ.
- Chấm sao không còn bị mất: chọn sao trực tiếp bằng click (không phụ thuộc label-forward), tắt preview hover trên màn cảm ứng, tự lưu nháp sau mỗi lần đổi điểm nên quay lại chọn đội hay đổi tiết mục vẫn giữ nguyên điểm.
- Phiếu chấm mobile: vỏ thẻ rộng 680→760px, chữ tiêu chí co giãn theo màn, số điểm (10/20/30/40/50 điểm) không rớt dòng; màn ≤560px số điểm tự xuống hàng riêng thẳng lề phải.

= 1.8.2 =
- Máy quét BTC đổi luồng: mở vào là trang chọn team (kèm tiến độ từng team), chạm team mới vào trang quét riêng; nút "← Chọn team" để đổi team.
- Trang quét hiện danh sách đầy đủ thành viên bên dưới camera: người đã quét mờ đi kèm ✓, người chưa quét nổi bật, người được miễn gắn nhãn "Miễn".
- Phải bật cổng văn nghệ trước mới được mở vote: mở mới và mở lại lượt đều bị chặn khi cổng đang tắt, báo rõ lý do trên dashboard.

= 1.8.1 =
- Bỏ tự reload 5 giây ở dashboard: kéo bảng không bị giật về đầu, đang gõ không mất chữ, mở inspect không bị render lại liên tục.
- Thanh tab trên tablet/mobile dính cứng trên cùng khi cuộn trang; tab đang chọn luôn tự cuộn ra giữa thanh tab.
- Mobile: bảng tổng kết bỏ cột hạng, chỉ giữ cột đội; cột đội của các bảng chồng nhau đồng bộ cùng rộng để thẳng hàng.
- Tìm nhanh trong miễn check-in giữ nguyên từ khóa và kết quả lọc sau mỗi lần dữ liệu tải lại.
- Ô nhập RESET hết cảnh gõ tiếng Việt bị nhảy/mất chữ (không ghi đè ô nhập khi đang gõ, bỏ autocapitalize).
- Nút "Màn hình trình chiếu" trên mobile xuống hàng riêng full-width như nút Mở máy quét / Xuất CSV.

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
