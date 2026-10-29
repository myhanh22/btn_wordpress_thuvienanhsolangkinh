# Quét lỗ hổng và đánh giá có căn cứ

## Bổ sung mới có API token

Người dùng cung cấp lần Docker WPScan 4.1.0: API OK, plan Free, dùng 3 request, còn 19. Đã đối chiếu với `wpscan-retry.txt` trên máy. Xem [báo cáo riêng](WPSCAN-API-USER-RUN.md); log đã che hostname. Các kết quả 3.8.28 bên dưới được giữ nguyên theo lịch sử, không tự nhận agent đã chạy lần Docker hoặc đánh đồng API thành công với quét CVE đầy đủ.

## Phạm vi và công cụ

Chỉ quét `http://baitapnhom1.local` của nhóm trên máy Ubuntu. Không quét GitHub hoặc website ngoài nhóm, không chạy password attack/enumeration tài khoản thật. Công cụ tải definitions từ nguồn của chính WPScan là hoạt động cập nhật dữ liệu, không phải quét các nguồn này.

Đã chạy **WPScan 3.8.28**, Ruby 3.2.3. Ruby được giải nén từ gói Ubuntu chính thức có kiểm tra digest APT vào thư mục tools riêng, WPScan cài qua RubyGems; không thay Ruby hệ thống. Ở thời điểm agent chuẩn bị các lần 3.8.28 trước đây, Docker daemon và Ruby/WPScan hệ thống không có sẵn, nhưng đã khắc phục để chạy được WPScan thực tế, không coi thiếu công cụ là lý do chỉ viết kế hoạch. Nmap/ZAP không được chạy và không có kết quả của hai công cụ này.

Câu lệnh đã loại thông tin riêng:

```sh
wpscan --update
wpscan --url http://baitapnhom1.local --enumerate vp,vt \
  --plugins-detection passive --max-threads 2 --throttle 100 \
  --no-update --no-banner --format json --output <PRIVATE_RAW_BEFORE>
wpscan --url http://baitapnhom1.local --enumerate vp,vt \
  --plugins-detection passive --max-threads 2 --throttle 100 \
  --no-update --clear-cache --cache-ttl 0 --random-user-agent --no-banner \
  --format json --output <PRIVATE_RAW_AFTER>
```

Lần thử sau khi WAF enabled/basic dùng user-agent mặc định bị trả 403, exit 4, lưu riêng `evidence/wpscan-waf-blocked.json`: đây không phải một lần quét hoàn tất. Chạy lại với `--random-user-agent` do chính WPScan hỗ trợ đã hoàn tất exit 0; không tắt WAF, không giả IP hoặc mở allowlist để quét.

WPScan tự hạ số thread khi có throttle. Lần sau cuối xóa cache và TTL 0 để không dùng response từ trước hardening. Không có `--passwords`, username list hoặc API token trên dòng lệnh. Thời gian UNIX, elapsed, requests và cached requests thực tế nằm trong các JSON bằng chứng; UTC đọc được ghi ở các kiểm tra HTTP/restart/regression.

Raw, console output và cấu hình riêng nằm ngoài Git. `scripts/sanitize-wpscan.py` chỉ xuất các trường allowlist: phiên bản/phương pháp phát hiện, loại phát hiện, số request/thời gian. Không xuất nội dung bài, user, cookie, nonce, URL đăng nhập riêng hoặc dữ liệu cấu hình.

## Các lần chạy đã đối chiếu

| Lần chạy | Bắt đầu UTC | Kết thúc UTC | Request | Cache | Exit |
|---|---|---|---:|---:|---:|
| Trước hardening | 2026-10-27T09:14:45+00:00 | 2026-10-27T09:16:22+00:00 | 690 | 11 | 0 |
| Sau cùng, WAF enabled/basic, random UA | 2026-10-27T10:43:31+00:00 | 2026-10-27T10:45:26+00:00 | 712 | 0 | 0 |

## Giới hạn dữ liệu

