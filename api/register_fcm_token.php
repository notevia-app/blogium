<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/db.php';

$data = json_decode(file_get_contents("php://input"));
if (!isset($data->token) || empty($data->token)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Token eksik.']);
    exit();
}
$token = $data->token;

// Aynı token'ın tekrar tekrar eklenmesini önlemek için IGNORE kullanıyoruz
$stmt = $db->prepare("INSERT IGNORE INTO fcm_tokens (token) VALUES (:token)");
$stmt->bindParam(':token', $token);
if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Token kaydedildi.']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Token kaydedilemedi.']);
}
?>