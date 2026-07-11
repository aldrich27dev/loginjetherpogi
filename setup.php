<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

$host = "localhost:3307"; 
$username = "root";
$password = "";
$db_name = "campuswell_db"; // Updated to your exact database name

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $pdo->exec("USE `$db_name`;");

    $pdo->exec("DROP TABLE IF EXISTS users;");

    // Rebuild table schema with distinct registration form fields
    $tableSql = "CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(50) NOT NULL UNIQUE,
        first_name VARCHAR(100) NOT NULL,
        middle_name VARCHAR(100) DEFAULT 'N/A',
        last_name VARCHAR(100) NOT NULL,
        year_level VARCHAR(50) NOT NULL,
        contact_number VARCHAR(20) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        address TEXT NOT NULL,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'student',
        failed_attempts INT DEFAULT 0,
        is_locked TINYINT DEFAULT 0,
        lockout_until DATETIME NULL DEFAULT NULL,
        verification_code VARCHAR(10) NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($tableSql);

    $seedSql = "INSERT INTO users (student_id, first_name, middle_name, last_name, year_level, contact_number, email, address, password, role) VALUES 
    ('2024-06-00159', 'Aldrich', 'N/A', 'Dela Cruz', '4th Year', '09123456789', 'aldrichpogi@gmail.com', 'Valenzuela City', '\$2y\$10\$zqYkfHVS5E02sC9HiLLPnObzpEGinHnhZhqZ5ezyaU...', 'student'),
    ('2024-06-00160', 'Aldrich', 'N/A', 'Dela Cruz', '4th Year', '09123456789', 'aldrich@grc.edu.ph', 'Valenzuela City', '\$2y\$10\$REEtvs8mX1I/dgg6EimmjeCsQjbqNCfdTb60zh7qo3s...', 'student'),
    ('COUNSELOR-01', 'Jane', 'N/A', 'Smith', 'N/A', '09123456788', 'counselor@grc.edu.ph', 'Caloocan City', '\$2y\$10\$GaRKxY0zZOEPGc5pgYl4pu0YT9PXhSEJizi9repNYC7...', 'counselor'),
    ('ADMIN-01', 'Super', 'N/A', 'Admin', 'N/A', '09123456787', 'admin@grc.edu.ph', 'Manila City', '\$2y\$10\$GaRKxY0zZOEPGc5pgYl4pu0YT9PXhSEJizi9repNYC7...', 'admin');";

    $pdo->exec($seedSql);

    echo json_encode([
        "status" => "success",
        "message" => "Database 'campuswell_db' successfully reconfigured and seeded on port 3307!"
    ]);

} catch (PDOException $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Database Initialization Failure: " . $e->getMessage()
    ]);
}
?>