=== Universal Sports Manager ===
Contributors: mralb
Donate link: https://example.com
Tags: sports, management, attendance, coaching, students
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Comprehensive sports center management — students, coaches, courses, attendance, scheduling, payments, and reporting.

== Description ==

**Universal Sports Manager (USM)** is an all-in-one WordPress plugin for managing sports academies, training centers, and fitness clubs.

Designed for the Vietnamese market, with full Vietnamese UI and VietQR payment integration.

= Key Features =

* **Student Management** — Full CRUD with search by name/phone, CSV export
* **Coach Management** — Track coaches, specialties, salary rates, auto salary calculation
* **Course & Package Management** — Create courses, flexible packages (session-based or time-based)
* **Enrollment System** — Enroll students, track payments, pause/resume/renew
* **Payment History** — Record payments (cash/transfer/QR), auto-update payment status
* **Printable Receipts** — Professional receipts with payment history, ready for PDF printing
* **Smart Scheduling** — Weekly + Monthly calendar view, batch session generator
* **Attendance Tracking** — Per-session check-in with automatic session deduction
* **Mobile Check-in** — Touch-friendly interface for coaches on phones
* **Coach Salary Calculator** — Auto-calculate based on sessions taught
* **Visual Reports** — Revenue charts, attendance rates, enrollment analytics, CSV export
* **Parent Dashboard** — Parents view children's progress, schedule, attendance history
* **VietQR Payment** — QR code payment integration for tuition collection
* **Focus Mode** — Hide WordPress clutter, focus on USM only
* **Role-Based UI** — Clean interface per role (Admin/Coach/Parent), custom login page
* **Custom Branding** — USM-branded login page, admin bar, and footer

= Vietnamese UI =

All labels, menus, and messages are in Vietnamese. Perfect for sports centers in Vietnam.

= Roles & Capabilities =

* **Admin** — Full access to all features
* **Coach** — Check-in, attendance, schedule only
* **Parent** — View-only dashboard for their children

= Requirements =

* WordPress 6.0 or higher
* PHP 7.4 or higher
* MySQL 5.7 / MariaDB 10.3 or higher

== Installation ==

1. Upload the `usm-plugin` folder to `/wp-content/plugins/`
2. Activate via the **Plugins** menu in WordPress
3. Navigate to **USM → Cài đặt** to configure your center name and bank details
4. Add facilities, coaches, courses, packages, and students
5. Start enrolling students and tracking attendance!

== Frequently Asked Questions ==

= Does this plugin work with any theme? =

Yes! USM operates entirely within the WordPress admin area and is theme-independent.

= Can I export data? =

Yes, you can export students, coaches, and enrollments to CSV files. Reports can be printed to PDF via the browser.

= Is it mobile friendly? =

Yes! The dedicated Check-in page is designed for coaches using phones at the training ground, with large touch-friendly buttons.

= Can parents see their children's progress? =

Yes, parents with the `usm_parent` role see a dedicated dashboard showing enrollment details, schedules, and attendance history.

= Does it support Vietnamese payment methods? =

Yes, VietQR integration generates dynamic QR codes for bank transfers. Supports all major Vietnamese banks.

== Screenshots ==

1. Dashboard with KPI stats, alerts, and revenue charts
2. Weekly + Monthly schedule calendar
3. Mobile check-in page for coaches
4. Payment history and printable receipts
5. Visual reports with revenue and attendance charts
6. Student management with search and CSV export
7. Professional custom login page
8. Parent dashboard with enrollment details

== Changelog ==

= 1.1.1 =
* **Phone Validation** — Relaxed to accept 10 or 11 digits (removed strict prefix check)
* **Notice HTML Fix** — Success messages now render links properly instead of raw HTML

= 1.1.0 =
* **Menu Reorder** — Logical workflow grouping: Dashboard → People → Operations → Finance → System
* **Menu Separators** — Visual dividers between menu groups for clarity
* **Consolidated Setup** — Courses/Packages/Facilities merged into a single tabbed "🛠️ Thiết lập" page
* **2-Step Enrollment** — Select Course first, then filtered Packages only
* **Enrollment → Schedule Link** — After enrollment, direct link to create schedule for the course
* **Breadcrumbs** — Navigation trail on add/edit forms (e.g., "Học viên › Sửa: Name")
* **Delete Confirmation** — JavaScript confirm dialog on all delete actions
* **Hidden Page Fix** — Packages/Facilities/Check-in accessible without "permission denied" error
* **Sidebar Menu Fix** — Menu stays expanded on hidden pages with correct item highlighted
* **Compact Actions** — Pill-button style for table action links
* **Zebra Striping** — Alternating row colors for better table readability
* **Empty State UI** — Styled empty states with icon when tables have no data
* **Row Count Badge** — "📊 Hiển thị X dòng" below tables
* **Mobile Check-in Link** — Quick access button on Attendance page header

= 1.0.0 =
* Initial release
* Student, Coach, Course, Package CRUD with search & filters
* Enrollment system with payment tracking & history
* Attendance with session deduction (transaction-safe)
* Mobile check-in page for coaches
* Batch session generator
* Weekly + Monthly calendar views
* Visual reporting with Chart.js (revenue, students, enrollment status)
* Coach salary calculator
* VietQR payment integration
* Parent dashboard with schedule & attendance
* Session notes for coaches
* Printable receipts (PDF via browser print)
* CSV export for Students, Coaches, Enrollments
* Focus Mode
* Role-based UI (Admin/Coach/Parent)
* Custom login page with center branding
* Zalo ZNS notification framework
* 8+ index.php security files

== Upgrade Notice ==

= 1.1.1 =
Fix phone validation and notice HTML rendering.

= 1.1.0 =
Improved admin menu organization, 2-step enrollment workflow, breadcrumbs, and multiple UX/UI enhancements.

= 1.0.0 =
Initial release of Universal Sports Manager.