**Các lần agent chạy 3.8.28 trước đây không có WPScan API token.** Tool khi đó thông báo không xuất vulnerability data. Lần Docker người dùng bổ sung sau đó đã có API OK, xem báo cáo riêng. Definitions dùng cho nhận diện, không thay thế vulnerability API. Chế độ plugin passive không phát hiện đầy đủ plugin đang cài; kiểm kê filesystem tách riêng trong `PLUGIN-LICENSES.md`. Danh sách plugin trống từ tool không có nghĩa website không có plugin hoặc plugin an toàn.

Không gán CVE từ số phiên bản. `status: latest` chỉ là kết quả so với metadata WPScan tại thời điểm chạy, không chứng minh phiên bản không có lỗi hoặc dữ liệu luôn cập nhật. Wordfence WAF `enabled/basic` được đọc từ cấu hình thực; chưa kiểm chứng signature/cloud intelligence hoặc chạy malware scan Wordfence. Không suy từ việc có key thành “không còn malware”.

## Kết quả và phân loại

Các mức dưới đây là **đánh giá rủi ro cấu hình có ngữ cảnh LocalWP**, không phải CVSS/CVE đã xác nhận. Chưa có bằng chứng khai thác gây xâm nhập hoặc lỗ hổng High được xác nhận trong đợt này.

| Cảnh báo | Bằng chứng | Core/Theme/Plugin/Server | Mức và căn cứ | Khắc phục | Sau xử lý/quét lại |
|---|---|---|---|---|---|
| XML-RPC có thể truy cập | HTTP trước GET 405, WPScan nhận diện `xmlrpc` | Server/Core endpoint | Medium: bề mặt đăng nhập/RPC không dùng, không đồng nghĩa có CVE | Nginx exact location 403, module fallback từ chối | HTTP sau 403; WPScan không còn phát hiện xmlrpc |
| Generator lộ phiên bản | HTML meta và RSS trước có 7.1.3 | Core output | Low: thông tin hỗ trợ fingerprint, không phải exploit | Xóa output generator bằng hook, không xóa toàn bộ ver CSS/JS | HTML/feed không còn tag; cache busting giữ nguyên |
| Readme/wizard công khai | WPScan trước thấy readme; lần sau trung gian nhận diện qua query CSS của install page | Server/Core endpoint | Low: tài nguyên không cần công khai trên site đã cài | Chặn readme, install.php, setup-config.php bằng Nginx, không xóa/rename core | Exact endpoints trả 403; tool vẫn fingerprint từ CSS/query của trang upgrade, giữ trang upgrade để không hỏng cập nhật và không xóa ver CSS/JS |
| Directory browsing | Baseline `/wp-includes/` và uploads đã 403 | Server | Low/phòng ngừa: chưa phát hiện listing trước đổi | Bổ sung `autoindex off` tường minh trong template bền vững | Vẫn 403, không có Index of sau Stop/Start |
| Quyền file rộng | Ban đầu document root 775 và wp-config 664 | Server/filesystem | Medium: config cho phép các user local khác đọc; chưa có bằng chứng bị đọc trái phép | Chỉ application root về 755/644, config 400; giữ owner phù hợp PHP | PHP đọc được, upload/watermark hoạt động; quyền cuối ghi trong bằng chứng |
| Chưa có bằng chứng limiter/password policy server-side | Plugin bảo mật chưa có ở kiểm kê ban đầu | Plugin/authentication | Medium: dễ chịu thử mật khẩu và không có chính sách mới đã kiểm chứng; không kết luận bị xâm nhập | LLAR 5/15 phút, module profile/reset/REST; không dùng admin thật để thử sai | Lần 5 khóa, password đúng cũng bị chặn; khôi phục được; weak password bị từ chối |
| Wordfence bỏ qua IP nội bộ | Bundled `lib/wfIPWhitelist.php` có loopback/private | Plugin/configuration | Medium nếu chỉ dựa vào Wordfence để chứng minh lockout trên LocalWP | LLAR local là limiter duy nhất, tắt thành phần trùng của Wordfence | Đã khóa qua HTTP trên chính LocalWP; không giả địa chỉ IP để làm bằng chứng |
| WAF chỉ Basic | Đọc `wfFirewall` mode enabled, protection basic | Plugin | Low/giới hạn triển khai: không chạy trước WordPress như Extended | Giữ Basic, không sửa core/.user.ini; muốn nâng cấp cần backup và kiểm thử riêng | Không tuyên bố Extended Protection hoặc hiệu quả chống mọi payload |
| Header server còn fingerprint | Router LocalWP vẫn có thể trả `Server: nginx/1.26.1` | Server/router | Low: lộ thông tin, không xác nhận lỗi Nginx | Đã tắt expose PHP, server_tokens ở site; không đổi router dùng chung | Header PHP đã mất; không tuyên bố ẩn được mọi dấu vết |
| WP-Cron/robots được tool liệt kê | Findings trước/sau có `wp_cron`, `robots_txt` | Core/Server | Low/thông tin: endpoint tiêu chuẩn, chưa xác nhận DoS/lỗ hổng | Giữ để scheduler/plugin hoạt động; cân nhắc cron hệ thống/rate limit khi đưa lên production | Không đánh dấu là CVE hoặc tắt bừa chức năng nền |
| Phạm vi đối chiếu CVE chưa đầy đủ | Các lần cũ thiếu API; lần Docker mới API OK nhưng passive không nhận diện core/plugin | Quy trình kiểm tra | Medium/thiếu bằng chứng về độ phủ, không phải CVE xác nhận | Dùng token đang hoạt động để đối chiếu từng slug/phiên bản với inventory; không cần cấp lại token chỉ để chứng minh API OK | API đã xác thực theo log người dùng; toàn bộ core/plugin chưa được chứng minh bao phủ |

