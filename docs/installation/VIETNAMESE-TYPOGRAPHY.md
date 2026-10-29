# Typography tiếng Việt Ubuntu / Windows

## Kết luận và giới hạn ngày 28/10/2026

- Repo trước sửa: `b4bff6a7232d216352ab858973385b4168bc44ee`. Chưa truy cập máy Windows, chưa biết commit/theme/CSS/database bên đó. Không có ảnh đính kèm khả dụng trong phiên này. Không kết luận lỗi Windows do Georgia, OS hay encoding database.
- LocalWP Ubuntu chạy `astra-child`, parent `astra`, bản 2.4.1 trước sửa. Repo và thư mục triển khai là **hai bản riêng**. `style.css`, `front-page.php`, `js/custom.js`, `inc/hero.php` giống byte trước sửa; `functions.php` chỉ khác khoảng trắng ở lời gọi `add_action('init', 'lk_upgrade_theme', 20)` có sẵn trong working tree. Không ghi đè thay đổi này.
- HTML thực tải `lk-style-css` từ `/wp-content/themes/astra-child/style.css?ver=...`, sau CSS Astra. HTTP và meta charset đều UTF-8; `blog_charset=UTF-8`. Additional CSS đọc từ WordPress là rỗng. Không có key typography tùy chỉnh trong `astra-settings` ở site này; CSS mặc định Astra vẫn được sinh inline. Đây không phải bằng chứng về database Windows.
- **Xác nhận phụ thuộc font hệ thống:** source yêu cầu Georgia/Times New Roman nhưng Rendered Fonts của Edge headless Linux thực dùng Liberation Serif (cả normal/italic), không phải Georgia. Đây là sai khác có thể loại bỏ trong source, chưa chứng minh nguyên nhân dấu lệch trên Windows.
- Các file PHP/CSS/JS theme là UTF-8 NFC, không ký tự thay thế U+FFFD hay dấu kết hợp. Text thực của các vùng được kiểm tra là NFC, không có khoảng trắng bất thường. Không chỉnh nội dung hoặc encoding database.
- Trước sửa: hero 400 normal/italic, letter-spacing -1.7px, line-height 63px tại viewport 1440; tiêu đề mục -1px. Không có bằng chứng cần thay tracking/line-height nên giữ nguyên. Bản sửa giữ serif/italic nhưng hình dáng/độ rộng Lora có khác font hệ thống trước đây.

## Bản sửa 2.4.2

`style.css` khai báo hai `@font-face` cùng alias `LK Lora`, đường dẫn tương đối `assets/fonts/lora/lora-normal.woff2` và `lora-italic.woff2`, weight variable 400–700. Toàn bộ glyph nguồn được giữ, bao gồm Latin, Latin mở rộng và tiếng Việt; không chỉ lấy subset Latin. Không dùng `local()` để tránh chọn nhầm bản font cài sẵn. `font-display:swap` cho phép fallback tạm trong lúc tải; sau `document.fonts.ready` phải là Lora.

Tất cả vị trí serif cũ dùng chung `--lk-serif`: banner, h1/h2, em/i trong dòng serif, caption banner, tên ảnh, album, trạng thái rỗng, số thứ tự nghiêng, lightbox. `font-synthesis:none` ở các vùng này; normal/italic có file thật. Chữ nhỏ sans-serif giữ nguyên thiết kế. Không dùng `!important` để che cấu hình người dùng. Giấy phép OFL, nguồn commit cố định, phiên bản và SHA-256 nằm trong `assets/fonts/lora/` và bảng `PLUGIN-LICENSES.md`.

