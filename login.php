<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 👈 Synchronize timezone to prevent lock logic mismatches
date_default_timezone_set('Asia/Manila'); 

$host = "localhost:3307"; 
$db_name = "campuswell_db"; 
$username = "root";
$password = "";

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "DATABASE CONNECTION FAILED. PLEASE CHECK YOUR XAMPP STATUS."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->email) && !empty($data->password)) {
    $email = trim($data->email);
    $password = trim($data->password);
    $role = !empty($data->role) ? strtolower(trim($data->role)) : 'student';

    try {
        $query = "SELECT id, first_name, last_name, password, role, failed_attempts, is_locked, lockout_until FROM users WHERE email = :email AND role = :role LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":role", $role);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $userId = $user['id'];
            $now = date('Y-m-d H:i:s');

            // 1. Check if the account state is currently flagged as locked
            if ($user['is_locked'] == 1) {
                if ($user['lockout_until'] !== null && $now < $user['lockout_until']) {
                    $timeLeft = strtotime($user['lockout_until']) - strtotime($now);
                    $minutesLeft = ceil($timeLeft / 60);
                    echo json_encode([
                        "status" => "error", 
                        "message" => "This account is locked due to 5 failed attempts. Please try again in $minutesLeft minutes."
                    ]);
                    exit;
                } else {
                    // Lockout expired; clean flags up
                    $resetLock = $conn->prepare("UPDATE users SET is_locked = 0, failed_attempts = 0, lockout_until = NULL WHERE id = :id");
                    $resetLock->bindParam(":id", $userId);
                    $resetLock->execute();
                    $user['failed_attempts'] = 0;
                    $user['is_locked'] = 0; // Ensure local flag clears too
                }
            }
            
            // 2. Password verification
            if (password_verify($password, $user['password'])) {
                // Success: clear lockouts and tracked mistakes
                $clearQuery = "UPDATE users SET failed_attempts = 0, is_locked = 0, lockout_until = NULL WHERE id = :id";
                $clearStmt = $conn->prepare($clearQuery);
                $clearStmt->bindParam(":id", $userId);
                $clearStmt->execute();

                echo json_encode([
                    "status" => "success",
                    "user" => [
                        "email" => $email,
                        "name" => $user['first_name'] . ' ' . $user['last_name']
                    ]
                ]);
            } else {
                // Failure calculation
                $newAttempts = $user['failed_attempts'] + 1;
                
                if ($newAttempts >= 5) {
                    $lockoutUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                    $updateQuery = "UPDATE users SET failed_attempts = :attempts, is_locked = 1, lockout_until = :lockout_until WHERE id = :id";
                    $updateStmt = $conn->prepare($updateQuery);
                    $updateStmt->bindParam(":lockout_until", $lockoutUntil);
                    
                    $msg = "Account locked out! You have exceeded 5 unsuccessful login tries.";
                } else {
                    $updateQuery = "UPDATE users SET failed_attempts = :attempts WHERE id = :id";
                    $updateStmt = $conn->prepare($updateQuery);
                    
                    $remaining = 5 - $newAttempts;
                    $msg = "Invalid username or password. You have $remaining remaining tries before account locking.";
                }
                
                $updateStmt->bindParam(":attempts", $newAttempts);
                $updateStmt->bindParam(":id", $userId);
                $updateStmt->execute();

                echo json_encode(["status" => "error", "message" => $msg]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Invalid username or password."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "System error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Please complete all input tracking items."]);
}
?>