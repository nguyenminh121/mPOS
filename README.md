# mPOS - Hệ thống Quản lý Bán hàng & Kho hàng Đa tiền thuê (SaaS)

mPOS (Onivaco) là một hệ thống phần mềm dạng dịch vụ (SaaS) toàn diện hỗ trợ quản lý bán hàng (Point of Sale), quản lý kho hàng (WMS) và tài chính kế toán cho nhiều cửa hàng/chuỗi cửa hàng. Hệ thống được thiết kế theo kiến trúc **Multi-Tenant** (Đa tiền thuê) và **Modular Monolith** nhằm đảm bảo hiệu suất, khả năng mở rộng và dễ dàng bảo trì.

## 🚀 Công nghệ sử dụng (Tech Stack)

### Backend (Core API & Logic)
- **Framework:** Laravel 12 (PHP ^8.2)
- **Multi-Tenancy:** `stancl/tenancy` (Quản lý dữ liệu độc lập cho từng khách hàng/Tenant)
- **Authentication & Authorization:** Laravel Sanctum / Fortify & `spatie/laravel-permission` (RBAC)
- **Database ORM:** Eloquent ORM
- **Testing:** Pest PHP
- **Code Quality:** Laravel Pint, PHPStan

### Frontend (Web SPA)
- **Framework:** Vue 3 (Composition API) với Inertia.js
- **Ngôn ngữ:** TypeScript
- **Styling:** Tailwind CSS v4, `tailwind-merge`, `clsx`
- **UI Components:** Reka UI (Radix Vue port), `lucide-vue-next` (Icons)
- **Build Tool:** Vite
- **Code Quality:** ESLint, Prettier, Vue-tsc

## ✨ Tính năng nổi bật (Core Modules)

Hệ thống được chia thành nhiều module chức năng (Domain-Driven):

1. **Quản trị Multi-Tenant (SaaS)**
   - Quản lý không gian làm việc (Workspace/Tenant) độc lập.
   - Quản lý gói cước (Plans), đăng ký (Subscriptions) và giới hạn tính năng (Feature Toggles).
   - Mời người dùng vào Tenant (TenantInvitation).

2. **Quản lý Kho hàng & Tồn kho (WMS - Warehouse Management)**
   - Quản lý đa kho (Warehouse), vị trí lưu trữ trong kho (Storage Location/Warehouse Location).
   - Kiểm kê kho (Stock Take), luân chuyển kho (Transfer Order), nhập/xuất kho (Import Receipt).
   - Quản lý lô hàng (Stock Batch), định giá hàng tồn kho (Inventory Costing).
   - Advanced Mode cho các nghiệp vụ kho phức tạp.

3. **Điểm bán hàng (POS) & Xử lý đơn hàng**
   - Giao diện bán hàng tại quầy tối ưu, hỗ trợ offline đồng bộ sau (SyncConflict, SyncLog).
   - Quản lý ca làm việc của nhân viên (Pos Shift).
   - Xử lý đơn hàng (Order), Đơn trả hàng (Return Order).
   - Hỗ trợ xuất Hóa đơn điện tử (E-Invoice Batch/Detail).
   - Áp dụng chương trình khuyến mãi (Promotion), tính thuế (TaxConfig).

4. **Quản lý Sản phẩm (Catalog)**
   - Quản lý sản phẩm đa dạng (Product, Product Variant).
   - Quản lý thuộc tính sản phẩm (Attribute), danh mục (Category).
   - Quản lý đơn vị tính (UOM), quy đổi đơn vị.
   - Quản lý bảng giá linh hoạt (Price List, Surcharge Catalog).

5. **Quản lý Đối tác & Khách hàng (CRM/SRM)**
   - Quản lý Khách hàng (Customer), nhóm khách hàng.
   - Quản lý Nhà cung cấp (Supplier) và đơn đặt hàng nhà cung cấp (Purchase Order).

6. **Tài chính & Kế toán (Financial)**
   - Quản lý Công nợ phải thu (Account Receivable) & Công nợ phải trả (Account Payable).
   - Quản lý các khoản thu chi, sổ quỹ (Payment, Business Expense).
   - Hỗ trợ nhiều phương thức thanh toán.

7. **Hệ thống API & Tích hợp (Integrations)**
   - Cung cấp API Key và Webhook để tích hợp ứng dụng bên thứ 3 hoặc Mobile App (Flutter).
   - Lưu vết kiểm toán toàn bộ hệ thống (Audit Logs).

## 🛠 Hướng dẫn Cài đặt & Chạy dự án (Local Development)

Dự án được cấu hình sẵn các lệnh tự động trong `composer.json`.

### Yêu cầu môi trường
- PHP >= 8.2
- Node.js >= 20.x
- Composer
- Database: MySQL / PostgreSQL / SQLite

### Các bước cài đặt

1. **Clone dự án về máy:**
   ```bash
   git clone <repo-url> mPOS
   cd mPOS
   ```

2. **Cài đặt tự động (Khuyên dùng):**
   Chạy lệnh dưới đây để cài đặt toàn bộ dependencies (PHP & Node), copy file `.env`, tạo key, migrate database và build frontend.
   ```bash
   composer setup
   ```

3. **Cấu hình CSDL:**
   Mở file `.env` và đảm bảo các thông số kết nối Database (MySQL/PostgreSQL) hoặc tạo file `database/database.sqlite` (Mặc định lệnh setup sẽ xử lý phần này nếu dùng SQLite).

4. **Khởi chạy Server:**
   Để chạy đồng thời cả Backend (Laravel Serve), Queue Worker và Frontend (Vite) trong môi trường dev, sử dụng lệnh:
   ```bash
   composer dev
   ```

   *(Nếu muốn chạy cả SSR cho Frontend: `composer dev:ssr`)*

5. **Truy cập:**
   Mở trình duyệt và truy cập vào `http://localhost:8000`.

## 🧪 Kiểm thử và Format Code

- **Kiểm tra Code Style (Linter/Formatter):**
  ```bash
  composer lint        # Format PHP (Pint)
  npm run format       # Format Frontend (Prettier)
  npm run lint         # ESLint check
  ```

- **Chạy Unit/Feature Tests:**
  ```bash
  composer test
  ```

## 📜 Kiến trúc Code (Quy ước)

- **Domain-driven / Modular:** Code backend nằm trong thư mục `app/Domain`, `app/Models`, và `app/Http`. Mỗi tính năng (Sản phẩm, Đơn hàng, Tenant) có model và logic riêng biệt.
- **Vue & Inertia:** Tất cả views nằm ở thư mục `resources/js/pages/`. Components dùng chung nằm ở `resources/js/components/`.
- **Tenancy:** Chú ý các tác vụ liên quan đến dữ liệu người dùng phải được thực thi trong context của Tenant thông qua package `stancl/tenancy`.

---
*Dự án mPOS - Xây dựng và phát triển trên nền tảng công nghệ hiện đại, hướng tới trải nghiệm mượt mà và bảo mật cho người dùng SaaS.*