Không thay ảnh đại diện/banner hoặc tác phẩm thật để “sửa” cảnh báo. Trong đợt hardening lịch sử không bật Converter; trạng thái WebP mới ở README và hướng dẫn bàn giao. Không nâng core/theme/plugin ngoài các plugin bảo mật được yêu cầu mà không kiểm tra tương thích riêng.

## Bằng chứng ảnh và phần cần bổ sung

Đã có ảnh thật `hardening-settings.png`, `login-lockout.png`, `gallery-after.png`, `wps-login-redacted.png`, `wps-redirect.png`. Trường username thử trong ảnh khóa được làm mờ; không làm giả nội dung thông báo hoặc cấu hình.

Nếu báo cáo môn học yêu cầu đầy đủ ảnh màn hình giao diện công cụ, người quản lý bổ sung:

1. Wordfence Firewall hiển thị **Enabled / Basic Protection**; che key, email và URL riêng nếu xuất hiện. Không ghi thành Extended.
2. LLAR Settings hiển thị 5 retries/15 phút, cửa sổ 15 phút; crop/che trường email/IP/username. Trạng thái này cũng đã có ảnh module và kiểm thử khóa thật.
3. Console WPScan: phân biệt lần agent chạy trước không token và lần Docker có API OK; che hostname/token/tài khoản. Log và summary lần Docker đã lưu, có thể bổ sung ảnh terminal thực tế của người dùng. Không dựng ảnh console giả từ báo cáo.

## Nguồn chính thức

- [Wordfence license](https://www.wordfence.com/help/api-key/), [Brute Force Protection](https://www.wordfence.com/help/firewall/brute-force/).
- [Wordfence](https://wordpress.org/plugins/wordfence/), [Limit Login Attempts Reloaded](https://wordpress.org/plugins/limit-login-attempts-reloaded/), [WPS Hide Login](https://wordpress.org/plugins/wps-hide-login/).
- [WPScan documentation](https://github.com/wpscanteam/wpscan/wiki/WPScan-User-Documentation): yêu cầu API token cho vulnerability data và các chế độ enumeration.

File `wpscan-after-hardening.txt` hiện ghi lần chạy bị WAF trả 403; giữ nguyên thay đổi của người dùng, không coi là quét hoàn tất. File `wpscan-retry.txt` là lần chạy lại do người dùng xác nhận và cung cấp đầy đủ câu lệnh/kết quả API OK; đã che hostname, lưu nguồn gốc trong summary riêng. Các JSON 3.8.28 trước đó không bị ghi đè.