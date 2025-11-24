=== My QR Code Generator ===
Contributors: rongconhg
Tags: qr code, qr generator, short url, url shortener, qr scanner, dynamic qr
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 1.7
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Tạo mã QR động và link rút gọn với tính năng bảo mật thời hạn, thống kê chi tiết và logo tùy chỉnh.

== Description ==

**My QR Code Generator** là plugin WordPress mạnh mẽ giúp bạn tạo mã QR động và link rút gọn một cách dễ dàng. Plugin hỗ trợ tính năng bảo mật độc đáo với mã QR có thời hạn để chống chụp màn hình chia sẻ trái phép.

= Tính năng chính =

* **Tạo mã QR và link rút gọn**: Chuyển đổi URL dài thành mã QR và link ngắn gọn
* **Mã QR có thời hạn**: Đặt thời gian hiệu lực cho mã QR (giây), mã cũ tự động hết hạn
* **Link vĩnh viễn**: Link rút gọn luôn hoạt động, chỉ mã QR có thời hạn
* **Logo/Watermark**: Thêm logo của bạn vào giữa mã QR
* **Thống kê chi tiết**: Theo dõi lượt quét theo ngày, tuần, tháng với biểu đồ trực quan
* **Tùy chỉnh màu sắc**: 6 màu QR đẹp mắt để lựa chọn
* **Tải xuống PNG**: Xuất mã QR chất lượng cao với logo
* **Shortcode linh hoạt**: Hiển thị mã QR động trên bất kỳ trang nào
* **Tự động làm mới**: Mã QR tự động cập nhật theo chu kỳ đã đặt
* **Giao diện tiếng Việt**: Hoàn toàn bằng tiếng Việt, thân thiện với người dùng Việt Nam

= Ứng dụng thực tế =

* **Sự kiện/Hội nghị**: QR check-in có thời hạn, tự động vô hiệu sau sự kiện
* **Khuyến mãi/Flash sale**: QR ưu đãi tự động hết hạn đúng giờ
* **Bảo mật**: Chống chia sẻ QR trái phép, mã cũ không thể quét lại
* **Giáo dục**: QR tài liệu học tập, bài tập có thời hạn nộp
* **Marketing**: Theo dõi hiệu quả chiến dịch với thống kê chi tiết

= Cách sử dụng =

1. **Tạo link rút gọn**: Vào menu "Tạo mã QR", nhập URL và tạo
2. **Quản lý link**: Vào "Danh sách link" để xem tất cả QR đã tạo
3. **Cấu hình động**: Vào "Quản lý link", chọn link, đặt "Giây đổi mã QR" (ví dụ: 60 = 1 phút)
4. **Thêm logo**: Vào "Cài đặt", upload logo 150x150px
5. **Hiển thị trên trang**: Dùng shortcode `[my_dynamic_qr id="POST_ID" refresh="60"]`
6. **Xem thống kê**: Click "Thống kê" để xem biểu đồ lượt quét

= Shortcode =

**Hiển thị mã QR động:**
`[my_dynamic_qr id="123" refresh="60"]`

* `id`: ID của shortlink (bắt buộc)
* `refresh`: Tự động làm mới sau N giây (tùy chọn)

**Tạo form nhập URL:**
`[my_qr_generator]`

= Yêu cầu kỹ thuật =

* WordPress 5.0 trở lên
* PHP 7.4 trở lên
* GD Library (cho xử lý ảnh logo)
* Composer (tùy chọn, cho phpqrcode)

= Bảo mật & Quyền riêng tư =

* Không thu thập dữ liệu cá nhân
* Tất cả dữ liệu lưu trong database WordPress của bạn
* Mã QR được cache 1 giờ để tối ưu hiệu suất
* Token bảo mật dùng wp_salt() của WordPress

== Installation ==

= Cài đặt tự động =

1. Vào **Plugins > Add New** trong WordPress admin
2. Tìm kiếm "My QR Code Generator"
3. Click **Install Now** và **Activate**

= Cài đặt thủ công =

1. Tải file plugin về máy
2. Upload thư mục `my-qr-generator` vào `/wp-content/plugins/`
3. Kích hoạt plugin trong menu **Plugins**

= Cài đặt phpqrcode (tùy chọn) =

Nếu muốn sử dụng tính năng tải PNG server-side:

1. Mở terminal/cmd tại thư mục plugin
2. Chạy: `composer require phpqrcode/phpqrcode`

Hoặc plugin sẽ tự động tìm phpqrcode nếu đã cài.

= Sau khi cài đặt =

1. Vào menu **Tạo mã QR** trên thanh admin
2. Tạo link rút gọn đầu tiên
3. (Tùy chọn) Vào **Cài đặt** để upload logo
4. Sử dụng shortcode để hiển thị QR trên trang

== Frequently Asked Questions ==

= Làm sao để mã QR có thời hạn? =

