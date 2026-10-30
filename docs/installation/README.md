# Cài đặt và khôi phục Lăng Kính

## 1. Môi trường đã kiểm chứng

Ubuntu 24.04.4 LTS, LocalWP 10.2.0, Nginx 1.26.1, PHP 8.2.29 (GD, mbstring, mysqli), MySQL 8.4.0, WordPress 7.1.3; Astra 4.14.1 và child theme 2.4.1. Đây là inventory thực tế, không bắt buộc giữ bản cũ nếu có advisory; thử cập nhật trên clone trước. Cần WP-CLI trong LocalWP Site Shell để import, thay URL an toàn, phục hồi media và bootstrap. Không cần Elementor.

## 2. Tạo site và cài source

1. Cài LocalWP từ nguồn chính thức, tạo site mới dùng Nginx, ghi URL local và đường dẫn `app/public`. Không dùng database/site đang có dữ liệu để thử import.
2. Trong site mới, cài WordPress bằng LocalWP. Cài theme Astra từ WordPress.org. Copy **thư mục `astra-child`** của repo vào `app/public/wp-content/themes/astra-child/`; không lồng vào Astra.
3. Copy `security/lang-kinh-security/` vào `app/public/wp-content/plugins/lang-kinh-security/`. Cài riêng Image Watermark, Converter for Media, Wordfence, LLAR và WPS Hide Login từ các link trong [inventory](../../PLUGIN-LICENSES.md), nhưng chỉ bật sau bước restore/bootstrap khi đã cấu hình.
4. Tạo database và wp-config mới bằng LocalWP; dùng credential/salt mới của máy nhận. Không copy wp-config, runtime hoặc file license của máy nguồn. Source code giao diện nằm trong repo; WordPress chỉ chạy các file đã chép vào document root.

## 3. Import SQL và đổi URL

Mở **Open Site Shell** của đúng site mới. Xác nhận `pwd`, `wp option get siteurl`, `wp db prefix`. Đặt đường dẫn repo vào biến riêng (thay đường dẫn minh họa, không ghi secret):

```bash
LK_REPO_ROOT='/duong-dan/repo-hien-tai'
wp db export '/duong-dan-rieng/backup-site-moi.sql'
wp db import "$LK_REPO_ROOT/database/gallery-demo.sql"
wp search-replace 'http://lang-kinh-demo.invalid' 'http://ten-site-moi.local' \
  --all-tables-with-prefix --precise --skip-columns=guid --dry-run
wp search-replace 'http://lang-kinh-demo.invalid' 'http://ten-site-moi.local' \
  --all-tables-with-prefix --precise --skip-columns=guid
```

SQL dùng prefix `wp_`; wp-config của site nhận phải dùng prefix tương ứng. `wp db import` thay dữ liệu các bảng trong dump, vì vậy chỉ chạy trong DB mới/clone đã sao lưu. Không dùng editor replace chuỗi trong SQL: serialized strings lưu cả độ dài. GUID cũ là định danh demo, không phải URL ảnh đang phục vụ; cố ý không đổi GUID. Xác minh `home/siteurl` và ảnh hiển thị sau thay URL. Nếu chỉ cần xem 30 mẫu mà không restore dữ liệu, có thể cài child theme trên WordPress mới và bỏ qua import.

## 4. Phục hồi media và tạo tài khoản mới

```bash
export LK_REPO_ROOT
wp theme activate astra-child
wp eval-file "$LK_REPO_ROOT/scripts/restore-demo-media.php"
wp plugin activate lang-kinh-security
wp user create new_admin admin@example.invalid --role=administrator --prompt=user_pass
wp option update admin_email 'email-nguoi-nhan@example.invalid'
wp rewrite flush
```

Thay email minh họa bằng email của người nhận khi dùng thật; nhập mật khẩu mới tại prompt (ít nhất 12 ký tự, hoa/thường/số/ký tự đặc biệt). Không lưu mật khẩu vào Git. Các `demo_author_*` trong SQL bị vô hiệu mật khẩu; chỉ kích hoạt tài khoản demo cần thiết bằng mật khẩu mới qua CLI, hoặc tạo Author/Editor mới. Không thử đăng nhập bằng mật khẩu tài khoản thật của site nguồn. Nếu tài khoản bootstrap admin vừa tạo, kiểm tra đăng nhập trong cửa sổ riêng trước khi bật đổi URL/limiter.

Media demo được phục hồi từ file có sẵn trong child theme theo manifest, không phải bộ uploads gốc. Script tạo thumbnail/medium/lk-gallery và metadata đúng file. Xem [phạm vi SQL và quyền ảnh](../../database/README.md). Với ảnh thật được phép bàn giao, phải chuẩn bị gói uploads riêng có checksum và metadata tương ứng; SQL tự nó không chứa toàn bộ ảnh.

## 5. Watermark và WebP trên Nginx

