# Ai làm được gì trong hệ thống Company Trip

Tài liệu dành cho người vận hành sự kiện, mô tả quyền hạn của ba nhóm tài khoản: Super Admin, BTC (Hoa tiêu) và Hướng dẫn viên Vietravel.

## Ba nhóm tài khoản

- **Super Admin** — người nắm toàn bộ hệ thống, thường là trưởng ban tổ chức hoặc kỹ thuật phụ trách chính. Tài khoản WordPress quản trị mặc định cũng thuộc nhóm này.
- **BTC / Hoa tiêu** — đội vận hành đêm sự kiện: cầm máy quét check-in, chấm điểm thi đua, xếp hạng trò chơi lớn. Được xem mọi bảng tin nhưng không đụng vào dữ liệu gốc.
- **Hướng dẫn viên Vietravel** — người điểm danh trên xe. Đăng nhập vào chỉ thấy đúng hai màn hình: Check-in và Xe của tôi, và chỉ điểm danh được xe mình phụ trách.

Super Admin tạo tài khoản cho cả ba nhóm: cấp quyền ngay từ bảng nhân sự (chọn loại Super hoặc BTC), hoặc khai sẵn trong file nhân sự khi nhập liệu; riêng hướng dẫn viên được thêm ở bảng HDV trong tab Phân xe.

## Super Admin làm được những gì

- Nhập danh sách nhân sự từ file, thêm từng người, gửi QR cá nhân qua email, tạo lại QR khi cần, và cấp quyền tài khoản mới.
- Đánh dấu một người tự túc đi xe riêng (hoặc bỏ đánh dấu) khi có phát sinh, không cần nhập lại file.
- Xóa sạch toàn bộ nhân sự khi cần làm lại từ đầu — thao tác phải gõ mã xác nhận nên không sợ bấm nhầm.
- Bật hoặc tắt cổng chấm điểm văn nghệ; mở, đóng, mở lại từng vòng vote; thêm, sửa, xóa team.
- Điều phối phân xe: mở hoặc chốt xe thủ công, gán tay những người chưa có xe và danh sách tự túc, chèn người vào xe bất kỳ, xuất danh sách tổng năm xe gửi resort.
- Quản lý tài khoản hướng dẫn viên: thêm mới, đổi xe phụ trách, xóa tài khoản.
- Đặt lại dữ liệu từng mảng (check-in, phân xe, trò chơi lớn, văn nghệ, thi đua) hoặc đặt lại toàn bộ sự kiện — đều phải gõ mã xác nhận.

## BTC làm ngang Super Admin ở mảng vận hành

- Chấm điểm thi đua: thêm lần thi đua, đổi tên, xóa, cộng hoặc trừ điểm từng team.
- Xếp hạng ba trò chơi lớn.
- Cầm máy quét check-in cho mọi team đang thi đấu.

Ngoài ba việc trên, BTC mở được tất cả các tab còn lại nhưng ở chế độ chỉ xem: thấy danh sách nhân sự, bảng phân xe, kết quả văn nghệ… nhưng không có nút sửa, nút gửi QR hay nút đặt lại nào hiện ra.

## Cả ba nhóm cùng làm được

- Xem bảng tổng quan, tải lại dữ liệu mới nhất, theo dõi điểm và màn hình kết quả.
- Mọi thao tác có tính sửa đổi của Super Admin và BTC đều được hệ thống ghi nhật ký lại để đối soát sau sự kiện.

## Nhớ nhanh trong một câu

- BTC là người chạy chương trình: chấm thi đua, xếp hạng game, quét check-in, xem được mọi thứ nhưng không sửa dữ liệu gốc.
- Super Admin là BTC cộng thêm toàn quyền dữ liệu: nhân sự, QR, cổng vote, phân xe, đặt lại và tài khoản.
- Hướng dẫn viên là máy quét trên xe: chỉ check-in và điểm danh đúng xe mình phụ trách.
