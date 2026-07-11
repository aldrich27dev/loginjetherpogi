<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$host = "localhost";
$db_name = "campuswell_db";
$username = "root";
$password = "";
$port = "3307"; 

try {
    $conn = new PDO("mysql:host=$host;port=$port;dbname=$db_name;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $data = json_decode(file_get_contents("php://input"));

    // Removed strict tracking constraint check on address since it's not present in the frontend UI profile panel layout
    if (
        empty($data->studentId) || 
        empty($data->lastName) || 
        empty($data->firstName) || 
        empty($data->yearLevel) || 
        empty($data->contactNumber) || 
        empty($data->email) ||
        empty($data->password) // Check for incoming password from form
    ) {
        echo json_encode(["status" => "error", "message" => "All required fields must be completed."]);
        exit;
    }

    // Sanitize values safely
    $studentId     = trim($data->studentId);
    $lastName      = trim($data->lastName);
    $middleName    = !empty($data->middleName) ? trim($data->middleName) : 'N/A';
    $firstName     = trim($data->firstName);
    $yearLevel     = trim($data->yearLevel);
    $contactNumber = trim($data->contactNumber);
    $email         = trim($data->email);
    $address       = !empty($data->address) ? trim($data->address) : 'N/A';
    $role          = 'student'; 

    // Securely BCrypt hash the password selected by the student in the form view component tracking item
    $hashed_password = password_hash(trim($data->password), PASSWORD_BCRYPT);

    // 1. Verify if the account or email address is already registered using the correct column structures
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = :email OR student_id = :studentId LIMIT 1");
    $check_stmt->execute([':email' => $email, ':studentId' => $studentId]);
    
    if ($check_stmt->fetch()) {
        echo json_encode(["status" => "error", "message" => "This Student ID or Email Address is already registered."]);
        exit;
    }

    // 2. Process database insert aligning cleanly with your actual table column schemas
    $insert_query = "INSERT INTO users (
        student_id, first_name, middle_name, last_name, year_level, 
        contact_number, email, address, password, role, failed_attempts, is_locked
    ) VALUES (
        :student_id, :first_name, :middle_name, :last_name, :year_level, 
        :contact_number, :email, :address, :password, :role, 0, 0
    )";

    $stmt = $conn->prepare($insert_query);
    $stmt->execute([
        ':student_id'     => $studentId,
        ':first_name'     => $firstName,
        ':middle_name'    => $middleName,
        ':last_name'      => $lastName,
        ':year_level'     => $yearLevel,
        ':contact_number' => $contactNumber,
        ':email'          => $email,
        ':address'        => $address,
        ':password'       => $hashed_password,
        ':role'           => $role
    ]);

    echo json_encode([
        "status" => "success",
        "message" => "Account successfully created! You may now sign into the control panel."
    ]);

} catch (PDOException $e) {
    echo json_encode([
        "status" => "error", 
        "message" => "Database validation error: " . $e->getMessage()
    ]);
}
?>