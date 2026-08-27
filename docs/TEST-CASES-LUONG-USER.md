# TEST CASE LUỒNG SỬ DỤNG — SUPER ADMIN · BTC · HDV

> Bảng QA tay (human E2E) theo từng vai trò, dùng để rà lỗi trước sự kiện. Mỗi case: **luồng thao tác → kỳ vọng → lỗi cần tìm**.
> Chạy trên 3 thiết bị: máy Super Admin (desktop), 1 máy quét BTC (mobile), 1 máy HDV (mobile). Mở kèm màn chiếu `/ket-qua-tong/` + `/ket-qua-van-nghe/`.
> Chuẩn bị: import CSV nhân sự đủ 6 team + team 7 Hoa tiêu; tạo 5 tài khoản HDV; reset phân xe về trạng thái sạch.

---

## 1. SUPER ADMIN (SA)

| Mã | Luồng thao tác | Kỳ vọng | Lỗi cần tìm |
|---|---|---|---|
| SA-01 | Login `admin` tại `/company-trip-admin/` | Đủ 7 tab: Tổng quan, Check-in, Phân xe, Trò chơi lớn, Văn nghệ, Thi đua, Nhân sự & QR | Thiếu tab Phân xe; nav lệch mobile |
| SA-02 | Check-in → Mở trạm 1 (15 phút) | Chip đếm ngược chạy; 4 scanner quét được ngay | Mở trạm báo lỗi khi trạm khác đang mở phải hiện tên trạm đó |
| SA-03 | Miễn check-in 1 người → bỏ miễn | Người biến khỏi "CÒN THIẾU", mẫu số giảm rồi phục hồi | Miễn rồi mà vẫn tính vào tỷ lệ điểm |
| SA-04 | Đóng & chốt trạm 1 → Mở lại | Điểm chốt vào sổ; mở lại xóa cửa sổ cũ, team chưa đủ quét tiếp | Mở lại mà cửa sổ cũ vẫn khóa |
| SA-05 | Phân xe → Mở Xe 1 → quét 3 người ở Trạm 1 | Manifest Xe 1 hiện 3 NV QR; chip "ĐANG PHÂN · XE 1 · 3" | Quét vào sai xe; đếm thiếu |
| SA-06 | Chốt Xe 1 → mở Xe 2 → quét tiếp | Người mới rơi vào Xe 2; Xe 1 ĐÃ CHỐT | Chốt rồi vẫn nhận người vào Xe 1 |
| SA-07 | Chốt lần lượt đến Xe 5 | Sau xe 5: "hoàn tất", auto-assign tắt, quét chỉ check-in | Vẫn gán xe sau khi hoàn tất |
| SA-08 | Reset phân xe (có dữ liệu) | Hộp xác nhận hiện; sau reset 5 xe CHỜ, manifest + lịch sử lượt xóa; **check-in & điểm không mất** | Reset xóa luôn check-in (bug nặng) |
| SA-09 | Chuyển 1 NV từ Xe 1 → Xe 3; thêm 1 người thủ công; thêm 1 BTC | Manifest cập nhật đúng; người đó KHÔNG còn ở xe cũ | 1 người hiện ở 2 xe |
| SA-10 | Tạo/sửa tài khoản HDV (đổi xe, đổi mật khẩu) | Lưu xong HDV đăng nhập được ngay, nav theo xe mới | HDV vẫn thấy xe cũ |
| SA-11 | Xuất CSV từng xe | File mở Excel tiếng Việt đúng, đủ cột Họ tên/Team/Loại/Nguồn | Lỗi dấu tiếng Việt |
| SA-12 | Tổng kết: tung điểm → lộ 6&5 → nhá top4 → lộ 4 → chốt top2 → twist → top3 → quán quân | Đúng thứ tự cột vươn; badge Khuyến khích/Hạng; twist 3 cột tung số; pháo hoa quán quân | Lộ sớm hạng 1-2 trước twist; badge sai bước |
| SA-13 | Tạo đồng điểm 2 đội trong dữ liệu → chạy luồng tổng kết | Cảnh báo trùng điểm hiện ở bàn công bố; 2 đội lộ cùng lượt | Xé nhóm đồng hạng |
| SA-14 | Văn nghệ: mở màn → tìm kiếm → công bố 6→quân quân | Spotlight khóa đúng đội; tiêu đề 3 tầng; đội đã lộ giữ sáng | Spotlight nhảy sai đội khi bấm nhanh |
| SA-15 | Bật/tắt tone biển sáng | Màn văn nghệ đổi nền trong ~1s, không mất trạng thái | Đổi tone làm reset công bố |

## 2. BTC / HOA TIÊU (BTC)

