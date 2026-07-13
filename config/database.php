<?php
/**
 * ============================================
 * POS SYSTEM - DATABASE LAYER
 * Enterprise‑Grade | Secure | High‑Performance
 * ============================================
 */

// ---------- Configuration (move to .env in production) ----------
$config = [
    'host'     => 'localhost',
    'dbname'   => 'pos_system',
    'username' => 'root',
    'password' => '',
    'charset'  => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'timezone' => 'UTC',
    'error_log' => __DIR__ . '/../logs/db_errors.log', // ensure logs dir exists
];

// ---------- PDO Connection ----------
try {
    $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false, // prevents SQL injection via emulation
        PDO::ATTR_PERSISTENT => false, // set true if you need persistent connections
    ]);

    // Set timezone
    $pdo->exec("SET time_zone = '{$config['timezone']}'");

    // Set collation
    $pdo->exec("SET NAMES {$config['charset']} COLLATE {$config['collation']}");

} catch (PDOException $e) {
    // Log error to file (if logs dir exists) else fallback to die
    $logFile = $config['error_log'];
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    error_log("[" . date('Y-m-d H:i:s') . "] DB Connection failed: " . $e->getMessage() . "\n", 3, $logFile);
    die("Database connection failed. Please check the logs.");
}

// ---------- Database Schema & Migration ----------
function runMigrations($pdo) {
    // Create logs directory if not exists
    $logDir = dirname($GLOBALS['config']['error_log']);
    if (!is_dir($logDir)) mkdir($logDir, 0755, true);

    try {
        // 1. Create migrations table to track schema versions
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                version VARCHAR(50) NOT NULL UNIQUE,
                applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 2. Define migrations (version => SQL)
        $migrations = [
            '1.0.0' => "
                CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(50) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    role ENUM('admin', 'cashier') DEFAULT 'cashier',
                    full_name VARCHAR(100),
                    email VARCHAR(100),
                    last_login DATETIME,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_username (username)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS categories (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uk_name (name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS products (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    category_id INT,
                    name VARCHAR(255) NOT NULL,
                    description TEXT,
                    price DECIMAL(10,2) NOT NULL,
                    cost_price DECIMAL(10,2) DEFAULT 0.00,
                    quantity INT NOT NULL DEFAULT 0,
                    reorder_level INT DEFAULT 5,
                    barcode VARCHAR(50) UNIQUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
                    INDEX idx_category (category_id),
                    INDEX idx_name (name),
                    INDEX idx_barcode (barcode)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS customers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    phone VARCHAR(20),
                    email VARCHAR(100),
                    address TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_phone (phone),
                    INDEX idx_email (email)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS suppliers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    phone VARCHAR(20),
                    email VARCHAR(100),
                    address TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_name (name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS sales (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    sale_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                    total_amount DECIMAL(10,2) NOT NULL,
                    discount DECIMAL(10,2) DEFAULT 0.00,
                    tax DECIMAL(10,2) DEFAULT 0.00,
                    payment_method ENUM('cash', 'card', 'mobile', 'credit') DEFAULT 'cash',
                    customer_id INT,
                    user_id INT,
                    notes TEXT,
                    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                    INDEX idx_sale_date (sale_date),
                    INDEX idx_user (user_id),
                    INDEX idx_customer (customer_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS sale_items (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    sale_id INT NOT NULL,
                    product_id INT NOT NULL,
                    quantity INT NOT NULL,
                    price DECIMAL(10,2) NOT NULL,
                    discount DECIMAL(10,2) DEFAULT 0.00,
                    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
                    FOREIGN KEY (product_id) REFERENCES products(id),
                    INDEX idx_sale_product (sale_id, product_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS inventory_adjustments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    product_id INT NOT NULL,
                    adjustment_type ENUM('add', 'subtract', 'correct') NOT NULL,
                    quantity INT NOT NULL,
                    reason TEXT,
                    user_id INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                    INDEX idx_product (product_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS returns (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    sale_id INT NOT NULL,
                    product_id INT NOT NULL,
                    quantity INT NOT NULL,
                    refund_amount DECIMAL(10,2) NOT NULL,
                    reason TEXT,
                    returned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    user_id INT,
                    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
                    FOREIGN KEY (product_id) REFERENCES products(id),
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                    INDEX idx_sale (sale_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ",
            // You can add future migrations here with new versions
            // '1.1.0' => "ALTER TABLE products ADD COLUMN weight DECIMAL(8,2);",
        ];

        // 3. Apply pending migrations
        $applied = $pdo->query("SELECT version FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($migrations as $version => $sql) {
            if (!in_array($version, $applied)) {
                $pdo->exec($sql);
                $stmt = $pdo->prepare("INSERT INTO migrations (version) VALUES (?)");
                $stmt->execute([$version]);
                error_log("Migration applied: $version");
            }
        }

        // 4. Seed default data if tables are empty
        seedDefaultData($pdo);

    } catch (PDOException $e) {
        error_log("Migration error: " . $e->getMessage());
        die("Database migration failed. Check logs.");
    }
}

// ---------- Seed Default Data ----------
function seedDefaultData($pdo) {
    // Create default admin user if not exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $hashed = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, full_name, email) VALUES ('admin', ?, 'admin', 'Administrator', 'admin@pos.local')");
        $stmt->execute([$hashed]);
    }

    // Seed categories if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM categories");
    if ($stmt->fetchColumn() == 0) {
        $categories = ['Beverages', 'Bakery', 'Dairy', 'Meat', 'Produce', 'Snacks', 'Household', 'Personal Care'];
        $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
        foreach ($categories as $cat) {
            $stmt->execute([$cat]);
        }
    }

    // Seed products if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    if ($stmt->fetchColumn() == 0) {
        // Fetch category IDs (use names as fallback)
        $catMap = [];
        $rows = $pdo->query("SELECT id, name FROM categories")->fetchAll();
        foreach ($rows as $row) {
            $catMap[$row['name']] = $row['id'];
        }

        $products = [
            ['Coca Cola 500ml', 1.50, 100, 'Beverages'],
            ['Bread Loaf', 2.25, 50, 'Bakery'],
            ['Milk 1L', 3.00, 30, 'Dairy'],
            ['Eggs (12 pack)', 4.50, 25, 'Dairy'],
            ['Bananas (1kg)', 2.80, 40, 'Produce'],
            ['Rice (5kg)', 12.00, 20, 'Household'],
            ['Chicken Breast (1kg)', 8.50, 15, 'Meat'],
            ['Tomatoes (1kg)', 3.20, 35, 'Produce'],
            ['Potato Chips', 2.75, 60, 'Snacks'],
            ['Orange Juice 1L', 4.25, 25, 'Beverages'],
        ];

        $stmt = $pdo->prepare("INSERT INTO products (name, price, quantity, category_id) VALUES (?, ?, ?, ?)");
        foreach ($products as $p) {
            $catId = $catMap[$p[3]] ?? null;
            $stmt->execute([$p[0], $p[1], $p[2], $catId]);
        }
    }
}

// ---------- Run Migrations ----------
runMigrations($pdo);

// ---------- Global Helper Functions ----------
/**
 * Get the PDO connection (for backward compatibility)
 */
function getDB() {
    global $pdo;
    return $pdo;
}

/**
 * Execute a query with parameters and return the statement
 */
function dbQuery($sql, $params = []) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Fetch all rows
 */
function dbFetchAll($sql, $params = []) {
    return dbQuery($sql, $params)->fetchAll();
}

/**
 * Fetch one row
 */
function dbFetchOne($sql, $params = []) {
    return dbQuery($sql, $params)->fetch();
}

/**
 * Fetch a single column
 */
function dbFetchColumn($sql, $params = []) {
    return dbQuery($sql, $params)->fetchColumn();
}

/**
 * Insert a row into a table
 */
function dbInsert($table, $data) {
    global $pdo;
    $columns = implode(', ', array_keys($data));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));
    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($data));
    return $pdo->lastInsertId();
}

/**
 * Update a row in a table
 */
function dbUpdate($table, $data, $where, $whereParams = []) {
    global $pdo;
    $sets = implode(' = ?, ', array_keys($data)) . ' = ?';
    $sql = "UPDATE $table SET $sets WHERE $where";
    $stmt = $pdo->prepare($sql);
    $params = array_merge(array_values($data), $whereParams);
    return $stmt->execute($params);
}

/**
 * Delete rows from a table
 */
function dbDelete($table, $where, $params = []) {
    global $pdo;
    $sql = "DELETE FROM $table WHERE $where";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Begin a transaction
 */
function dbBegin() {
    global $pdo;
    return $pdo->beginTransaction();
}

/**
 * Commit a transaction
 */
function dbCommit() {
    global $pdo;
    return $pdo->commit();
}

/**
 * Rollback a transaction
 */
function dbRollback() {
    global $pdo;
    return $pdo->rollBack();
}

/**
 * Get the last inserted ID
 */
function dbLastInsertId() {
    global $pdo;
    return $pdo->lastInsertId();
}

/**
 * Escape a string for LIKE clause (prevents wildcard injection)
 */
function dbEscapeLike($string) {
    return addcslashes($string, '%_');
}

// ---------- Return PDO for direct use ----------
return $pdo; // optional if you want to require and get PDO
?>
