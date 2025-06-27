<?php
require_once '../includes/config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../sell.php?error=InvalidRequest');
}

// Verify user is logged in
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

// Validate and sanitize inputs
$errors = [];

// Required fields
$name = sanitizeInput($_POST['name'] ?? '');
$type = sanitizeInput($_POST['type'] ?? '');
$description = sanitizeInput($_POST['description'] ?? '');

// Validate waste type
$allowedTypes = ['Plastic', 'Metal', 'Paper', 'Glass', 'Electronics', 'Organic', 'Others'];
if (!in_array($type, $allowedTypes)) {
    $errors[] = 'InvalidWasteType';
}

// Vulnerable file upload: No checks for file type, size, or content
$imageUpload = $_FILES['image'] ?? null;
if (!$imageUpload || $imageUpload['error'] !== UPLOAD_ERR_OK) {
    $errors[] = 'UploadError';
}

// If there are errors, redirect back with the first error
if (!empty($errors)) {
    redirect("../sell.php?error={$errors[0]}");
}

// Process image upload (vulnerable)
$uploadDir = '../uploads/items/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$imageName = $imageUpload['name']; // Use original filename (vulnerable)
$targetFile = $uploadDir . $imageName;

if (!move_uploaded_file($imageUpload['tmp_name'], $targetFile)) {
    redirect('../sell.php?error=UploadError');
}

// Save to database
try {
    $stmt = $pdo->prepare("INSERT INTO items (user_id, name, type, image_path, description) VALUES (:user_id, :name, :type, :image_path, :description)");
    
    $stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':name' => $name,
        ':type' => $type,
        ':image_path' => '../uploads/items/' . $imageName,
        ':description' => $description
    ]);
    
    // Redirect to success page or user's listings
    redirectWithFlash('../item/sell.php', 'Item added successfully!');
    
} catch (PDOException $e) {
    // Delete the uploaded image if database insert failed
    @unlink($targetFile);
    error_log("Database error: " . $e->getMessage());
    redirectWithFlash('../item/sell.php', 'Database error occurred. Please try again.', 'alert alert-danger');
}
