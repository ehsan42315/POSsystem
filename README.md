# Web-Based POS System for Small Retail Shop

A complete Point of Sale (POS) system built with PHP, MySQL, Bootstrap, and JavaScript for small retail shops.

## Features

 **Core Features Implemented:**
- Product Management (CRUD operations)
- Sales/Billing Module with cart functionality
- Stock Update after sales
- Sales History with detailed reports
- Login System with roles (Admin/Cashier)
- Receipt generation and printing
- Dashboard with sales summary
- User management (Admin only)

 **Additional Features:**
- Product search and filtering
- Low stock alerts
- Responsive design
- Print receipts
- Sales statistics
- Date-based filtering
- Pagination for sales history

## Tech Stack

- **Frontend:** HTML5, CSS3, Bootstrap 5, JavaScript
- **Backend:** PHP 7.4+
- **Database:** MySQL
- **Icons:** Font Awesome 6

## Database Schema

### Tables Created:
1. **users** - User authentication and roles
2. **products** - Product inventory management
3. **sales** - Sales transaction records
4. **sale_items** - Individual items in each sale

## Installation & Setup

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx) or XAMPP

### Option 1: Using XAMPP (Recommended for Windows)

1. **Download and Install XAMPP:**
   - Download from: https://www.apachefriends.org/
   - Install XAMPP with Apache, MySQL, and PHP

2. **Setup the Project:**
   ```bash
   # Copy project files to XAMPP htdocs
   cp -r /path/to/pos-system C:/xampp/htdocs/pos-system
   ```

3. **Start Services:**
   - Open XAMPP Control Panel
   - Start Apache and MySQL services

4. **Create Database:**
   - Open http://localhost/phpmyadmin
   - Create a new database named `pos_system`
   - The tables will be created automatically when you first run the application

5. **Access the Application:**
   - Open: http://localhost/pos-system
   - Default login: `admin` / `admin123`

### Option 2: Using PHP Built-in Server

1. **Install PHP and MySQL separately**

2. **Setup Database:**
   - Create MySQL database named `pos_system`
   - Update database credentials in `config/database.php` if needed

3. **Run the Application:**
   ```bash
   cd /path/to/pos-system
   php -S localhost:8000
   ```

4. **Access:** http://localhost:8000

## Default Login Credentials

- **Username:** admin
- **Password:** admin123
- **Role:** Administrator

## File Structure

```
POSsystem/
├── assets/
│   └── css/
│       └── style.css          # Custom styles
├── config/
│   └── database.php           # Database configuration
├── includes/
│   ├── navbar.php             # Navigation bar
│   └── sidebar.php            # Sidebar navigation
├── index.php                  # Dashboard
├── login.php                  # Login page
├── logout.php                 # Logout handler
├── products.php               # Product management
├── new_sale.php               # New sale/checkout
├── receipt.php                # Receipt display
├── sales_history.php          # Sales history
├── view_sale.php              # Sale details
├── users.php                  # User management (Admin)
└── README.md                  # This file
```

## Usage Guide

### For Administrators:
1. **Dashboard:** View sales summary and quick stats
2. **Products:** Add, edit, delete products and manage inventory
3. **New Sale:** Process customer purchases
4. **Sales History:** View all transactions with filtering
5. **Users:** Manage system users and roles

### For Cashiers:
1. **Dashboard:** View today's sales summary
2. **Products:** View product list (read-only)
3. **New Sale:** Process customer purchases
4. **Sales History:** View sales transactions

## Key Features Explained

### Product Management
- Add new products with name, price, and stock quantity
- Edit existing products
- Delete products (with confirmation)
- Stock level indicators (In Stock, Low Stock, Out of Stock)
- Search functionality

### Sales Processing
- Add products to cart
- Adjust quantities
- Calculate totals automatically
- Process payment
- Generate receipts
- Update stock levels automatically

### Sales History
- View all past transactions
- Filter by date range
- Pagination for large datasets
- View detailed sale information
- Print receipts

### User Management (Admin Only)
- Add new users (Admin/Cashier roles)
- Edit user details
- Delete users
- Password management

## Security Features

- Password hashing using PHP's `password_hash()`
- Session-based authentication
- Role-based access control
- SQL injection prevention using prepared statements
- XSS protection with `htmlspecialchars()`

## Customization

### Database Configuration
Edit `config/database.php` to change database settings:
```php
$host = 'localhost';
$dbname = 'pos_system';
$username = 'root';
$password = '';
```

### Styling
Custom styles are in `assets/css/style.css`. The system uses Bootstrap 5 for responsive design.

## Troubleshooting

### Common Issues:

1. **Database Connection Error:**
   - Check MySQL service is running
   - Verify database credentials in `config/database.php`
   - Ensure database `pos_system` exists

2. **Permission Denied:**
   - Check file permissions
   - Ensure web server has read/write access

3. **PHP Errors:**
   - Enable error reporting in PHP
   - Check PHP version compatibility

## License

This project is open source and is on no license

## Support

For issues and questions:
1. Check the troubleshooting section
2. Verify all prerequisites are met
3. Ensure database is properly configured

