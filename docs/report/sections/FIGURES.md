# Nguồn và nội dung cần quan sát trong hình

| File | Nguồn | Nội dung quan sát |
|---|---|---|
| home-desktop.png, home-mobile.png | LocalWP thật, chụp mới 27/10/2026 | Banner cố định, thiết kế, responsive |
| albums.png, search.png, lightbox.png | LocalWP thật, thao tác trong Edge | Album, tìm không dấu, ảnh lớn/đóng/chuyển |
| upload-guest.png | LocalWP thật, không cookie đăng nhập | Chặn khách, liên kết quay về form |
| upload-form.png, upload-pending.png | LocalWP thật, tài khoản dùng một lần | Taxonomy, form, thành công sau lưu |
| moderation.png | LocalWP thật, Editor dùng một lần | Các bài thử pending/published; không dữ liệu thật |
| watermark-new.png | Crop derivative WebP thật của ảnh mới thử | Dấu © Lăng Kính · Nhóm 1 còn trên WebP |
| network-webp.png, network-fallback.png | DevTools Edge gắn trực tiếp target LocalWP | URL JPG giữ nguyên, MIME, Accept, Vary, 200 |
| hardening-current.png | Module riêng trên LocalWP, chụp mới | Trạng thái chính sách; đi cùng HTTP test |
| login-lockout.png | docs/security/evidence, ảnh thật lịch sử | Khóa theo ngưỡng; username đã che |
| restore-demo.png | Môi trường restore DB thứ hai | Bản import/restore riêng; không thay bằng chứng LocalWP |

Mỗi hình đã được nhúng trong README báo cáo và gắn caption/label trong LaTeX. Không dùng ảnh mẫu làm giả kết quả công cụ. Ảnh UI có ảnh mẫu trong repo, quyền từng ảnh chưa được xác nhận; xem THIRD-PARTY-NOTICES.md.

Sơ đồ `architecture.tex` là source TikZ tự biên soạn; `architecture.png` là bản render dùng trong DOCX, không phải screenshot. PDF chính lấy TikZ trực tiếp. Không coi mũi tên trong sơ đồ là kết quả đo mạng.

![Sơ đồ thiết kế](figures/architecture.png)