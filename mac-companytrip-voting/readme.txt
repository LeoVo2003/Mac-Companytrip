=== MAC Company Trip Voting ===
Contributors: macmarketing
Tags: voting, company trip, scoring, event
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.10.7

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

= 1.10.7 =
- Khối "Thêm vào xe" nổi bật: nền gradient đỏ–cam nhạt, viền trên 2px, heading 15px/800; ô tên người ngoài full-width nằm đúng dưới khối tìm nhân sự.
- Danh sách thêm nhân sự sort theo tên nên hiện đủ 6 team (trước đó slice theo thứ tự team nên toàn #1), tối đa 60 dòng.
- Khoảng cách giữa các khối Phân xe 24px theo Premium scale; nút "Điểm danh lượt mới" chỉ in hoa chữ đầu.

= 1.10.6 =
- Manifest mobile chỉ còn Họ tên + tick điểm danh + chuyển xe + xóa (ẩn cột Team/Loại); tablet giữ đủ cột.
- Form thêm vào xe dựng lại dạng block dọc thay grid; pick item flex ngang checkbox + tên; ô tìm nhân sự thêm thủ công đúng style input 44px.

= 1.10.5 =
- Phân xe: nút "Reset phân xe" (Super Admin, có xác nhận) và "Xuất CSV" từng xe trong manifest.
- Thành viên thêm thủ công cũng chuyển xe được; thêm khối tìm kiếm nhân sự bất kỳ để thêm thủ công (Super Admin); danh sách chọn thêm ẩn người đã ở xe khác (mỗi người một xe).
- Sửa nút xóa khỏi xe (40px rõ ràng), font empty-state nhỏ lại, bỏ margin-right auto của header tools và margin-top của nút điểm danh; đồng bộ font nút trên mobile; bảng bus gọn min-width 520px.

= 1.10.4 =
- Tab xe đang chọn bỏ box-shadow, chỉ còn nền gradient đỏ–cam.

= 1.10.3 =
- Form thêm BTC/Hoa tiêu vào xe: chọn nhiều người cùng lúc (checkbox list) rồi bấm thêm một lần.
- Header manifest thẳng hàng (title trái, tìm kiếm + nút điểm danh phải); mobile xếp dọc.
- Giữ nút đăng xuất trên điện thoại: nav cuộn ngang không đẩy logout ra ngoài.

= 1.10.2 =
- Manifest Phân xe có tab đủ 5 xe ngay trong panel (kèm số người), hết cảnh tưởng thiếu xe; kèm lượt điểm danh + lịch sử ngay tại manifest.
- BTC/Hoa tiêu thêm quyền: vào tab Phân xe, tự pick mình (người team 7) vào xe, tick điểm danh kiểm soát cùng HDV; mở/chốt xe, chuyển xe, người thủ công vẫn chỉ Super Admin.
- Polish UI Phân xe/roll-call theo chuẩn Premium: spacing 16/20/24, radius 10-12, input/select 44px đồng bộ token, tab xe dạng pill gradient.

= 1.10.1 =
- Sửa lỗi HDV không đăng nhập được: tài khoản HDV giờ có email công ty (hdv.xe1 → hdv.xe1@macusaone.com) và login dashboard chấp nhận username thô với tài khoản có quyền quét/điểm danh.

= 1.10.0 =
- Module Phân xe Trạm 1: 5 xe WAITING/BOARDING/CLOSED, action atomic "Chốt xe N → mở xe N+1", server tự gán QR vào xe đang mở (không tin browser), không có xe mở thì check-in vẫn thành công và vào danh sách CHƯA PHÂN XE.
- Scanner mới: mở trang quét là camera mở ngay (bỏ bước chọn team), mọi scanner quét full 6 team (bỏ lỗi WRONG_TEAM), accordion danh sách 6 team mặc định đóng, chip "ĐANG PHÂN · XE n", poll đồng bộ 2,5s.
- Role HDV Vietravel (mac_bus_guide) + meta mac_bus_id: dashboard chỉ còn Check-in + Xe của tôi; điểm danh trên xe nhiều lượt (lượt mới không xóa lịch sử), lọc chưa có mặt, tìm tên; HDV quét QR không bị giới hạn theo xe.
- Super Admin: tab Phân xe (overview 5 xe, manifest, chuyển xe, thêm BTC/Hoa tiêu/người thủ công, gán người chưa phân xe, tạo 5 tài khoản HDV); toàn bộ permission kiểm tra server-side; audit BUS_* đầy đủ.
- Bảng mới: buses, bus_members, bus_rollcalls, bus_rollcall_marks (dbDelta idempotent, seed 5 xe). Engine check-in/tính điểm giữ nguyên.

= 1.9.46 =
- Làm lại tiêu đề màn văn nghệ cùng ngôn ngữ với ket-qua-tong: kicker nhỏ ✦ hai bên, tên đội 34-68px line-height 1.22, dòng điểm 16-26px; khoảng cách 14px giữa các tầng nên "Sao Bắc Cực" không còn dính "Hạng ba" / "127 điểm".

= 1.9.45 =
- ket-qua-tong: giãn kicker/h1 (margin 10px + line-height 1.18) hết dính chữ kiểu "Hạng 5" chạm tiêu đề.
- Thay text: TEASE43 "Hiện top 4 để tiếp tục", TWIST "Chặng nước rút Company Trip · ngôi vương chỉ có một", kicker viết thường đầu dòng.
- Màn văn nghệ: lời dẫn tìm kiếm tiếp = "Nhịp tìm kiếm mới · Ai sẽ là cái tên kế tiếp?".

= 1.9.44 =
- Phân cấp lại tiêu đề màn văn nghệ: lúc tìm kiếm kicker nhỏ / tên vừa / mô tả nhỏ mờ; lúc công bố tên đội giãn line-height 1.16 và nhỏ hơn một nhịp để không dính chữ với kicker (hết vụ "Bắc Cực" chạm "Hạng ba").
- ket-qua-tong: thay kicker "TỔNG ĐIỂM ĐANG CHUYỂN ĐỘNG" bằng "Company Trip · Chặng về đích" chuyên nghiệp hơn.

= 1.9.43 =
- Phát hành lại bản 1.9.42 với zip đầy đủ (bản 1.9.42 thiếu zip do check font).

= 1.9.42 =
- Làm lại tiêu đề màn văn nghệ chuẩn UI từng tầng, tất cả Bricolage Grotesque 600: kicker nhỏ màu vàng → tên đội lớn → dòng điểm; không in hoa toàn bộ.
- Bục tên đội to hơn (13vw/220px, chữ 15-22px) để "Sao Bắc Cực" không rớt chữ.
- ket-qua-tong: badge .mr-rank chuyển sang Bricolage Grotesque 600, chữ chỉ in hoa đầu ("Hạng 6", "Quán quân", "Khuyến khích").
- Cú twist: 3 cột khuyến khích giữ 50%, 3 cột ứng viên chạy 50%→80%.

= 1.9.41 =
- Tiết chế màn văn nghệ: tiêu đề công bố còn 1 hàng viết thường kiểu "Quán quân văn nghệ · Đèn Hiệu · 141 điểm", bỏ khối kicker/description thừa.
- Bỏ nhãn "ĐIỂM TRUNG BÌNH" dưới điểm (giữ nguyên số điểm); tên trên bục để 1 hàng, không in hoa, tràn thì cắt gọn.
- Kết quả tổng giữ nguyên font đã duyệt: h1 Bricolage Grotesque, badge hạng Prata.

= 1.9.40 =
- Đảo vai trò font trên Kết quả tổng theo duyệt trực tiếp: tên đội nổi bật dùng Bricolage Grotesque 500, badge hạng dùng Prata 400.
- Tăng nhẹ cỡ và giảm letter-spacing của badge Prata để danh hiệu vẫn rõ khi trình chiếu xa và không tràn trên màn nhỏ.

= 1.9.39 =
- Thay Newsreader bằng Prata Regular 400 cho tiêu đề và tên đội nổi bật trên cả Kết quả tổng lẫn Kết quả văn nghệ.
- Giữ Bricolage Grotesque cho hạng, điểm số, nhãn nhỏ và chữ trên bục để đọc xa ổn định trên màn LED hội trường.
- Bundle Prata Latin + Vietnamese cùng giấy phép SIL OFL; gỡ toàn bộ asset và dependency Newsreader không còn sử dụng.

= 1.9.38 =
- Chuyển toàn bộ Newsreader display sang đúng Regular 400 Italic trên cả Kết quả tổng và Kết quả văn nghệ theo lựa chọn cuối.
- Tiêu đề hạng, điểm display, nhãn la bàn và brand serif dùng italic thật từ font file, không dùng browser giả nghiêng; lining/tabular figures vẫn giữ đúng baseline.
- Gỡ ba file Newsreader Regular đứng khỏi plugin, chỉ bundle italic Latin, Latin Extended và Vietnamese cần thiết.

= 1.9.37 =
- Thay Fraunces bằng Newsreader cho tiêu đề/hạng/điểm trên cả hai màn kết quả: thanh hơn, bớt retro nhưng vẫn giữ lining/tabular figures đúng baseline.
- Thay toàn bộ Manrope và Plus Jakarta Sans bằng Bricolage Grotesque; nền typography dùng weight 400 theo lựa chọn mới, các cấp nhấn vẫn giữ độ đậm cần thiết cho hội trường.
- Bundle local đầy đủ Newsreader và Bricolage Grotesque cho Latin, Latin Extended, Vietnamese; gỡ sạch asset/dependency font thử nghiệm không còn dùng.

= 1.9.36 =
- Thay Cormorant Garamond bằng Fraunces trên cả Kết quả tổng và Kết quả văn nghệ; bật lining/tabular figures để số hạng và điểm luôn nằm đúng baseline.
- Thay Inter bằng Manrope cho tên đội, nhãn và nội dung trình chiếu để nét chữ mềm, thoáng và bớt cảm giác giao diện hành chính.
- Nhúng đầy đủ font variable Latin, Latin Extended và Vietnamese cùng license vào plugin; màn chiếu không còn phụ thuộc Google Fonts hoặc kết nối mạng.

= 1.9.35 =
- Trả `ar-stage-world` về đúng bản oval theo ảnh đã duyệt: sàn cong, vòng đồng tâm, haze vàng và đường viền sân khấu mảnh.
- Gỡ toàn bộ cánh gà, mái hình học và backdrop Art Deco của bản 1.9.34.
- Giữ nguyên typography cỡ lớn cho màn LED 3,5 × 6,5 m, spotlight, hai line đỏ–cam và logic công bố.

= 1.9.34 =
- Dựng lại `ar-stage-world` theo kiến trúc sân khấu gala: cánh gà xếp lớp, mái sân khấu hình học, backdrop phân mảng đỏ–đồng và sàn phản quang sáu vị trí.
- Tăng rõ rệt cỡ tiêu đề, tên đội, điểm, nhãn hạng và chữ trên bục để đọc tốt trên màn LED hội trường 3,5 × 6,5 m.
- Giữ nền không có oval/radar hoặc đường chân trời; dark mode, tone biển sáng, spotlight và hai line đỏ–cam tiếp tục đồng bộ.

= 1.9.33 =
- Làm lại nền sân khấu công bố thành phông điện ảnh liền mạch, bỏ hoàn toàn oval/radar, vòng lưới và đường chân trời cắt ngang bục.
- Thay sàn cũ bằng sương tầng thấp cùng các vùng phản quang riêng dưới từng vị trí đội, giúp khung hình có chiều sâu nhưng không bị nặng hoặc đục.
- Đồng bộ cách xử lý mới cho cả dark mode và tone biển sáng; giữ nguyên hai line đỏ–cam cùng toàn bộ logic spotlight công bố.

= 1.9.32 =
- Trả hai line viền về đúng kiểu sân khấu gốc: tia đỏ–cam 2px, cao 64%, nghiêng ±10° và tan mềm ở hai đầu; bỏ đầu đèn/quầng tròn của bản 1.9.31.
- Sau khi công bố hạng nhì, giữ nguyên khung hình và không tự bật spotlight tìm kiếm ở ô quán quân; MC bấm nút quán quân để công bố trực tiếp.

= 1.9.31 =
- Sửa công bố đồng điểm theo nhóm hạng thực tế: mọi đội cùng hạng sáng cùng lúc, hạng bị khuyết tự làm mờ/bỏ qua và MC được chuyển thẳng tới hạng kế tiếp tồn tại.
- Đồng top 1 không còn công bố quán quân hai lần: nút hạng 2 được làm mờ và nút quán quân mở đồng thời toàn bộ đội đồng hạng nhất.
- Màn chiếu hỗ trợ nhiều đội current trong cùng một nhịp, dùng tiêu đề “ĐỒNG QUÁN QUÂN” hoặc “ĐỒNG HẠNG …”.
- Giảm vòng tia xoay quán quân xuống opacity 0.34 và dùng screen blend để không làm tối khung hình.
- Thêm lại hai đèn viền đỏ–cam có đầu đèn và quầng rơi rõ ràng, đặt sát hai mép sân khấu.

= 1.9.30 =
- Tối ưu sân khấu cho máy chiếu hội trường: beam đã công bố sáng rõ hơn, luồng sáng kéo xuống tận bục và sàn dark tăng tương phản theo cùng tone kem–vàng.
- Bỏ hai vệt đỏ chéo không có nguồn và loại bỏ film grain để giảm nhiễu thị giác/GPU khi trình chiếu.
- Nâng cỡ chữ tên đội, điểm và tên trên bục; tên dài tự giảm cỡ theo độ dài và chỉ xuống dòng tại ranh giới từ.
- Thêm overlay toàn màn hình khi mất kết nối kéo dài; kết quả hiện tại được giữ nguyên và overlay tự đóng khi đồng bộ trở lại.

= 1.9.29 =
- Các đội đã công bố tiếp tục giữ beam và bụi riêng; đội vừa công bố sáng mạnh hơn, spotlight động chỉ quét đội chưa lộ diện.
- Đổi lời dẫn tìm kiếm thành “ÁNH SÁNG ĐANG GỌI TÊN” và “Tâm điểm tiếp theo thuộc về ai?”.
- Giảm khoảng nghỉ sau mỗi lần công bố từ 5 giây xuống 3 giây; thời gian mở rèm ban đầu vẫn giữ 5 giây.
- Dựng lại beam dạng ánh sáng sân khấu mềm nhiều lớp; thay runway một vệt bằng mặt sàn cong tỏa rộng và haze nhiều điểm tự nhiên hơn.

= 1.9.28 =
- Trả dark mode về đúng sân khấu v1.9.26: nền tối, haze vàng, runway và hai line đỏ; thiết kế xanh đại dương–gỗ–đồng chỉ áp dụng cho tone sáng.
- Thay canvas bụi toàn cục bằng trường bụi riêng cho từng đội; bụi bật đúng đội đang được quét và chuyển theo spotlight, không còn mắc tại La Bàn.
- Khi spotlight đã khóa, chỉ đội hiện tại có bụi; khi tìm kiếm tiếp, bụi đội cũ tự tắt.
- Bỏ text-shadow khỏi cả dòng “SPOTLIGHT ĐANG TÌM KIẾM” và tiêu đề tên đội.

= 1.9.27 =
- Làm mềm spotlight bằng radial beam, loại bỏ hoàn toàn hai vệt trắng ở mép và giữ đúng một luồng sáng khi tìm kiếm.
- Hạt bụi xanh ngọc/đồng đi theo đội đang được spotlight quét hoặc đội vừa khóa, không còn đứng riêng tại La Bàn.
- Dựng lại sân khấu thành không gian biển–boong tàu liền mạch, blend sàn/đường gỗ tự nhiên hơn và nâng palette xanh đại dương, đồng, gỗ.
- Bỏ text-shadow đỏ khỏi tiêu đề công bố; đổi nhãn công tắc thành tone biển sáng/biển đêm.

= 1.9.26 =
- Giữ lại 2 line đỏ hai bên sân khấu (.ar-edge) và runway viền đỏ theo ý thích — chỉ bỏ line trắng cắt cứng ở mép vệt sáng (đã blur mềm từ 1.9.25).

= 1.9.25 =
- Dọn sạch line thừa trên sân khấu: bỏ runway (2 viền đỏ chéo hai bên) và lớp sọc đỏ nền; nền chỉ còn quầng sáng đỉnh.
- Hạt bụi chỉ bay trong luồng sáng đã khóa (is-current), không còn lơ lửng giữa trời khi chưa có đèn.
- Tăng blur mép cone (beam/spot 12px, lõi 9px) để mép vệt sáng tan mềm, hết line trắng cắt cứng hai bên vệt sáng.

= 1.9.24 =
- Ánh sáng thật hơn theo bản lighting-fix: khi tìm kiếm chỉ còn 1 luồng sáng trượt duy nhất (.ar-beam/.ar-floor-ring chỉ bật khi đã khóa); .ar-spot cao 92vh khớp luồng khóa; sương mù nền giảm còn 0.4; tia sáng quán quân blur 26px + mask tỏa tròn; bỏ 2 line đỏ hai bên sân khấu; ẩn thanh footer.
- Tiêu đề lúc tìm kiếm nhỏ lại; đổi câu mô tả "Ánh sáng đang lướt qua từng mũi thuyền".
- Thêm công tắc tone trong admin văn nghệ: tone tối mặc định hoặc tone đại dương xanh kiểu đại hải trình.

= 1.9.23 =
- Spotlight tìm kiếm chiếu đồng thời ngẫu nhiên 1-4 đội thay vì chỉ 1, kịch tính hơn.
- Bục công bố thành card hiện đại: bỏ số La Mã, tên đội khắc vàng ngay trên card; bục quán quân cao hơn.
- Nhịp công bố: sau mỗi lần công bố giữ khóa ~5 giây cho MC xướng tên rồi spotlight tiếp tục tìm kiếm các đội chưa lộ; bấm công bố thì khóa chặt đội đó rồi mới random tiếp.
- Sửa font lỗi dấu tiếng Việt: dùng Cormorant Garamond (bộ font của màn công bố tổng kết) cho tiêu đề/điểm; tăng line-height tiêu đề "QUÁN QUÂN VĂN NGHỆ" hết dính chữ.

= 1.9.22 =
- Nâng cấp "wow" màn The Spotlight theo 7 nhóm: chùm sáng nhiều lớp mix-blend screen + hạt bụi lơ lửng trong luồng sáng; sương mù đáy dày hơn trôi chậm; vòng sáng + ripple dưới chân bục; số La Mã khắc trên bục; rèm nhung thêm nếp gấp, tua rua vàng, rung đàn hồi và bóng đổ khi kéo.
- Spotlight trượt ngang qua các bục và có pha hãm dần kiểu vòng quay số (380→620→900ms) trước khi khóa đúng đội.
- Điểm đếm chạy 0→điểm thật ~0.8s; tiêu đề wipe từng khối; số/hạng dùng font display kiểu bảng tỷ số.
- Khoảnh khắc quán quân: pháo hoa thêm tia lửa lấp lánh + dải ruy băng xoay rơi, tia sáng xoay chậm quét nền, rung màn hình 2-3px, bục quán quân cao hơn; phủ film grain + vignette toàn màn. Mọi hiệu ứng tôn trọng prefers-reduced-motion.

= 1.9.21 =
- Thay màn đua thuyền văn nghệ bằng concept "One Direction — The Spotlight" dành cho trình chiếu hội trường.
- Màn chờ dùng rèm đỏ; bấm mở màn kéo rèm, sau 5 giây spotlight nhảy ngẫu nhiên giữa tên thật của 6 đội.
- Công bố tuần tự từng đội theo hạng 6 → 5 → 4 → 3 → 2 → quán quân; mỗi tín hiệu chỉ mở đúng một đội, kể cả khi đồng điểm.
- Spotlight khóa đúng đội vừa công bố, hiện tên/hạng/điểm trên bục; quán quân có hiệu ứng pháo hoa. Team Hoa tiêu không xuất hiện.

= 1.9.20 =
- Thi đua không còn bắt buộc đủ 6/6 đội: hạng mục có ít nhất một đội được chấm sẽ được tính.
- Team chưa chọn trong hạng mục đang tính được xem là không tham gia và nhận 0đ; hạng mục hoàn toàn trống vẫn chưa tính.
- Giao diện hiển thị rõ số team tham gia/không tham gia và trạng thái "Không tham gia · 0đ".

= 1.9.19 =
- Tinh chỉnh spacing/padding trang Thi đua, chống tràn chữ và tối ưu bố cục chấm điểm trên mobile.
- Hạng mục Thi đua mới xuất hiện ngay sau khi thêm, không cần tải lại trang; các thao tác chấm điểm dùng payload gọn hơn.
- Căn trái nhóm nút Nhân sự/QR cho đồng bộ và chuyển danh sách nhân sự thành thẻ dễ đọc trên mobile.
- Giảm độ trễ khi bật/tắt cổng văn nghệ và mở/đóng trạm check-in; mở lại trạm sẽ reset cửa sổ 15 phút của team nhưng giữ nguyên lượt check-in đã ghi nhận.
- Đồng bộ typography cho nút quay lại/chọn team và tiêu đề Company Trip Check-in.

= 1.9.18 =
- Sửa lỗi ẩn điểm rồi công bố quán quân, hiện điểm lại vẫn thấy •••: số thật luôn ghi vào cột điểm, ẩn/hiện thuần CSS display:none.

= 1.9.17 =
- Sửa lỗi màn /ket-qua-tong/ văng "Cannot read properties of null (reading 'scoresHidden')" ở lần tải đầu (state còn null khi so sánh scoresChanged).

= 1.9.16 =
- Ẩn điểm trên màn chiếu không còn làm tắt pháo hoa đang bắn (chỉ toggle CSS display:none).
- Đua thuyền văn nghệ đơn giản hóa theo kiểu đua vịt: bỏ cú lừa DECOY, không đồng khuyến khích — mỗi thuyền nhận badge đúng hạng (QUÁN QUÂN / HẠNG NHÌ / HẠNG BA / HẠNG 4-5-6), thuyền về bến theo thứ hạng; khi đua các thuyền liên tục vượt lên/tuột lại giữ hồi hộp.
- Trò chơi lớn: Hạng 6 = 0đ giờ giữ record riêng, bảng không còn nhầm thành "Chưa xếp"; chọn "Chưa xếp" mới xóa.
- Thi đua: cho phép trùng hạng — hạng mục đủ 6 team chấm là hoàn tất và tính vào điểm chính thức (bỏ chặn "Chưa thể hoàn tất").
- Intro bàn công bố tổng kết rút gọn; sửa padding/spacing tab Thi đua và nút QR cá nhân trên mobile/tablet.

= 1.9.15 =
- Làm lại logic Thi đua: Điểm Thi đua chính thức = ROUND(trung bình các hạng mục HOÀN TẤT), luôn 0-50; tổng toàn hệ thống tối đa 1.000đ (600 + 150 + 200 + 50). Hạng mục hoàn tất = đủ 6 team có record (kể cả 0đ) và thang 50..0 không trùng; hạng mục dở chỉ hiện điểm thô.
- Phân biệt 3 trạng thái: chưa chấm (không record, hiện —) / Hạng 6 = 0đ (record 0, hiện 0đ) / xóa (operation clear riêng, bấm lại ô đang chọn).
- Backfill legacy idempotent: hạng mục cũ có đúng 5 record {50,40,30,20,10} và thiếu 1 team tự insert row 0 cho team đó.
- UI tab Thi đua + Tổng quan: khối "THI ĐUA · 5% · Tối đa 50 điểm" + công thức, badge trạng thái hạng mục (x/6, ✓ hoàn tất, trùng hạng), cột "Điểm Thi đua x/50", lịch sử phân biệt "Hạng 6 · 0đ" với "Xóa điểm".
- Demo thi đua đủ 6 team kèm row 0 explicit.

= 1.9.14 =
- Migration hội tụ: site đã lỡ lên 1.9.12-1.9.13 (option mac_voting_total_page_id, shortcode *_total_results/*_art_results) tự chuẩn hóa về bộ tên mới, không tạo trang trùng; site ≤ 1.9.11 và site mới vẫn tách trang đúng.
- Thêm alias shortcode mac_companytrip_total_results / mac_companytrip_art_results để trang cũ nhúng vẫn chạy.

= 1.9.12 =
- Tách hai màn trình chiếu: /ket-qua-tong/ = màn cột kết quả tổng kết; /ket-qua-van-nghe/ = màn ĐUA THUYỀN văn nghệ mới (6 làn thuyền SVG, cú lừa 3 thuyền thấp điểm bứt lên, thuyền về bến theo hạng 3 → 2 → quán quân + pháo hoa).
- Bàn điều khiển văn nghệ đổi ngôn ngữ đua thuyền và link đúng màn /ket-qua-van-nghe/; bàn tổng kết link /ket-qua-tong/.
- Ẩn điểm: đáy màn tổng kết hạ padding còn 50px khi ẩn.

= 1.9.11 =
- Badge rút gọn còn chữ "KHUYẾN KHÍCH" (bỏ chữ HẠNG).
- Ẩn điểm: hàng tên đội giãn xuống thế chỗ khối điểm, hết khoảng trống thừa dưới đáy; hiện điểm thì đôn lên như cũ.
- Thang độ cao mới: bước 02 hạng 4-5-6 cùng 80%; twist hạng 1-2-3 dao động 45-60% còn 4-5-6 giữ 50%; hiện top 3 thì 4-5-6 về 30%, hạng 3 = 50%, hạng 1-2 dao động 50-90%; quán quân 85% · nhì 65%.

= 1.9.10 =
- Bước 02 "Hiện top 4" chỉ lộ hạng 4-5-6 (cùng mốc 50%); hạng 3 không lộ ở bước này mà dành cho bước "Hiện top 3" sau cú twist.
- Badge HẠNG KHUYẾN KHÍCH: gắn ngay khi lộ hạng 5-6 ở bước 01 và hạng 4 ở bước 02 (thay nhãn HẠNG 4/5/6 cũ).
- Bộ test 12 TC cập nhật theo ngưỡng mới của bước 02 (lộ 3 đội cuối, bảo vệ hạng 1-3).

= 1.9.9 =
- Kịch bản twist đúng ý MC: bước 03 "Tạo cú twist" cho 3 đội dẫn đầu (hạng 1-2-3) cùng leo lên và TUNG ĐIỂM liên tục (số chạy ngẫu nhiên, dao động 70-90%); bước 04 "Hiện top 3" lộ diện hạng ba về 50%, hai đội còn lại tiếp tục tung điểm; bước 05 công bố quán quân (nhất 85%, nhì 60%).
- Badge hạng ba chuyển sang gắn ở bước "Hiện top 3"; bước 02 chỉ gắn badge 4-5-6.
- Bàn điều khiển thành 6 nút 00-05; bộ test 12 TC mở rộng kiểm tra bước TWIST (giấu hạng 1-3) và REVEAL3.

= 1.9.8 =
- Thang độ cao mới theo kịch bản MC: lộ 6-5 = 80% → lộ top 4: hạng 4-5-6 cùng 50% + hạng 3 lên 80% → twist: hạng 3 về 50%, hạng 4-5-6 về 30%, top 2 dao động 70-90% → quán quân 85%, hạng nhì 60%.
- Bước 02 thành 2 nhịp (stage TEASE43 mới): nhấn lần 1 các cột chưa lộ nhấp nháy sáng tối nhá hàng, nhấn lần 2 mới lộ top 4.
- Mở màn tung điểm kéo mượt từ vạch xuất phát 122px lên cao trong 1,4s đầu rồi mới lượn sóng — hết cảnh giật bật cao ngay khung đầu.
- Cú twist hết giật: top 2 leo mượt 900ms lên 80% rồi mới chuyển sang dao động nhanh; hạng 3-6 hạ độ cao mượt thay vì nhảy.

= 1.9.7 =
- Điểm tăng trưởng: nhóm mới lộ nhận chữ số điểm phóng to (tối đa 52px), nhóm lộ trước tự thu nhỏ khi nhóm sau xuất hiện; tới FINAL chỉ quán quân giữ chữ to, hạng 2-6 về kích thước thường.
- Pháo hoa FINAL chờ thêm 3 giây sau khi cột quán quân lên đỉnh rồi mới bắn.
- Nút ẩn điểm nay giấu hẳn khối số (display:none) thay vì che bằng •••.

= 1.9.6 =
- Quy tắc bảo vệ top đầu khi trùng điểm: đội hạng 1-2 không bao giờ lộ trước bước twist kể cả khi cụm trùng điểm chạm ngưỡng lộ (sửa TC03, 04, 07, 09, 12 bị hỏng cú twist ở bản 1.9.5).
- Tiêu đề bước lộ hạng sinh động theo hạng thực lộ: "HẠNG 5 ×2", "HẠNG 4 ×3" thay vì cứng "HẠNG 6 & HẠNG 5"; mô tả twist tự ghi số cột khi có 3-4 đội dẫn đầu trùng điểm.
- Bàn điều khiển công bố hiện hộp cảnh báo trùng điểm sau khi mở màn: số cột twist dự kiến và bước 02 có thể không lộ thêm đội.
- Thêm bộ test 12 trường hợp trùng điểm (TC01-TC12) vào check-plugin.mjs: kiểm rank thể thức thi đấu, số đội lộ từng bước và quy tắc không lộ sớm hạng 1-2.

= 1.9.5 =
- Giải pháp trùng điểm: thang lộ hạng chuyển sang đếm số đội từ dưới lên (bước 01 lộ 2 đội cuối, bước 02 lộ 4 đội cuối, FINAL lộ hết) thay vì ngưỡng hạng cứng — trùng điểm không còn làm lép bước lộ hạng, nhóm trùng điểm luôn lộ cùng nhau và nhận đồng hạng.
- Cột chưa lộ hạng trả về vạch xuất phát 112px (min-height) trong các bước lộ hạng để nhóm được lộ nổi bật hẳn.
- Đồng quán quân: FINAL xướng đủ tên mọi đội hạng 1, cả hai cột cùng lên đỉnh 82% với badge QUÁN QUÂN.
- Dòng "Xin chúc mừng" chỉ xướng nhóm mới lộ ở nhịp hiện tại (không xướng lại đội đã lộ); bảng điểm thật trong admin gắn nhãn "đồng hạng" cho các đội trùng điểm.

= 1.9.4 =
- Gộp bước "Top 2 bước lên" và "Tạo cú twist" thành một nút "Tạo cú twist" duy nhất: top 2 leo mượt lên 6 ô rồi dao động bám đuổi ngay trong cùng một nhịp (kịch bản còn 5 nút 00-04).
- Khi lộ diện hạng 4 & 3: gắn đủ badge hạng 3-4-5-6 cùng lúc; badge hạng 1-2 vẫn giữ đến bước công bố quán quân.
- RANK12 giữ làm trạng thái legacy trong backend: dashboard cũ kẹt ở step này vẫn tiến lên TWIST bình thường.

= 1.9.3 =
- Bàn điều khiển công bố thêm nút Ẩn/Hiện điểm trên màn chiếu: admin che số bằng ••• bất cứ lúc nào, màn trình chiếu tự đồng bộ trong ~1 giây.
- Mở màn tung điểm dâng cao hơn (từ ~11-28% lên ~19-41% chiều cao cột) cho có đà trước khi lộ hạng.
- Badge hạng xuất hiện trễ một nhịp: hạng lộ ở bước này thì bước kế tiếp mới gắn badge, riêng bước công bố quán quân gắn đủ badge.
- Bước 03 top 2 bước lên: hạng 3-6 giữ nguyên badge, vị trí và màu sắc, không nhấp nháy lại (render theo diff trạng thái từng đội).
- Quán quân giảm từ 10 ô (100%) xuống ~82% cho vừa khung màn chiếu.
- Đổi text mở màn: "Điểm từ bốn mặt trận đang dồn về một mối" → "6 đội · 4 chặng đường · 1 ngôi vương duy nhất".

= 1.9.2 =
- Sửa lỗi lệch layout màn công bố: khối mr-chart-lines còn sót trong markup results.js mất position:absolute sau bản 1.9.1 nên chiếm ô grid, đẩy header giãn nửa màn hình.
- Bỏ tag #số đội phía trên tên đội trên màn trình chiếu; tên đội căn giữa ô cho cân.
- Đổi nhãn khối nút điều khiển: "KỊCH BẢN MC" → "TÍN HIỆU TỔNG KẾT".

= 1.9.1 =
- Sửa lỗi "Cần đủ 6 đội" khi mở màn công bố tổng kết (snapshot đọc đúng mảng teams của bảng tổng điểm).
- Bàn điều khiển công bố chuyển thành tab "Công bố" trong Tổng quan, đứng giữa Tổng điểm và Lịch sử; đổi nhãn LIVE REVEAL / Tín hiệu MC; bỏ tiền tố #số đội ở bảng điểm thật.
- Màn tổng kết gọn hơn: bỏ vạch ngang chia 10 ô trên cột và lớp mr-chart-lines — thang ô chỉ còn trong logic.

= 1.9.0 =
- Màn hình trình chiếu chuyển thành màn công bố ĐIỂM TỔNG Company Trip (giữ layout hải trình): endpoint mới /results-total, thang 10 ô với vạch chia, tổng điểm thật lấy từ sổ điểm.
- Kịch bản 6 step do MC bấm trên admin: tung điểm lượn nhẹ → hạng 6-5 lên 3 ô → hạng 4-3 (4-5-6 cùng 4 ô, hạng 3 lên 5 ô) → top 2 cùng 6 ô → twist bám đuổi → quán quân nhảy lên 10 ô kèm pháo hoa tưng bừng.
- Text đổi theo màn tổng kết; pháo hoa mạnh và dày hơn; logic công bố văn nghệ cũ giữ nguyên trong code để tái sử dụng sau.

= 1.8.20 =
- Nâng khoảng trống dưới màn hình để tên đội + điểm không sát mặt đất khi chiếu sân khấu lớn.
- Điểm số hạng 3 chuyển sang màu copper (#f0bd91) để tách khỏi màu hạng 4-5-6.

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
