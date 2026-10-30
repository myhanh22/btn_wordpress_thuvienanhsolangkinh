# Bổ sung WPScan có API token — 27/10/2026

Lần chạy này do **người dùng thực hiện trong terminal bằng Docker**, sau đó cung cấp câu lệnh và kết quả cho agent. Agent đã đối chiếu với file `wpscan-retry.txt` có trên máy, không tự nhận là đã thực thi container hoặc đọc được token. Bản gốc được sao lưu riêng ngoài Git; bản repo đã che hostname cá nhân. Không lưu token, cookie hay thông tin tài khoản API.

Bằng chứng: [log đã che thông tin riêng](wpscan-retry.txt), [summary có nguồn gốc và hash log](evidence/wpscan-api-user-run.json), [kiểm kê thực tế](evidence/installed-inventory.json).

## Kết quả được ghi nhận

| Trường | Theo kết quả người dùng cung cấp |
|---|---|
| Công cụ | WPScan 4.1.0, image `wpscanteam/wpscan` |
| Phạm vi | `http://baitapnhom1.local/`, network host, ánh xạ domain về loopback |
| Bắt đầu / kết thúc | 27/10/2026 12:18:16 → 12:18:24, theo log; log không ghi múi giờ |
| Thời gian elapsed | 7 giây, theo tool |
| Detection | `passive`; plugin detection `passive`; enumerate `vp,vt` |
| API | **WPScan DB API OK**, plan **free** |
| API request / còn lại | **3 / 19**, tại thời điểm chạy |
| HTTP request / cache | **15 / 9** |
| Response codes | 200: 13; 404: 2 |
| Phiên bản core nhận diện | Không nhận diện được trong lần passive này |
| Theme nhận diện | Astra 4.14.1, confidence 80% |
| Plugin nhận diện | Không có trong kết quả passive; không có nghĩa website không cài plugin |
| CVE/cảnh báo lỗ hổng được in | Không có mục CVE trong log được cung cấp |
| Kết thúc | Có dòng Finished, không có Scan Aborted; không ghi exit code vì pipeline `tee` chưa chứng minh mã thoát riêng của WPScan |

Câu lệnh được người dùng cung cấp, không có giá trị token trong command line:

```sh
sudo --preserve-env=WPSCAN_API_TOKEN docker run --rm \
  --network host \
  --add-host=baitapnhom1.local:127.0.0.1 \
  -e WPSCAN_API_TOKEN \
  wpscanteam/wpscan \
  --url http://baitapnhom1.local \
  --random-user-agent \
  --enumerate vp,vt \
  --detection-mode passive \
  --plugins-detection passive \
  2>&1 | tee docs/security/wpscan-retry.txt
```

Nếu chạy lại, bật `set -o pipefail` để lấy lỗi của scanner, lưu raw vào thư mục riêng ngoài Git rồi lọc hostname/token trước khi đưa báo cáo vào repo. Không chép giá trị token vào command, ảnh chụp hoặc Markdown.

## Phạm vi được giải quyết và phần còn lại

**Đã có bằng chứng API xác thực thành công.** Nhận định “thiếu token” của các lần agent chạy WPScan 3.8.28 trước đây là lịch sử của các lần đó, không còn là kết luận chung cho lần bổ sung này. Không cần cấp lại token chỉ để chứng minh API OK.

Tuy nhiên, không thể kết luận “không có lỗ hổng”:

- Core không được nhận diện trong lần passive này nên log không chứng minh core đã được đối chiếu CVE theo phiên bản. Kiểm kê filesystem trước đó ghi core 7.1.3; không tự gán kết quả API cho phiên bản đó.
- Website thực tế có 6 plugin và 5 theme theo inventory. Passive enumeration không nhận diện plugin, nên chưa chứng minh các plugin đã được đối chiếu CVE.
- Log có 9 cached requests; không gọi đây là một lần quét toàn bộ không cache.
- Có cảnh báo metadata cũ và prompt cập nhật, nhưng không có thông báo xác nhận cập nhật hoàn tất. Không khẳng định definitions mới nhất.
- Metadata nói Astra latest 4.14.0 trong khi nhận diện bản 4.14.1. Không dùng dòng “up to date” này để quyết định nâng/hạ phiên bản.
- Header `Server: nginx/1.26.1` còn được nhận diện. Đây là lộ thông tin cấu hình đã ghi trong bảng đánh giá, không tự gán CVE Nginx.

Bước bổ sung để tăng độ phủ: trong terminal đang có token, đối chiếu danh sách plugin/theme cài thực tế với chế độ nhận diện mixed/known locations hoặc truy vấn API cho từng slug và phiên bản, ghi cả component API không có dữ liệu. Chỉ quét LocalWP của nhóm, không dùng danh sách mật khẩu, không brute-force tài khoản thật. Lưu JSON ngoài Git và kiểm tra quota trước; không cập nhật core/vendor chỉ dựa vào cảnh báo phiên bản.

Không đổi thiết kế, banner, ảnh đại diện hoặc dữ liệu tác phẩm trong lần cập nhật báo cáo này. Converter for Media giữ tắt trong đợt đối chiếu log này; đợt sửa WebP tiếp theo đã bật theo rewrite Nginx và kiểm chứng, xem README hiện tại.