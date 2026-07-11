<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$host = "localhost:3307"; 
$db_name = "campuswell_db"; 
$username = "root";
$password = "";

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database connection error: " . $e->getMessage()]);
    exit;
}

$data = json_decode(file_get_contents("php://input"));

if (empty($data->action)) {
    echo json_encode(["status" => "error", "message" => "Missing request action parameters."]);
    exit;
}

$action = trim($data->action);

// ==========================================================
// ACTION 1: REQUEST OTP (STEP 1) - Generates a Random Code
// ==========================================================
if ($action === 'request_otp') {
    if (empty($data->email)) {
        echo json_encode(["status" => "error", "message" => "Please enter your registered email address."]);
        exit;
    }
    
    $email = trim($data->email);

    try {
        $query = "SELECT id FROM users WHERE email = :email LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Generates a secure, random 6-digit number between 100000 and 999999
            $otp_code = (string)rand(100000, 999999); 
            
            $update_query = "UPDATE users SET verification_code = :code WHERE email = :email";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bindParam(":code", $otp_code);
            $update_stmt->bindParam(":email", $email);
            $update_stmt->execute();

            echo json_encode([
                "status" => "success",
                "message" => "Identity verified. A random code has been generated in your database table."
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "The institutional email address was not found."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
}

// ==========================================================
// ACTION 2: VERIFY OTP (STEP 2) - Forgiving Property Validation
// ==========================================================
else if ($action === 'verify_otp') {
    // Read both common naming configurations ('otp' or 'code') to handle frontend naming discrepancies safely
    $email = !empty($data->email) ? trim($data->email) : '';
    $otp = !empty($data->otp) ? trim($data->otp) : (!empty($data->code) ? trim($data->code) : '');

    if (empty($email) || empty($otp)) {
        echo json_encode([
            "status" => "error", 
            "message" => "Verification verification parameters are incomplete. Check that email and code are sent together."
        ]);
        exit;
    }

    try {
        $query = "SELECT id FROM users WHERE email = :email AND verification_code = :code LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":code", $otp);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                "status" => "success",
                "message" => "Code verified successfully."
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "Invalid verification code. Please refresh your database table to see the new random code."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
}

// ==========================================================
// ACTION 3: RESET PASSWORD (STEP 3)
// ==========================================================
else if ($action === 'reset_password') {
    if (empty($data->email) || empty($data->password)) {
        echo json_encode(["status" => "error", "message" => "Missing email data or new password values."]);
        exit;
    }

    $email = trim($data->email);
    $new_password = password_hash(trim($data->password), PASSWORD_BCRYPT); 

    try {
        $query = "UPDATE users SET password = :password, verification_code = NULL WHERE email = :email";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":password", $new_password);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        echo json_encode([
            "status" => "success",
            "message" => "Password changed successfully!"
        ]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Failed to update password: " . $e->getMessage()]);
    }
}
?>