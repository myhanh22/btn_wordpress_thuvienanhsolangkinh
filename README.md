# Thư viện ảnh số — Nhóm 1

Giao diện mẫu **Lăng Kính**, child theme của Astra cho WordPress.

## Cài đặt

Cài WordPress trong LocalWP, cài theme cha Astra, rồi tải ZIP child theme lên tại **Giao diện → Thêm mới → Tải giao diện lên** và kích hoạt. Trang chủ tự hiển thị 30 ảnh minh họa. Không cần Elementor hoặc nhập SQL để xem mẫu.

Xem [hướng dẫn cài đặt, nhập tác phẩm và phạm vi tính năng](astra-child/HUONG-DAN.md).

## Cấu trúc

- `astra-child/`: mã giao diện, trang chủ, bộ lọc, lightbox và ảnh minh họa đi kèm.
- `media-assets/`: tài sản ảnh gốc hiện có; quyền từng ảnh cần bổ sung hồ sơ, xem THIRD-PARTY-NOTICES.md.
- `scripts/build-demo-watermarks.php`: tạo lại 30 bản watermark bằng Image Watermark, không sửa ảnh gốc.

Có thể cài thủ công bằng cách copy `astra-child` vào `wp-content/themes/` trên site LocalWP. Giữ theme cha `astra` bên cạnh, không lồng child theme vào thư mục Astra.

## Tình trạng

Có giao diện responsive, chủ đề/album, tác giả WordPress, tìm kiếm không dấu, lightbox và lazy loading. CPT **Tác phẩm** lưu bài ảnh trong database WordPress. Trang **Đăng tác phẩm** dùng đăng nhập WordPress, nhận JPG/PNG/WebP và tạo bài chờ duyệt. Author không được tự xuất bản tác phẩm qua REST hoặc wp-admin; Admin/Editor duyệt bài. Bản 2.4.0 tự cập nhật quyền và tạo trang gửi ảnh còn thiếu trên theme đang hoạt động.

Trang chủ luôn giữ đủ 30 ảnh mẫu ghi “Bộ sưu tập mẫu”, đặt sau ảnh thật đã duyệt có ảnh đại diện. Ảnh mẫu đã có watermark “© Lăng Kính · Nhóm 1” nhúng trong ảnh; giữ ảnh gốc riêng, không nhập mẫu vào database. Liên kết **Đăng tác phẩm** có trên menu trang chủ và điện thoại.

Form dùng các chủ đề hiện có trên web, mặc định **Tự do**. Chọn **Khác** để nhập tên chủ đề mới (tối đa 80 ký tự); tên đã có được dùng lại. Mọi tác phẩm gửi lên vẫn phải duyệt.

Ô tìm kiếm được căn lại viền focus; lightbox bỏ viền vuông ở nút đóng/trước/sau nhưng giữ focus cho bàn phím. Bố cục lưới được tính lại theo ảnh đang hiển thị khi lọc.

Ảnh banner cố định, không đổi khi đăng tác phẩm mới. Chọn ảnh tại **Giao diện → Tùy biến → Lăng Kính — Ảnh banner**; có thể nhập chú thích riêng. Khi chưa chọn hoặc đã xóa ảnh, dùng ảnh mẫu “Lối về bình yên” có watermark.

