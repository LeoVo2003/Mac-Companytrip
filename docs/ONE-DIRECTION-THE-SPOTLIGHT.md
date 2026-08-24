# ONE DIRECTION — THE SPOTLIGHT

Tài liệu concept và vận hành màn hình công bố kết quả văn nghệ Company Trip.

## 1. Mục tiêu

Thay màn đua thuyền bằng một sân khấu công bố tối giản, điện ảnh và phù hợp khi trình chiếu trong hội trường hơn 250 người.

Thông điệp trung tâm:

> Chỉ một hướng. Chỉ một khoảnh khắc. Spotlight sẽ gọi tên từng đội.

Mỗi lần MC bấm nút, hệ thống chỉ công bố đúng một đội. Không công bố theo nhóm Top 3 hoặc Top 2.

## 2. Phong cách hình ảnh

- Tỷ lệ chính: màn hình ngang 16:9.
- Nền sân khấu: đen và than đậm.
- Màu nhận diện: đỏ `#E31E24`, cam `#FF6A2C`.
- Spotlight chính: trắng vàng, tương phản cao.
- Màn chờ: rèm nhung đỏ đóng kín.
- Sáu bục tương ứng sáu đội thi văn nghệ.
- Typography: Inter, chữ lớn, nét đậm, đọc rõ từ cuối hội trường.
- Không sử dụng thuyền, biển, cúp, micro hoặc các chi tiết trang trí rối mắt.

## 3. Sáu đội xuất hiện trên màn hình

1. La Bàn
2. Hải Đỏ
3. Đèn Hiệu
4. Viking
5. Sao Bắc Cực
6. Hải Đăng

Tên đội được lấy trực tiếp từ dữ liệu WordPress. Nếu admin đổi tên đội, màn hình tự sử dụng tên mới.

Team `Hoa tiêu` là team BTC, không tham gia thi văn nghệ và bị loại khỏi dữ liệu công bố.

## 4. Luồng công bố

```text
IDLE
  ↓ Mở màn
ROLLING
  ↓ Công bố hạng 6
SIXTH
  ↓ Công bố hạng 5
FIFTH
  ↓ Công bố hạng 4
FOURTH
  ↓ Công bố hạng 3
THIRD
  ↓ Công bố hạng 2
SECOND
  ↓ Công bố quán quân
FINAL
```

Mỗi chuyển trạng thái chỉ được thực hiện đúng thứ tự. Nếu dashboard gửi sai bước, backend từ chối tín hiệu để tránh công bố nhầm.

## 5. Kịch bản trình chiếu

### 5.1. Trạng thái chờ — IDLE

- Rèm nhung đỏ đóng kín toàn bộ màn hình.
- Hiển thị tiêu đề:
  - `ONE DIRECTION`
  - `THE SPOTLIGHT`
- Không hiển thị tên đội, điểm hoặc thứ hạng.

### 5.2. Mở màn — ROLLING

Khi MC bấm `Mở màn · The Spotlight`:

1. Rèm kéo sang hai bên trong khoảng 2,8 giây.
2. Sân khấu sáu bục xuất hiện.
3. Tên thật của sáu đội hiển thị trên từng bục.
4. Sau đúng 5 giây tính từ tín hiệu mở màn, spotlight bắt đầu nhảy ngẫu nhiên giữa sáu đội.
5. Spotlight đổi đội khoảng mỗi 620 ms.
6. Không hiển thị điểm giả trong lúc tìm kiếm.

Nếu màn hình trình chiếu được tải lại giữa chừng, thời gian được đồng bộ theo timestamp từ server; hiệu ứng không tự chạy lại từ đầu sai nhịp.

### 5.3. Công bố từng đội

MC bấm lần lượt:

1. `Công bố hạng 6`
2. `Công bố hạng 5`
3. `Công bố hạng 4`
4. `Công bố hạng 3`
5. `Công bố hạng 2`
6. `Công bố quán quân`

Ở mỗi lần bấm:

- Spotlight ngừng nhảy.
- Spotlight khóa chính xác vào bục của đội vừa được công bố.
- Tên đội, thứ hạng và điểm trung bình xuất hiện trên bục.
- Tên đội và kết quả đồng thời được phóng lớn ở vùng tiêu đề.
- Các đội đã công bố trước đó vẫn giữ kết quả nhưng giảm độ sáng.
- Các đội chưa công bố tiếp tục nằm trong vùng tối.

### 5.4. Quán quân — FINAL

