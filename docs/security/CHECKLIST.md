# Checklist Phần 3

`Đạt` chỉ áp dụng phạm vi đã kiểm tra trên LocalWP; không suy rộng sang production.

| Yêu cầu | Trạng thái | Bằng chứng / giới hạn |
|---|---|---|
| Xác minh repo/nhánh/site, bảo toàn thay đổi | Đạt | Nhánh `huyyhoangg`, nền `87a81af`, sạch lúc bắt đầu; không đổi code theme trong đợt này |
| Sao lưu site/database/cấu hình trước đổi | Đạt | Archive site, dump SQL, cấu hình template/runtime ngoài Git; bản bổ sung giữ onboarding Wordfence; hồ sơ riêng |
| Plugin bảo mật nguồn chính thức | Đạt | Wordfence 9.0.2 có key/không còn onboarding; gói trùng WordPress.org; LLAR/WPS tải từ WordPress.org; `plugin-status.json` |
| Không giả định license/quét Wordfence thành công | Đạt trong phạm vi cấu hình | Chỉ ghi nhận trạng thái key/onboarding/WAF; không tuyên bố malware scan/cloud intelligence đã kiểm chứng |
| Không chạy trùng chức năng login/password | Đạt | Wordfence login/strong-password checks tắt; LLAR phụ trách lockout, module phụ trách mật khẩu |
| Giới hạn sai và khôi phục truy cập | Đạt | Kiểm thử 4 lần chưa khóa, lần 5 khóa; mật khẩu đúng vẫn bị chặn; khôi phục CLI mở lại; `regression-localwp.json`, `login-lockout.png` |
| Chính sách mật khẩu server-side | Đạt | Từ chối mật khẩu yếu qua REST, hồ sơ (kể cả checkbox weak), reset; reset mạnh đăng nhập được |
| Không đổi mật khẩu quản trị thật | Đạt | Hash password của user có trước trùng sau cleanup; `preservation.json` |
| Đổi URL đăng nhập đúng chuẩn | Đạt | WPS Hide Login; URL mới 200, mặc định 404; không rename core; `http-after.json` |
| Login/logout/quên mật khẩu/admin/quay lại form | Đạt | Tài khoản thử; email reset nhận ở Mailpit local; link dùng URL mới; đăng nhập lại thành công |
| Admin AJAX/REST/form không bị hỏng | Đạt | Heartbeat AJAX có nonce trả 200 và server time; cookie-auth REST dùng được; form upload liên tiếp qua HTTP |
| Ẩn phiên bản phù hợp, giữ cache busting | Đạt trong phạm vi | Generator HTML/feed đã ẩn; `style.css?ver=` vẫn còn. Vẫn có thể fingerprint WordPress từ dấu hiệu khác, không coi đây là phòng thủ chính |
| Tắt Directory Browsing | Đạt | Đã 403 từ baseline; bổ sung `autoindex off` tường minh, vẫn 403 sau Stop/Start |
| Chặn XML-RPC bằng Nginx thật | Đạt | Trước 405/scan nhận diện; sau 403, không còn phát hiện trong WPScan |
| Cấu hình tồn tại sau Stop/Start | Đạt | Dùng template `conf/nginx/*.hbs`, `conf/php/php.ini.hbs`; đã Stop/Start qua LocalWP thật; `localwp-restart.json` |
| Hardening độc lập theme | Đạt | Plugin riêng trong repo, không sửa Astra hoặc code child theme |
| Quyền thư mục/file/config đúng owner | Đạt | Đã kiểm tra cùng UID PHP/Nginx; 755/644, config 400; PHP đọc được, upload và watermark tạo file được; `permissions.json`, `final-verification.json` |
| Không chmod rộng ngoài application | Đạt | Chỉ `app/public`; không đổi owner/group, không 777, bỏ qua symlink |
| WPScan trước/sau chạy thực tế | Đạt | WPScan 3.8.28; scope LocalWP; exit 0; thời gian/request ở báo cáo JSON |
| API WPScan xác thực hoạt động | Đạt theo log người dùng | Docker WPScan 4.1.0: API OK, Free, dùng 3 request, còn 19; `wpscan-retry.txt`, `evidence/wpscan-api-user-run.json` |
| CVE/vulnerability intelligence đầy đủ | Chưa đầy đủ | API đã OK nhưng lần passive không nhận diện core/plugin; chưa đủ đối chiếu toàn inventory. Xem `WPSCAN-API-USER-RUN.md`; không kết luận “không lỗ hổng” |
| Bảng phân loại và căn cứ | Đạt | `SCAN-AND-ASSESSMENT.md`; phân biệt cảnh báo cấu hình với lỗ hổng đã xác nhận, không gán CVE suy đoán |
| Ảnh cấu hình/kết quả thật | Đạt một phần hồ sơ | Có ảnh module, khóa thực tế, gallery. Có ảnh WPS đã che slug/redirect; còn ảnh trang Wordfence/LLAR nguyên bản và ảnh console WPScan; danh sách cần bổ sung ở báo cáo |
| Admin/Editor duyệt, Author không tự publish | Đạt | Tài khoản thử trên LocalWP; REST 403, wp-admin vẫn pending, Editor publish, Admin đọc bài pending |
| Hai upload liên tiếp, hiển thị cùng 30 mẫu | Đạt | Hai ID pending có featured image, một bài duyệt xuất hiện cùng đủ 30 mẫu; fixture đã dọn |
| Album/lọc/tìm/lightbox/banner/mobile | Đạt | Kiểm thử sau Stop/Start; banner không đổi khi duyệt ảnh mới; mobile không tràn ngang |
| Không lỗi do quyền, không lỗi PHP/JS cuối kỳ | Đạt trong luồng đã chạy | Upload/Watermark thành công; `regression-localwp.json`, `final-verification.json` ghi log slice cuối và lỗi JS |
| Giữ nguyên tác phẩm/ảnh đại diện/banner; trạng thái WebP theo đợt | Đạt | `preservation.json`; Converter vẫn tắt; fingerprint dữ liệu gốc và theme mods không đổi |

Không tự quy đổi thành điểm. Các mục thiếu độ phủ CVE/ảnh bổ sung không được đánh dấu hoàn thành toàn bộ. API token đã hoạt động trong lần bổ sung người dùng cung cấp.

Các hàng về Converter tắt phản ánh đợt hardening cũ. Đợt sửa WebP sau đã bật Converter + Nginx rewrite; không tái ghi inventory/scan JSON lịch sử. Xem [hướng dẫn hiện tại](../installation/README.md).
