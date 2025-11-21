<?php
/**
 * Authentication API Endpoints (Railway Safe Version)
 * 
 * POST /api/auth.php?action=register
 * POST /api/auth.php?action=login
 *
 * This version fixes:
 * - Railway DB connection issues
 * - Unwanted output before headers
 * - Suppressed errors hiding critical failures
 * - Invalid response formatting
 */

// Disable ALL output before JSON response
@ob_start();
@error_reporting(0);
@ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

// Load autoloader
require_once __DIR__ . '/../app/autoload.php';

// Namespaces
use App\Config\Database;
use App\Utils\DatabaseHelper;
use App\Utils\Validator;
use App\Utils\Response;

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');

    if ($method !== 'POST') {
        Response::error("Only POST method allowed", 405);
    }

    if (!$action) {
        Response::error("Missing action parameter", 400);
    }

    switch ($action) {
        case 'register':
            handleRegister();
            break;
        case 'login':
            handleLogin();
            break;
        default:
            Response::error("Invalid action", 400);
    }

} catch (Throwable $e) {
    error_log("API Fatal Error: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());

    @ob_clean();
    Response::error("Internal server error", 500);
}


/**
 * ============================
 * REGISTER USER
 * ============================
 */
function handleRegister()
{
    try {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data) {
            Response::error("Invalid JSON body", 400);
        }

        $email = trim(strtolower($data['email'] ?? ''));
        $password = $data['password'] ?? '';

        // Validate
        $errors = Validator::required($data, ['email', 'password']);
        if ($errors) Response::validationError($errors);

        if (!Validator::email($email)) {
            Response::error("Invalid email format", 400);
        }

        if (!Validator::password($password)) {
            Response::error("Password must be at least 6 characters", 400);
        }

        $db = Database::getInstance();

        // Check user exists
        $exists = $db->queryOne(
            "SELECT id FROM users WHERE email = :email",
            ['email' => $email]
        );

        if ($exists) {
            Response::error("User already exists", 400);
        }

        // Insert user
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $userId = $db->insert("users", [
            "email" => $email,
            "password" => $hashed,
            "is_active" => true
        ]);

        // Create default settings
        $tomorrow = date("Y-m-d", strtotime("+1 day"));
        $db->insert("settings", [
            "user_id" => $userId,
            "stake" => 1.00,
            "target" => 100.00,
            "stop_limit" => 50.00,
            "is_bot_active" => false,
            "daily_profit" => 0.00,
            "daily_loss" => 0.00,
            "reset_date" => $tomorrow
        ]);

        // Set session
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $email;

        Response::success([
            "token" => session_id(),
            "user" => [
                "id" => $userId,
                "email" => $email
            ]
        ], "User registered successfully", 201);

    } catch (Throwable $e) {
        error_log("Register Error: " . $e->getMessage());
        Response::error("Registration failed", 500);
    }
}


/**
 * ============================
 * LOGIN USER
 * ============================
 */
function handleLogin()
{
    try {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data) {
            Response::error("Invalid JSON body", 400);
        }

        $email = trim(strtolower($data['email'] ?? ''));
        $password = $data['password'] ?? '';

        $errors = Validator::required($data, ['email', 'password']);
        if ($errors) Response::validationError($errors);

        if (!Validator::email($email)) {
            Response::error("Invalid email format", 400);
        }

        $db = Database::getInstance();

        $user = $db->queryOne(
            "SELECT id, email, password, is_active, is_admin FROM users WHERE email = :email",
            ['email' => $email]
        );

        if (!$user) {
            Response::error("Invalid credentials", 401);
        }

        if (!password_verify($password, $user['password'])) {
            Response::error("Invalid credentials", 401);
        }

        if (!$user['is_active']) {
            Response::error("Account disabled", 403);
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['is_admin'] = (bool)($user['is_admin'] ?? false);

        Response::success([
            "token" => session_id(),
            "user" => [
                "id" => $user['id'],
                "email" => $user['email'],
                "is_admin" => (bool)$user['is_admin']
            ]
        ], "Login successful");

    } catch (Throwable $e) {
        error_log("Login Error: " . $e->getMessage());
        Response::error("Login failed", 500);
    }
}