1. Vào **Danh sách link**, click **Quản lý động**
2. Nhập số giây vào ô **"Giây đổi mã QR"** (ví dụ: 60 = 1 phút)
3. Click **Lưu thay đổi**
4. Mã QR sẽ tự động đổi và mã cũ hết hiệu lực

= Link rút gọn có bị hết hạn không? =

Không. Chỉ mã QR (khi quét) mới có thời hạn. Link thường (click/paste) luôn hoạt động vĩnh viễn.

= Làm sao thêm logo vào QR? =

1. Vào **Tạo mã QR > Cài đặt**
2. Click **Chọn Logo**
3. Upload ảnh (khuyến nghị: vuông 150x150px, PNG trong suốt)
4. Click **Lưu cài đặt**

Logo sẽ tự động xuất hiện ở giữa tất cả QR khi tải PNG.

= Mã QR không hiển thị? =

Kiểm tra:
* QRCode.js đã load chưa (mở Console xem lỗi)
* Cache trình duyệt (Ctrl+F5 để refresh)
* Conflict với plugin khác (tắt các plugin để test)

= Làm sao xem thống kê? =

1. Vào **Danh sách link**
2. Click nút **Thống kê** bên cạnh link
3. Chọn khoảng thời gian: 7 ngày, 30 ngày, 90 ngày

= Shortcode không hoạt động? =

Đảm bảo:
* ID shortlink đúng (xem trong Danh sách link)
* Format: `[my_dynamic_qr id="123"]`
* Plugin đã kích hoạt

= Có thể tùy chỉnh màu QR không? =

Có. Có 6 màu sẵn: Đen, Xanh dương, Đỏ, Xanh lá, Cam, Tím.
Chọn màu khi tạo hoặc tải QR.

= Plugin có tương thích với Gutenberg không? =

Có. Dùng block **Shortcode** và paste `[my_dynamic_qr id="123"]` vào.

= Tôi có thể export dữ liệu không? =

Có. Click **Export CSV** để tải thống kê dạng CSV cho từng link hoặc tất cả.

= Plugin có tính phí không? =

Không. Hoàn toàn miễn phí và mã nguồn mở (GPL2+).

== Screenshots ==

1. Giao diện tạo mã QR và link rút gọn
2. Danh sách link với ID và shortcode
3. Quản lý link với cài đặt thời hạn
4. Trang thống kê với biểu đồ chi tiết
5. Cài đặt logo/watermark
6. Mã QR động với countdown trên frontend
7. QR với logo ở giữa

== Changelog ==

= 1.7 - 2025-11-18 =
* **NEW**: Tính năng mã QR có thời hạn để chống chia sẻ trái phép
* **NEW**: Link vĩnh viễn hoạt động song song với QR có thời hạn
* **NEW**: Token bảo mật với wp_salt() cho mỗi chu kỳ
* **NEW**: Countdown hiển thị thời gian còn lại của mã QR
* **IMPROVED**: Tối ưu logic thời hạn, ẩn countdown khi không cần
* **IMPROVED**: Script copy shortcode dùng Clipboard API hiện đại
* **IMPROVED**: QR preview trong admin hoạt động ổn định hơn
* **FIXED**: Nút download PNG hoạt động đúng với canvas và img
* **FIXED**: Media uploader cho logo settings
* **FIXED**: Loại bỏ code rác từ quy tắc động cũ

= 1.6 - 2024 =
* **NEW**: Logo/Watermark tùy chỉnh ở giữa QR
* **NEW**: Shortcode `[my_dynamic_qr]` với auto-refresh
* **NEW**: Thống kê chi tiết với biểu đồ Chart.js
* **NEW**: Export CSV theo khoảng thời gian
* **IMPROVED**: Cache QR PNG với transient
* **IMPROVED**: Hỗ trợ nhiều màu QR
* **IMPROVED**: Giao diện admin hoàn toàn tiếng Việt

= 1.5 =
* **NEW**: Tính năng shortlink động
* **NEW**: Quản lý nhiều link
* **IMPROVED**: Tối ưu database queries

= 1.0 =
* Phát hành ban đầu
* Tạo QR code cơ bản
* Link rút gọn

== Upgrade Notice ==

= 1.7 =
Bản cập nhật quan trọng với tính năng bảo mật QR có thời hạn. Khuyến nghị nâng cấp để chống chia sẻ mã QR trái phép.

= 1.6 =
Thêm logo/watermark và thống kê chi tiết. Backup trước khi nâng cấp.

== Additional Info ==

**Hỗ trợ**
* Website: https://rongcon.net
* Email: support@rongcon.net

**Đóng góp**
Plugin mã nguồn mở, chào đón mọi đóng góp!

**Báo lỗi**
Nếu phát hiện lỗi, vui lòng liên hệ hoặc tạo issue trên repository.

**Đánh giá**
Nếu plugin hữu ích, hãy để lại đánh giá 5 sao ⭐⭐⭐⭐⭐ để ủng hộ!
