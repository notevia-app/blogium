<?php
// save_token.php
require_once 'adminpanel/includes/db.php'; // DB yolunu kontrol et

// JSON verisini al
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['token'])) {
    $token = $data['token'];
    
    try {
        // Token daha önce var mı kontrol et
        $check = $pdo->prepare("SELECT id FROM fcm_tokens WHERE token = ?");
        $check->execute([$token]);
        
        if ($check->rowCount() == 0) {
            // Yoksa ekle
            $stmt = $pdo->prepare("INSERT INTO fcm_tokens (token) VALUES (?)");
            $stmt->execute([$token]);
            echo json_encode(['status' => 'success', 'message' => 'Token kaydedildi.']);
        } else {
            echo json_encode(['status' => 'exists', 'message' => 'Token zaten kayıtlı.']);
        }
    } catch (PDOException $e) {
        error_log('Token kayıt hatası: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Veritabanı hatası']);
    }
}
?>