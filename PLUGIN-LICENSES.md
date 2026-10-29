# Kiểm kê Theme/Plugin và giấy phép

Phiên bản dưới đây là những gói được cài trong site LocalWP `baitapnhom1` ngày 27/10/2026. Ghi lại phiên bản thực tế trong **Plugins → Installed Plugins** và **Appearance → Themes** nếu nhóm cập nhật gói.

| Thành phần | Phiên bản | Trạng thái | Giấy phép | Mục đích |
|---|---:|---|---|---|
| Lăng Kính — Astra Child | 2.4.2 | Typography cập nhật 28/10/2026 | GPL-2.0-or-later | Giao diện, CPT thư viện ảnh và form gửi ảnh |
| Lora (Cyreal / The Lora Project Authors) | 3.008, variable 400–700 normal + italic | WOFF2 đầy đủ glyph trong child theme | SIL OFL-1.1, Reserved Font Name Lora | Serif tiếng Việt; [giấy phép](astra-child/assets/fonts/lora/OFL.txt), [nguồn và checksum](astra-child/assets/fonts/lora/manifest.json) |
| Astra | 4.14.1 | Theme cha | GPL-2.0-or-later | Theme nền |
| Twenty Twenty-Five | 1.5 | Không hoạt động | GPL-2.0-or-later | Theme mặc định WordPress |
| Twenty Twenty-Four | 1.6 | Không hoạt động | GPL-2.0-or-later | Theme mặc định WordPress |
| Twenty Twenty-Three | 1.7 | Không hoạt động | GPL-2.0-or-later | Theme mặc định WordPress |
| Image Watermark — dFactory | 2.0.16 | Đang hoạt động | MIT | Watermark tự động khi upload |
| Converter for Media — matt plugins | 6.7.0 | Đang bật; Nginx rewrite, WebP + fallback JPG | GPL-2.0-or-later | Tạo và phục vụ ảnh WebP |
| Wordfence Security | 9.0.2 | Đang hoạt động, có key; WAF enabled/basic | GPL-3.0-or-later | WAF; login/password checks tương ứng tắt để tránh trùng |
| Limit Login Attempts Reloaded | 3.3.10 | Đang hoạt động, app Local miễn phí | GPL-2.0-or-later | 5 lần sai / khóa 15 phút trên LocalWP |
| WPS Hide Login | 1.9.19 | Đang hoạt động | GPL-2.0-or-later | URL đăng nhập riêng; redirect mặc định 404 |
| Lăng Kính Security (nhóm 1) | 1.0.0 | Đang hoạt động, mã trong repo | GPL-2.0-or-later | Chính sách mật khẩu và hardening độc lập theme |

## Nguồn xác minh

- Lora: [Google Fonts tại commit cố định](https://github.com/google/fonts/tree/9862abc066c9b83bf84c3e8ed1fc2d8d48f9bbf0/ofl/lora). Chỉ đóng gói TTF sang WOFF2 bằng FontTools 4.68.0, giữ toàn bộ glyph/kiểu/weight; không subset, không đổi outline. Font giữ giấy phép riêng OFL, không đổi sang GPL/MIT. Giữ OFL.txt khi phân phối; không bán font riêng lẻ.

- [Astra](https://wordpress.org/themes/astra/) và WordPress mặc định: metadata `style.css` trong từng theme đã cài.
- [Image Watermark](https://wordpress.org/plugins/image-watermark/): header và `readme.txt` trong gói phiên bản 2.0.16 khai báo MIT.
- [Converter for Media](https://wordpress.org/plugins/webp-converter-for-media/): `readme.txt` trong gói phiên bản 6.7.0 khai báo GPLv2 or later.
- Quyền sử dụng ảnh trong `media-assets/` là quyền riêng của từng ảnh, không được xác nhận bởi license của mã/theme/plugin.

30 bản ảnh mẫu có watermark được tạo bằng renderer Image Watermark, font Open Sans từ plugin; không phân phối thêm file font hoặc sửa vendor. Bản gốc và bản watermark được giữ riêng trong theme. Watermark upload được cấu hình riêng tại Settings → Watermark. WebP dùng rewrite Nginx lưu trong template LocalWP, không dùng Pass Thru cho themes; xem README.md và config/nginx/converter-for-media.conf.

Đợt ATTT đối chiếu các gói mới với nguồn WordPress.org chính thức; không đưa mã vendor hoặc key vào repo. Wordfence được bật/đăng ký trong lúc thực hiện, giữ nguyên key đã có. Key hiện diện và onboarding đã hoàn tất không phải bằng chứng malware scan thành công. Chưa bật Extended Protection. Xem [hồ sơ bảo mật, kiểm thử thật và giới hạn API WPScan](docs/security/README.md).

Nguồn bổ sung: [Wordfence](https://wordpress.org/plugins/wordfence/), [LLAR](https://wordpress.org/plugins/limit-login-attempts-reloaded/), [WPS Hide Login](https://wordpress.org/plugins/wps-hide-login/). Giấy phép lấy từ header/readme của gói đã cài. Module riêng: `security/lang-kinh-security/lang-kinh-security.php`.