| Mã | Luồng thao tác | Kỳ vọng | Lỗi cần tìm |
|---|---|---|---|
| BTC-01 | Login tài khoản BTC | Vào dashboard; **không** thấy nút Mở/Đóng trạm, không thấy Reset phân xe, không thấy form tạo HDV | BTC thấy nút super |
| BTC-02 | Mở trang quét | Camera mở NGAY, không màn chọn team; accordion 6 team đóng sẵn | Còn bước chọn team |
| BTC-03 | Quét người team khác team mình phụ trách cũ | Thành công, flash hiện tên + team + xe (nếu Trạm 1) | Báo WRONG_TEAM (bug cũ tái phát) |
| BTC-04 | Quét trùng 1 QR trong 2,5s rồi quét lại | Lần 2 báo "đã check-in lúc …" kèm xe | Tạo check-in thứ 2 |
| BTC-05 | Quét người của team đã khóa cửa sổ | Báo WINDOW_LOCKED, không ghi điểm | Vẫn ghi nhận sau khóa |
| BTC-06 | Phân xe → tự pick mình (team 7) vào Xe 2 | Mình hiện trong manifest Xe 2; sang tab Xe 1 không thấy mình | Mình hiện ở 2 xe; danh sách pick vẫn hiện người đã ở xe khác |
| BTC-07 | Tick điểm danh trên Xe 1 và Xe 4 | Tick được mọi xe (kiểm soát cùng HDV); lượt mới tạo được | BTC bị chặn roll-call |
| BTC-08 | Trên mobile: kiểm tra nút đăng xuất + manifest | Logout luôn hiện; manifest chỉ còn họ tên + tick + chuyển xe + xóa | Mất logout; bảng tràn ngang |

## 3. HDV VIETRAVEL (HDV)

| Mã | Luồng thao tác | Kỳ vọng | Lỗi cần tìm |
|---|---|---|---|
| HDV-01 | Login `hdv.xe2` | Chỉ 2 tab: Check-in + Xe của tôi; không thấy điểm/thi đua/nhân sự | Lộ tab khác hoặc dữ liệu điểm |
| HDV-02 | Xe của tôi | Manifest **chỉ Xe 2**; đếm "a/b có mặt"; lịch sử lượt hiện | Thấy người xe khác |
| HDV-03 | Tick ○→✓ rồi ✓→○ | Đổi trạng thái 2 chiều, đếm cập nhật | Tick không đảo lại được |
| HDV-04 | "Điểm danh lượt mới" | Lượt n+1 trắng; lượt n giữ nguyên trong lịch sử | Lượt cũ bị xóa |
| HDV-05 | Lọc "Chưa có mặt" + tìm tên | Danh sách lọc đúng, Enter để tìm | Lọc sai khi có dấu tiếng Việt |
| HDV-06 | Mở trang quét, quét người đang ở Xe 4 | Check-in thành công; người đó **vẫn ở Xe 4** | HDV kéo người về xe mình |
| HDV-07 | Thử gọi API `mac_vote_rollcall` busId=3 (devtools) | 403 "không có quyền" | HDV tick được xe khác |
| HDV-08 | Truy cập `/wp-admin/` | Bị redirect về dashboard; không thấy menu WP | Lọt wp-admin |

## 4. XUYÊN VAI TRÒ & BIÊN (X)

| Mã | Luồng thao tác | Kỳ vọng | Lỗi cần tìm |
|---|---|---|---|
| X-01 | 4 BTC quét song song đúng lúc SA chốt Xe 1→Xe 2 | Mỗi QR rơi đúng xe theo thời điểm server xử lý; không mất lượt | 2 người cùng QR tạo 2 member; mất check-in |
| X-02 | Trạm 1 mở nhưng SA chưa mở xe nào → quét | Thành công, người vào "CHƯA PHÂN XE"; SA gán tay sau | Quét báo lỗi vì chưa có xe |
| X-03 | Quét ở Trạm 2/3/4 người đã ở Xe 3 | Check-in ok; xe không đổi; không hiện chip phân xe | Xe bị đổi ở trạm sau |
| X-04 | Quét QR người team 7 Hoa tiêu | Báo "Tài khoản BTC không check-in như đội thi" | Hoa tiêu bị tính vào mẫu số team |
| X-05 | Rút mạng màn chiếu 10s rồi cắm lại | Overlay "Tạm mất kết nối" hiện, tự đóng, trạng thái giữ nguyên | Màn trắng/mất trạng thái |
| X-06 | SA đổi mật khẩu HDV giữa chừng sự kiện | Phiên cũ HDV vẫn dùng tới khi hết phiên? (ghi nhận hành vi) — phiên mới dùng mật khẩu mới | Không đăng nhập lại được |
| X-07 | 2 SA cùng bấm chốt xe 1 lúc | Chỉ 1 lần chuyển xảy ra; bên kia báo lỗi nhẹ hoặc trạng thái khớp | 2 xe cùng BOARDING |
| X-08 | Thêm thủ công trùng tên người đã có QR | Vẫn thêm (MANUAL) nhưng không ảnh hưởng voter gốc; manifest hiện 2 dòng khác loại | MANUAL đè lên voter |

---

## CHECKLIST NHANH TRƯỚC GIỜ G

1. `npm run check` xanh (12 tie + 15 bus + invariants).
2. SA-05 → SA-07 chạy thử 1 vòng phân xe rồi **SA-08 reset** sạch.
3. BTC-02/03 trên 4 máy quét thật (camera sau).
4. HDV-01 → HDV-05 trên 5 tài khoản HDV.
5. SA-12 → SA-14 duyệt khô toàn bộ kịch bản công bố với số liệu demo.
6. Kiểm tra mobile: logout, manifest gọn, nút 44px.

> Mẹo tìm lỗi nhanh: luôn soi **ranh giới trạng thái** — lúc chốt xe, lúc quét trùng, lúc khóa cửa sổ, lúc đồng điểm, lúc mất mạng. Đó là nơi bug ẩn.