WebP trên Nginx dùng **quy tắc rewrite** của Converter for Media, không dùng Pass Thru cho ảnh theme. Bản 6.7.0 có thể đổi URL theme sang loader nhưng loader chỉ cho phép uploads, gây response HTML rỗng. Chọn `loader_type=htaccess` (chế độ rewrite, không phải Nginx đọc `.htaccess`), giữ tự động chuyển đổi và các thư mục uploads/themes. Đặt [snippet Nginx](config/nginx/converter-for-media.conf) vào `conf/nginx/includes/converter-for-media.conf.hbs` của site LocalWP, thêm `include includes/converter-for-media.conf;` **trong server, trước các include/location regex khác**, rồi Stop/Start. Đây là cách phục vụ theo [hướng dẫn chính thức của plugin](https://mattplugins.com/docs/nginx-configuration), giới hạn vào uploads/themes và chỉ bật WebP. Không ghi đè toàn bộ template Nginx; giữ nguyên include bảo mật. Nginx cần MIME `image/webp`.

URL JPG/PNG giữ nguyên: client nhận WebP khi gửi `Accept: image/webp` và có derivative; ngược lại nhận ảnh gốc. Cả hai có `Vary: Accept`, cache ngắn 5 phút; làm mới cache trình duyệt sau khi đổi cấu hình. Derivative nằm tại `wp-content/uploads-webpc/{uploads|themes}/...jpg.webp`. Chuyển đổi từ bản đã watermark; không chuyển đường dẫn frontend về ảnh mẫu gốc chưa watermark. Muốn khôi phục: phục hồi template Nginx và tùy chọn loader từ backup riêng, Stop/Start; không dùng Pass Thru với themes. Không xóa ảnh gốc hoặc backup watermark.

Form giới hạn ảnh **12 MB**, độc lập với `upload_max_filesize` và `post_max_size` của PHP. Request vượt giới hạn tổng POST nhận lỗi 413 trước bước kiểm tra submit, kể cả khi PHP làm rỗng POST/FILES; khi đó cần nhập lại nội dung và chọn ảnh nhỏ hơn. Giá trị K/M/G dùng hệ số 1024; 0/-1 không áp giới hạn tổng POST. Không cần hạ cấu hình PHP của site để áp dụng giới hạn 12 MB.

Sanitizer yêu cầu `--metadata /đường/dẫn/riêng/runner.json`, chứa `verified: true`, `tool_version` từ lần chạy thực, `command_argv` thực, `exit_code` thực (hoặc null nếu không biết), và `raw_sha256` của đúng raw JSON. Runner phải lấy mã thoát của WPScan, không lấy mã `tee`; có thể lấy bằng `subprocess.run(...).returncode` hoặc `${PIPESTATUS[0]}` ngay sau pipeline Bash. Metadata và raw phải lưu ngoài repo, quyền 600. Không điền phiên bản/câu lệnh hiện tại cho raw của lần cũ. Script giữ thời gian scan gốc, che credential, từ chối schema rỗng/sai, đánh dấu exit chưa biết là `unknown` và không suy ra cấu hình WAF từ user-agent. API không thành công được ghi rõ là thiếu dữ liệu lỗ hổng; scan hoàn tất không chứng minh site an toàn.

Kiểm thử trên WordPress riêng: xem [tests/README.md](tests/README.md).

Watermark tự động và WebP được xử lý bằng plugin; xem [bảng kiểm kê theme/plugin/license](PLUGIN-LICENSES.md). Phần 3 đã có cấu hình bảo mật và kiểm chứng trên LocalWP thật: Wordfence, giới hạn đăng nhập, URL đăng nhập riêng, chính sách mật khẩu, Nginx và phân quyền file; xem [hồ sơ ATTT](docs/security/README.md). WPScan trước/sau đã chạy; lần Docker bổ sung do người dùng thực hiện đã có API OK (Free, dùng 3 request, còn 19). [Báo cáo API và giới hạn độ phủ CVE](docs/security/WPSCAN-API-USER-RUN.md) ghi rõ passive chưa nhận diện core/plugin, không kết luận “không có lỗ hổng”. Database dùng các bảng WordPress mặc định; không cần SQL schema riêng. Không commit mật khẩu, cấu hình kết nối hay database chứa thông tin nhạy cảm lên GitHub công khai.

## Bộ bàn giao và báo cáo học phần

- [Cài đặt và khôi phục đầy đủ](docs/installation/README.md): môi trường, Astra/child theme, plugin, SQL, media, admin mới, Nginx/WebP, bảo mật, Stop/Start và xử lý lỗi.
- [Database demo đã ẩn danh](database/README.md), [SQL](database/gallery-demo.sql), [manifest media](database/media-manifest.json) và [kết quả import/restore riêng](database/restore-verification.json). SQL có nguồn từ clone site thật; hai bài demo bổ sung chỉ ở clone, ảnh riêng được thay bằng ảnh mẫu có sẵn. Không có mật khẩu mặc định hoặc key/site config thật.
- [Báo cáo LaTeX và PDF](docs/report/README.md), [WBS đề xuất 5 người](docs/report/WBS.md). Không xác nhận đóng góp theo tên; nhóm tự điền thông tin học phần và repo nộp cuối.
- [Phạm vi license/copyright và quyền ảnh](THIRD-PARTY-NOTICES.md): giữ LICENSE gốc và license riêng của thành phần; quyền ảnh không tự được MIT/GPL bảo đảm.

![Giao diện LocalWP hiện tại](docs/report/figures/home-desktop.png)

![WebP trên Nginx qua DevTools thật](docs/report/figures/network-webp.png)

![Bản demo khôi phục trên môi trường riêng](docs/report/figures/restore-demo.png)

Cấu trúc bàn giao (giữ nguyên đường dẫn source đang dùng):

```text
astra-child/                 # functions.php, inc/, style.css, js/, ảnh mẫu
security/lang-kinh-security/ # module tự viết, không phải vendor
config/nginx/               # snippet WebP; hardening ở security/
database/                   # SQL sạch, manifest, kết quả restore
docs/installation/          # cài đặt/khôi phục từ đầu
docs/security/              # bằng chứng bảo mật theo lịch sử
docs/report/                # LaTeX, references, figures, PDF/DOCX
scripts/                    # chuẩn bị clone, media, sanitizer, build/QA
tests/                      # kiểm thử độc lập và tích hợp có guard
```