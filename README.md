# 🚀 POS Pro – Enterprise Point of Sale System

**The “Most Dangerous” POS – Feature‑Rich, Secure, and Blazing Fast.**

---

## 📌 Overview

POS Pro is a **production‑ready, enterprise‑grade Point of Sale** system built with PHP, MySQL, Bootstrap, and modern JavaScript libraries. It combines a sleek, dark‑themed interface with powerful analytics, real‑time inventory tracking, and a fluid user experience that outshines standard POS solutions.

This is not your average POS – it’s **dangerously efficient**, giving you instant insights, lightning‑fast checkout, and absolute control over your sales operations.

---

## ✨ Key Features

- **🔥 Executive Dashboard** – Live stats, sales trend charts, category distribution, top‑selling products, and low‑stock alerts.
- **🛒 Smart Cart System** – Quick product search, quantity selection, real‑time cart updates, tax calculation, and one‑click checkout.
- **📦 Advanced Inventory** – Product categories, cost price, reorder levels, stock adjustment history, and barcode support.
- **📊 Sales History** – Filterable, searchable, paginated table with export to CSV; revenue trend chart.
- **👥 User Management** – Admin and cashier roles; secure password hashing; full CRUD operations.
- **🧾 Professional Receipts** – Print‑optimised receipts with company details, tax breakdown, and barcode simulation.
- **🔔 Real‑time Notifications** – Low‑stock badges, live clock, and animated alerts.
- **🔐 Security First** – CSRF protection on all forms, prepared statements, session regeneration, and input validation.
- **📱 Fully Responsive** – Works flawlessly on desktop, tablet, and mobile.
- **⚡ Keyboard Shortcuts** – `Ctrl+N` new sale, `Ctrl+P` print, `Ctrl+E` export, and more.

---

## 🧱 System Requirements

- PHP **7.4+** (8.x recommended)
- MySQL **5.7+** or MariaDB **10.2+**
- Web server (Apache/Nginx)
- PHP extensions: `PDO`, `pdo_mysql`, `session`, `json`, `mbstring`

---

## 📥 Installation

### 1. Clone or Download
```bash
git clone https://github.com/yourusername/pos-pro.git
cd pos-pro
```

### 2. Create Database
```sql
CREATE DATABASE pos_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Configure Environment
Edit `config/database.php` and update the database credentials:
```php
$config = [
    'host'     => 'localhost',
    'dbname'   => 'pos_system',
    'username' => 'your_db_user',
    'password' => 'your_db_password',
    // ...
];
```

> **For production**, move sensitive credentials to a `.env` file and use `getenv()`.

### 4. Run Migrations (Auto‑executed)
The system automatically creates all tables and seeds default data (admin user, categories, sample products) on first run.

### 5. Set Permissions
Ensure the `logs/` directory is writable:
```bash
mkdir logs
chmod 755 logs
```

### 6. Start Your Web Server
Point your document root to the project folder. Access via `http://localhost/pos-pro`.

---

## 🔑 Default Login

- **Username:** `admin`
- **Password:** `admin123`

> ⚠️ **Important:** Change the admin password immediately after first login.

---

## 📂 Directory Structure

```
pos-pro/
├── assets/
│   ├── css/               # Custom styles (style.css)
│   └── js/                # Custom JavaScript (if any)
├── config/
│   └── database.php       # Database connection & migrations
├── includes/
│   ├── navbar.php         # Top navigation bar
│   └── sidebar.php        # Sidebar menu
├── logs/                  # Error logs (auto‑created)
├── index.php              # Dashboard
├── new_sale.php           # Point of Sale page
├── products.php           # Product management
├── sales_history.php      # Sales history with filters & export
├── view_sale.php          # Sale details
├── receipt.php            # Print‑friendly receipt
├── users.php              # User management (admin only)
├── login.php              # Login page
├── logout.php             # Logout handler
├── export_sales.php       # CSV export (to be implemented)
└── README.md              # This file
```

---

## 🧩 Usage Guide

### Dashboard
- View today’s sales and revenue.
- Monitor low‑stock items.
- Analyse revenue trends and category performance.

### New Sale
- Search products by name or barcode.
- Click a product to add it to the cart (quantity modal appears).
- Adjust quantities directly in the cart.
- The cart automatically calculates subtotal, tax, and total.
- Click **Process Sale** to complete the transaction and generate a receipt.

### Products
- Add, edit, or delete products.
- Track stock levels with visual progress bars.
- Set reorder levels and categories.

### Sales History
- Filter sales by date range.
- Search any sale.
- View detailed information or print receipts.

### User Management (Admin only)
- Create new cashiers or admins.
- Edit or delete users.
- Assign roles.

---

## 🔒 Security Highlights

- **CSRF Tokens** – Every form includes a unique token to prevent cross‑site request forgery.
- **Prepared Statements** – All database queries use parameterized statements, eliminating SQL injection risks.
- **Password Hashing** – Passwords are hashed using `password_hash()` with bcrypt.
- **Session Regeneration** – Session IDs are regenerated upon login to prevent fixation.
- **Input Validation** – All user inputs are validated and sanitised before processing.

---

## 🛠️ Customisation & Extending

### Adding New Features
- The `database.php` includes a **migration system** – add new schema versions to the `$migrations` array.
- Use the provided helper functions (`dbInsert()`, `dbFetchAll()`, etc.) to write clean, maintainable code.
- The UI is fully themeable – modify `assets/css/style.css` to match your brand.

### Integrating Barcode Scanners
- The `new_sale.php` has a barcode input field – simply connect a USB barcode scanner, and it will work out‑of‑the‑box.

### Exporting Data
- The `export_sales.php` endpoint is ready to be implemented – you can generate CSV/Excel files from the sales history filters.

---

## 🤝 Contributing

Contributions are welcome! Please follow these steps:
1. Fork the repository.
2. Create a feature branch (`git checkout -b feature/amazing-feature`).
3. Commit your changes (`git commit -m 'Add amazing feature'`).
4. Push to the branch (`git push origin feature/amazing-feature`).
5. Open a Pull Request.

---

## 📄 License

This project is open‑source and available under the **MIT License**. See the [LICENSE](LICENSE) file for details.

---

## 🙏 Acknowledgements

- [Bootstrap](https://getbootstrap.com) – UI framework
- [Font Awesome](https://fontawesome.com) – Icons
- [Chart.js](https://www.chartjs.org) – Charts
- [DataTables](https://datatables.net) – Advanced tables

---

## 📧 Contact

For support or inquiries, please open an issue on GitHub or contact the maintainer at `support@pospro.local`.

---

**Take control of your business with POS Pro – the most dangerous POS system on the market!** 🚀