- Bật Image Watermark, chọn tự động frontend/admin, text `© Lăng Kính · Nhóm 1`, font có tiếng Việt (Open Sans trong bản đã kiểm tra), vị trí/kích thước phù hợp. Bật backup trước xử lý; chọn các size cần bảo vệ và thử một ảnh mới. Không watermark lặp lên 30 ảnh đã đóng dấu trong `assets/demo-watermarked`.
- Bật Converter for Media, WebP, GD phù hợp môi trường, auto conversion, chất lượng đã thử 85; chọn uploads/themes. Dùng loader **rewrite (`htaccess`)**, không Pass Thru với themes ở 6.7.0. Tên loader không có nghĩa Nginx đọc `.htaccess`.
- Sao lưu template site trước khi sửa. Copy [snippet WebP](../../config/nginx/converter-for-media.conf) vào `conf/nginx/includes/converter-for-media.conf.hbs`. Trong `server {}` của `conf/nginx/site.conf.hbs`, thêm `include includes/converter-for-media.conf;` **trước các include/location regex khác**. Không ghi đè template toàn bộ. Nginx phải có MIME image/webp.
- Dùng chức năng bulk conversion được plugin hỗ trợ cho ảnh đã watermark. Derivative tại `uploads-webpc`, URL JPG giữ nguyên. Xác minh Network: Accept WebP → image/webp; Accept JPEG hoặc thiếu derivative → JPEG; cả hai Vary: Accept. Clear cache sau thay cấu hình.

![Network WebP thật](../report/figures/network-webp.png)

Nếu ảnh vỡ: mở JPG gốc, xem status/MIME/body và đường dẫn loader; kiểm tra file derivative, thứ tự location và log Nginx/PHP. Tắt plugin chỉ là rollback tạm, không phải hoàn thành WebP. Không sửa vendor hoặc đổi hàng loạt đuôi JPG.

## 6. Hardening riêng cho máy nhận

- Wordfence: đăng ký/key riêng từ nhà cung cấp và hoàn tất onboarding; kiểm tra Enabled/Basic. Không gọi là Extended/Premium nếu chưa có bằng chứng. Giữ một limiter chính: LLAR 5 lần sai/900 giây; policy mật khẩu do module nhóm. Tắt thành phần trùng tương ứng khi dùng cấu hình này.
- WPS Hide Login: chọn slug mới riêng, lưu ngoài repo; kiểm tra login/logout/lostpassword/quay về upload. Không copy slug thật của máy nguồn. Giữ Site Shell làm đường khôi phục.
- Copy [hardening Nginx](../../security/nginx-hardening.conf) thành `conf/nginx/includes/lang-kinh-security.conf.hbs`; include trong server sau snippet WebP. PHP template: display_errors/display_startup_errors/expose_php Off, log_errors On.
- Xem owner/group và user chạy PHP/Nginx trước khi chmod. Chỉ application directories 755, files 644; wp-config 400 hoặc 440 nếu phù hợp owner/group và PHP vẫn đọc được. Không chmod toàn home, không 777, giữ file nhạy cảm vendor 600 nếu đã chặt hơn.
- Hướng dẫn khôi phục khi quên slug/khóa: [CONFIGURATION-RECOVERY.md](../security/CONFIGURATION-RECOVERY.md). Không tự xóa limiter định kỳ để né khóa.

## 7. Stop/Start và nghiệm thu

Stop rồi Start **site mới** trong LocalWP. Kiểm tra runtime được sinh lại có các include, Nginx `-t` với binary/config của đúng site, homepage HTTP 200, XML-RPC 403, thư mục không liệt kê. Kiểm tra đăng nhập riêng, giới hạn thử sai bằng tài khoản dùng một lần, upload hai bài pending, Author không publish, Editor duyệt, 30 mẫu còn đủ, banner không đổi, filter/search/lightbox/mobile, WebP/fallback và log không có lỗi ngoài dự kiến. DB restore đã được thử riêng; các plugin/key/URL riêng cần nghiệm thu lại trên máy nhận.

## 8. Lỗi thường gặp

| Hiện tượng | Kiểm tra/xử lý |
|---|---|
| Trang gửi ảnh 404 | Child theme active, migration chạy, trang shortcode tồn tại, flush permalink; không tự ghi đè trang người dùng. |
| Không đăng nhập sau import | User trong SQL cố ý vô hiệu mật khẩu; tạo admin mới qua Site Shell. Nếu WordPress yêu cầu xác nhận email quản trị, xác nhận/cập nhật địa chỉ của người nhận rồi vào Dashboard; đây không phải lỗi mật khẩu. |
| Ảnh attachment 404 | Đã chạy restore-demo-media, quyền uploads, siteurl và manifest/hash đúng chưa. |
| Ảnh WebP vỡ | Pass Thru/themes, MIME/body, derivative, vị trí rule và cache; xem bước 5. |
| Quên URL/khóa IP | Site Shell phục hồi riêng WPS/LLAR; không đổi core hoặc mật khẩu admin thật. |
| Upload lỗi kích thước | Form 12 MB khác PHP upload_max_filesize/post_max_size và client_max_body_size Nginx; không tăng mọi giới hạn để né kiểm tra. |
| Nội dung khác site nguồn | SQL đã ẩn danh, thay media và thêm hai bài demo chỉ trên clone; không phải backup toàn site. |