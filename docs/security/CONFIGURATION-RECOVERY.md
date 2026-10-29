# Cấu hình và khôi phục

Thao tác trong **LocalWP → Open Site Shell**, trên site của nhóm. Xác nhận đường dẫn trước mọi lệnh. Không dùng lệnh này cho site công khai. Backup ngoài Git phải được giữ kín vì có database, key và thông tin tài khoản.

## Thành phần quản lý

- Mã module: `security/lang-kinh-security/lang-kinh-security.php`. Cài tại `wp-content/plugins/lang-kinh-security/`, kích hoạt như plugin WordPress thông thường. Xem trạng thái tại **Công cụ → Lăng Kính Security**.
- LLAR: 5 lần sai, khóa 900 giây, cửa sổ đếm 900 giây, 4 đợt chuyển khóa dài 3600 giây. `limit_login_active_app=local`, `limit_login_trusted_ip_origins=[REMOTE_ADDR]`, thông báo `log`. Không thêm allowlist rộng chỉ để vượt kiểm thử.
- WPS Hide Login: `whl_page` lưu slug riêng, `whl_redirect_admin=404`. Không ghi giá trị thật vào Git. WordPress `wp_login_url`, logout và lostpassword tự được plugin chuyển về endpoint mới.
- Wordfence: key nhập trực tiếp trên LocalWP. Giữ WAF enabled/basic; không chỉnh vendor hoặc tự đổi `.user.ini`/wp-config để bật Extended. Login và strong-password checks của Wordfence tắt vì LLAR/module đang phụ trách; application-password availability giữ hoạt động. Không bật thêm một plugin limiter khác. Nếu chuyển sang Wordfence quản lý login ở môi trường khác, phải kiểm chứng địa chỉ IP thực và tắt limiter cũ trước.
- Module áp dụng mật khẩu mới/đổi qua profile, reset, REST; không thay mật khẩu hiện hữu. Đường CLI/programmatic tin cậy không bị chặn để phục hồi; không xem đây là kiểm tra mọi lệnh gọi PHP từ plugin khác.

## Nginx/PHP bền vững

`security/nginx-hardening.conf` là snippet đặt trong `server {}`. Đã chép vào template site `conf/nginx/includes/lang-kinh-security.conf.hbs`, và thêm dòng sau vào `conf/nginx/site.conf.hbs`:

```nginx
include includes/lang-kinh-security.conf;
```

Không sửa chỉ file runtime trong `.config/Local/run/`. LocalWP tạo lại runtime sau Stop/Start. Các `location =` chỉ áp dụng endpoint không dùng, không chặn `wp-admin` nói chung, admin-ajax hoặc REST.

Trong `conf/php/php.ini.hbs`: `display_errors=Off`, `display_startup_errors=Off`, `expose_php=Off`, giữ `log_errors=On`. LocalWP đã được Stop/Start và kiểm tra file runtime sinh lại. Kiểm tra bằng binary Nginx của môi trường site với `-t`, đúng `-c` và `-p`; không dùng cấu hình Nginx hệ thống thay thế.

Rollback cấu hình: Stop site, khôi phục **chỉ template liên quan** từ bản backup riêng, Start lại, kiểm tra HTTP. Không phục hồi toàn bộ database nếu chỉ cần sửa cấu hình.

## Lấy URL đăng nhập riêng

Mở file `access-and-recovery.txt` trong thư mục backup riêng của đợt này hoặc lấy ngay trên máy bằng:

```sh
wp option get whl_page
```

Ghép với domain local. Không chụp/commit kết quả lệnh này. Dùng tài khoản quản trị và mật khẩu hiện có của bạn.

Nếu mất URL, có thể vô hiệu WPS Hide Login qua CLI:

```sh
wp plugin deactivate wps-hide-login
```

Sau đó `wp-login.php` hoạt động lại. Khi không dùng được CLI, đổi tên **thư mục plugin** `wps-hide-login` bằng file manager để WordPress ngừng nạp nó; không đổi file core `wp-login.php` hoặc thư mục `wp-admin`. Đổi lại thư mục/kích hoạt sau khi sửa slug; thử trong cửa sổ riêng trước khi kết thúc phiên quản trị đang có.

## Mở khóa đăng nhập

Khóa áp dụng theo IP; LocalWP có thể gom nhiều phiên vào cùng loopback. Trước kiểm thử đã chụp lại trạng thái limiter, sau kiểm thử khôi phục đúng trạng thái đó bằng `scripts/security-local-fixtures.php unlock` với private config. Chỉ dùng script này cho cấu hình fixture được tạo cho đợt kiểm thử.

Trường hợp vận hành khẩn cấp, owner dùng LocalWP Site Shell:

```sh
wp option delete limit_login_lockouts
wp option delete limit_login_retries
wp option delete limit_login_retries_valid
```

Các lệnh này xóa bộ đếm/khóa của limiter, không đổi mật khẩu. Đây là thao tác khôi phục thủ công có chủ đích, không đặt trong cron. Hoặc vô hiệu riêng LLAR bằng `wp plugin deactivate limit-login-attempts-reloaded`, đăng nhập, cấu hình lại và bật lại. Wordfence login security đang tắt trong cấu hình local nên không có bộ đếm thứ hai cần mở.

Nếu module hardening gây xung đột: `wp plugin deactivate lang-kinh-security`. Rule Nginx vẫn chặn XML-RPC; plugin tắt không tự xóa cấu hình server.

## Quyền file

Đã xác nhận PHP-FPM/Nginx chạy cùng owner của site, không đổi owner/group. Chỉ duyệt `app/public`, bỏ qua symlink: thư mục 755, file ứng dụng 644, riêng `wp-config.php` 400. File dữ liệu nhạy cảm trong `wp-content/wflogs/` được Wordfence tự ghi 600 (chặt hơn 644) và được giữ theo cơ chế plugin, không mở rộng quyền của chúng. Backup ở ngoài document root, thư mục 700/file 600. LocalWP có thể sinh lại `local-xdebuginfo.php` với mode 664 khi Start; module chỉ hạ đúng file helper này về 644 khi WordPress chạy trong môi trường `local`, không sửa nội dung. Không áp dụng chmod đệ quy cho `$HOME`, project hay `.git`; không dùng 777. Khi chuyển sang host có UID khác, kiểm tra owner/group trước, dùng 440 nếu cần group đọc; không sao chép máy móc cấu hình owner hiện tại.

## Chạy lại kiểm thử

`tests/security-local.cjs` yêu cầu `LK_ALLOW_LOCAL_SECURITY_FIXTURES=1`, `LK_SECURITY_PRIVATE` trỏ file ngoài Git, Playwright/browser, PHP CLI và socket qua biến môi trường. Script từ chối domain không kết thúc `.local`. Tạo fixture riêng, không dùng tài khoản quản trị thật để thử sai; lưu trạng thái khóa để mở lại trong `finally`. Không chạy lại khi fixture đã được cleanup; cần tạo đợt riêng và backup mới.

Sau kiểm thử, `scripts/security-local-fixtures.php cleanup` chỉ xóa user/ảnh/bài mang danh tính fixture đã ghi riêng, sau đó so fingerprint nội dung gốc, thumbnail, mật khẩu user gốc và theme mods. Không dùng restore SQL tổng để dọn fixture vì có thể xóa việc người dùng đang làm.