Ubuntu đã đồng bộ **chỉ style.css và assets/fonts/lora/** sang LocalWP và so sánh từng file byte-for-byte. `filemtime(style.css)` trong enqueue hiện có đổi URL version. Không cập nhật PHP, ảnh, banner hay database.

## Đối chiếu và triển khai Windows

1. Ở **repo** của cả hai máy chạy `git rev-parse HEAD`, `git status --short`. So sánh commit sau khi người phụ trách đưa commit sửa lên remote; phiên này không push. Cùng commit chưa đủ nếu working tree khác hoặc LocalWP còn file cũ.
2. Trong LocalWP chọn đúng site → **Go to site folder**. Xác định theme trong `app/public/wp-content/themes/astra-child`, không nhầm với repo clone. Site Shell: `wp option get stylesheet`, `wp option get template`, `wp theme list`. Chạy `wp eval-file "ĐƯỜNG-DẪN-REPO/scripts/typography-audit.php"` để lấy theme directory/version, SHA-256, Additional CSS, typography Astra và charset. Script chỉ đọc, không xuất credentials. So sánh file repo/deploy bằng `Get-FileHash -Algorithm SHA256` (PowerShell); Ubuntu dùng `sha256sum`. So sánh cả hai WOFF2 với manifest.
3. Sao lưu CSS hiện có rồi copy `astra-child/style.css` và toàn bộ `astra-child/assets/fonts/lora/` từ repo vào theme đang active. Không copy database, không ghi đè uploads/wp-config. Giữ nguyên tên file/chữ hoa thường; Linux phân biệt hoa thường dù Windows có thể không.
4. Mở DevTools → Network → Disable cache; hard reload. Nếu dùng plugin cache/minify/CDN thì purge đúng cache site/CSS. Xác nhận response CSS có `LK Lora` và hai `@font-face`, đúng URL child theme và version mới. Hai WOFF2 phải HTTP 200, MIME `font/woff2`, không phải HTML lỗi/redirect. Kiểm tra Console lỗi decode/CORS/CSP; không coi status 200 đơn lẻ là đủ.
5. **Additional CSS và Astra nằm trong database, pull Git không đồng bộ chúng.** Tìm `wp-custom-css`, `astra-theme-css-inline-css`, CSS plugin và các rule `font`, `font-family`, `font-style`, `font-weight`, `font-synthesis`, `letter-spacing`, `line-height` trên đúng element. Rule `body {font-family:Arial}` không thắng font đặt trực tiếp trên h1/em; `font` shorthand cũng có thể reset family/style/weight. Trong Styles, xem rule nào bị gạch và nguồn/line của rule thắng. Tạm bỏ chọn từng declaration để xác nhận hiệu quả, sau đó chỉ sửa/xóa declaration hoặc selector thử nghiệm Arial/Georgia/spacing gây xung đột trong Additional CSS, lưu bản sao trước. **Không xóa toàn bộ Additional CSS**, không thêm `!important` toàn site. Ubuntu không có rule Additional CSS cần xóa.
6. Inspect riêng `#hero-title`, `#hero-title em`, `.lk-hero-caption strong`, `.lk-card-info h3`, `.lk-album h3`, `#lk-lightbox-title` khi mở ảnh. Ghi Computed Styles: font-family, weight, style, synthesis, letter-spacing, line-height. Rendered Fonts cuối pane phải là Lora/Lora Italic từ network, không chỉ nhìn tên family trong Computed. Heading hiện dùng weight 400; italic phải dùng file italic, synthesis none. Dùng cả 6 chuỗi: “Giữ một khoảnh khắc”, “Kể ngàn câu chuyện”, “Lối về bình yên”, “Ảnh nghệ thuật”, “Chủ đề”, “Tác giả”.
7. Kiểm tra Unicode trực tiếp phần tử đang chọn trong Console:

   ```js
   const t = $0.textContent;
   ({
     text: t,
     nfc: t === t.normalize("NFC"),
     codepoints: [...t].map(
       (c) => `${c} U+${c.codePointAt(0).toString(16).toUpperCase()}`,
     ),
     combining: [...t].filter((c) => /\p{M}/u.test(c)),
     unusual: [...t].filter((c) =>
       /[\u00a0\u2000-\u200b\u202f\ufeff]/u.test(c),
     ),
     charset: document.characterSet,
   });
   ```

   NFD hợp lệ không đồng nghĩa dữ liệu hỏng. Nếu có lỗi thật, đối chiếu raw response và bản ghi liên quan trước khi xử lý riêng; không đổi collation/encoding hoặc normalize database hàng loạt. Không chèn khoảng trắng để chữa dấu.

8. So sánh **Cốc Cốc và Edge/Chrome trên cùng Windows**, cùng URL, viewport, zoom 100% và cùng display scaling. Thử cửa sổ/profile sạch với extension tắt nếu chỉ Cốc Cốc lỗi; xem CSS bị extension chèn. Ghi browser version, OS, display scale, zoom, screenshot kèm Computed/Rendered Fonts và response font. Kiểm tra desktop và responsive 768/390/320 px, normal/italic, dấu không chồng/cắt, không tràn ngang, tên ảnh/caption/lightbox đọc được. Sau đó kiểm tra tìm kiếm, lọc album/tác giả, mở/đóng ảnh và banner.

## Kiểm thử tái lập

`tests/typography.cjs` chỉ đọc site; các probe được tạo/xóa trong DOM của trình duyệt kiểm thử, không ghi database. Cài Playwright ở thư mục công cụ riêng (đã chạy 1.56.1 với Node 18). Ví dụ Linux:

```sh
LK_PLAYWRIGHT_PATH=/tmp/lk-font-check/node_modules/playwright \
LK_TEST_URL=http://baitapnhom1.local LK_EXPECT_FONT=Lora \
LK_REPORT=/tmp/lk-typography.json node tests/typography.cjs
```

Trên PowerShell đặt `$env:LK_PLAYWRIGHT_PATH`, `$env:LK_TEST_URL`, `$env:LK_EXPECT_FONT='Lora'`, `$env:LK_BROWSER` thành đường dẫn exe Edge/Chrome/Cốc Cốc thực tế, `$env:LK_REPORT` thành file riêng rồi `node tests/typography.cjs`. Bỏ `LK_EXPECT_FONT` khi muốn thu chẩn đoán trước sửa. Có thể đặt `LK_SCREENSHOT_DIR` là thư mục đã tồn tại để lưu ảnh. Test headless không thay thế kiểm tra thủ công profile Cốc Cốc bị lỗi.

Kết quả Ubuntu: 4 viewport 1440/768/390/320; 11 vị trí serif; mỗi viewport 24 probe (6 chuỗi × NFC/NFD × normal/italic); tất cả Rendered Fonts là font web Lora; 2 file font trả 200 `font/woff2` mỗi lần reload; không tràn ngang; lightbox mở/đóng được. Cmap cả hai font chứa đủ 154 codepoint cần cho bảng chữ cái/dấu tiếng Việt (NFC và NFD). Chi tiết máy chạy trong `typography-verification.json`.
