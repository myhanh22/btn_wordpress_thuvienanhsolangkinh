# WBS đề xuất — không xác nhận đóng góp

TV1–TV5 để trống danh tính. Mục tiêu 20% mỗi người; không phải số giờ đã đo. Các đầu ra hiện có không chứng minh cá nhân được đề xuất đã làm. Mỗi người cần thay đổi/test/tài liệu thực tế và commit đúng tác giả; không rewrite lịch sử/tạo commit giả.

| Mã | Đầu việc | Phụ trách đề xuất | Đầu ra | Phụ thuộc | Nghiệm thu | Trạng thái |
|---|---|---|---|---|---|---|
| 1.1 | Môi trường, inventory | TV1 | Phiên bản, đường dẫn | Không | Đúng repo/site, phân biệt runtime | Có đầu ra* |
| 1.2 | SQL sạch và restore | TV1 | SQL, manifest, README | 1.1 | Clone thật, import riêng, không secret | Có đầu ra* |
| 1.3 | Kernel và khung LaTeX | TV1 | Phần kernel, main.tex | 1.1 | Citation, PDF build được | Có đầu ra* |
| 2.1 | Giao diện, banner | TV2 | Child theme, ảnh UI | 1.1 | Giữ Astra, không thay banner tự động | Có đầu ra* |
| 2.2 | Album, filter, search, lightbox | TV2 | JS/CSS, test UI | 2.1 | Không dấu, bàn phím, mobile | Có đầu ra* |
| 2.3 | So sánh phân quyền OS | TV2 | Nội dung Phần 1 | 1.1 | Giải thích file/thư mục, ACL | Có đầu ra* |
| 3.1 | Upload và pending | TV3 | Handler, test request | 2.1 | Hai lần gửi, MIME/size/cleanup | Có đầu ra* |
| 3.2 | Watermark/WebP | TV3 | Snippet, ảnh Network | 3.1 | Mark còn nguyên, MIME/fallback đúng | Có đầu ra* |
| 3.3 | Quản lý bản vá | TV3 | Nội dung Phần 1 | 1.1 | OS khác runtime/WordPress | Có đầu ra* |
| 4.1 | Hardening/account | TV4 | Module, config, ảnh | 1.1 | Login/lockout/XML-RPC kiểm chứng | Có đầu ra* |
| 4.2 | Lập trình an toàn | TV4 | Review code/capability | 3.1 | Nonce + quyền, Author không publish | Có đầu ra* |
| 4.3 | GPL/thương mại | TV4 | Nội dung license | 1.1 | Không đánh đồng Ubuntu/mã/ảnh | Có đầu ra* |
| 5.1 | WPScan/sanitizer | TV5 | Log sạch, test script | 4.1 | Không giả exit/API/WAF, che header | Có đầu ra* |
| 5.2 | Hồi quy tích hợp | TV5 | Test và đối chiếu | 2.2,3.2,4.2 | Passed/unrun tách rõ, giữ dữ liệu | Có đầu ra* |
| 5.3 | Auditability, biên tập | TV5 | Citation, license, PDF QA | 1.3--5.2 | Không lỗi tham chiếu, ảnh, font hoặc tràn bảng | Có đầu ra* |

*Có đầu ra trong bộ bàn giao; chưa xác nhận người thực hiện. TV1 phụ trách kernel/khung LaTeX, TV2 phân quyền, TV3 patch management, TV4 GPL/thương mại, TV5 auditability/biên tập. Thành viên tự viết phần mình và review phụ thuộc.