- Spotlight khóa vào quán quân.
- Bục quán quân chuyển sang ánh vàng mạnh.
- Tiêu đề đổi thành `QUÁN QUÂN VĂN NGHỆ`.
- Hiệu ứng pháo hoa đỏ, cam, vàng và trắng được kích hoạt.
- Người dùng bật chế độ giảm chuyển động sẽ không thấy hiệu ứng pháo hoa hoặc spotlight nhảy nhanh.

## 6. Quy tắc xếp hạng và bảo mật kết quả

- Kết quả được sắp theo điểm trung bình giảm dần.
- Trước khi công bố, API không gửi điểm và thứ hạng thật của các đội chưa lộ diện.
- Mỗi trạng thái chỉ mở thêm đúng một đội.
- Khi đồng điểm, hệ thống vẫn công bố từng đội riêng theo thứ tự ổn định của bảng kết quả; một lần bấm không làm lộ đồng thời hai đội.
- Chỉ được bắt đầu công bố khi:
  - Cả ba lượt vote đã đóng.
  - Lịch có đủ sáu tiết mục.
  - Mỗi tiết mục có ít nhất một phiếu hợp lệ.

## 7. Bàn điều khiển MC

Vị trí:

```text
Company Trip Admin → Văn nghệ → Công bố văn nghệ
```

Màn hình trình chiếu:

```text
/ket-qua-van-nghe/
```

Bàn điều khiển gồm:

- Trạng thái realtime hiện tại.
- Nút mở màn.
- Sáu nút công bố riêng biệt.
- Bảng điểm thật chỉ admin nhìn thấy.
- Nút đặt lại về màn rèm đỏ.

Chỉ nút hợp lệ tiếp theo được bật. Các nút còn lại bị vô hiệu hóa để hạn chế thao tác nhầm khi chương trình đang diễn ra.

## 8. Kịch bản gợi ý cho MC

### Mở màn

> Sáu đội đã hoàn thành phần trình diễn của mình. Nhưng chỉ một hướng dẫn tới khoảnh khắc cuối cùng. Hãy cùng bước vào — The Spotlight.

Sau lời dẫn, kỹ thuật viên bấm `Mở màn · The Spotlight`.

### Trước mỗi lần công bố

> Spotlight sẽ dừng lại ở cái tên tiếp theo…

Kỹ thuật viên bấm đúng một nút công bố và chờ hiệu ứng hoàn tất trước khi MC đọc tiếp.

### Quán quân

> Và spotlight cuối cùng của đêm nay thuộc về…

Kỹ thuật viên bấm `Công bố quán quân`.

## 9. Runbook kỹ thuật tại hội trường

Trước chương trình:

- Mở `/ket-qua-van-nghe/` trên máy trình chiếu.
- Bật full screen và kiểm tra đúng tỷ lệ 16:9.
- Kiểm tra màn hình đang ở trạng thái rèm đỏ.
- Kiểm tra kết nối realtime hiển thị `Đang đồng bộ`.
- Đóng đủ ba lượt vote.
- Đối chiếu bảng điểm thật trên dashboard.
- Chạy thử toàn bộ flow bằng dữ liệu demo nếu cần, sau đó bấm `Đặt lại`.

Trong chương trình:

- Mỗi tín hiệu chỉ bấm một lần.
- Chờ màn hình đổi trạng thái trước khi bấm bước kế tiếp.
- Không tải lại dashboard khi đang công bố nếu không cần thiết.
- Nếu màn trình chiếu mất kết nối, giữ nguyên dashboard và chờ màn hình tự kết nối lại.

Sau chương trình:

- Có thể giữ màn FINAL trên LED để chụp ảnh.
- Bấm `Đặt lại` khi cần chuẩn bị cho lần diễn tập tiếp theo.

## 10. Tiêu chí nghiệm thu

- [ ] Màn IDLE hiển thị rèm đỏ đóng kín.
- [ ] Bấm mở màn làm rèm kéo sang hai bên.
- [ ] Spotlight chỉ bắt đầu nhảy sau mốc 5 giây.
- [ ] Sáu bục hiển thị tên đội thật, không hiển thị số 01–06 thay tên.
- [ ] Không có team Hoa tiêu trên màn hình.
- [ ] Mỗi nút chỉ làm lộ thêm đúng một đội.
- [ ] Spotlight khóa đúng đội vừa được công bố.
- [ ] Tên, hạng và điểm trên bục khớp bảng điểm admin.
- [ ] Các đội chưa công bố không bị lộ điểm qua API.
- [ ] Quán quân có hiệu ứng ánh sáng và pháo hoa.
- [ ] Chế độ `prefers-reduced-motion` hoạt động.
- [ ] Nội dung đọc rõ ở độ phân giải 1920×1080.

## 11. Phiên bản triển khai

Concept này được triển khai lần đầu trong plugin `MAC Company Trip Voting v1.9.21`.
