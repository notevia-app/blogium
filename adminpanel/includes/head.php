<?php
// adminpanel/includes/head.php

// Oturum kontrolü yoksa (login sayfası gibi) hata vermesin
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Site adresini otomatik algıla (http/https ve domain)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
// Admin panelinin kök URL'si (Örn: https://site.com/adminpanel/)
$admin_url = "$protocol://$host/adminpanel/";
?>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Yönetim Paneli</title>

<link rel="icon" type="image/png" href="/favicon-96x96.png">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<link rel="stylesheet" href="<?= $admin_url ?>assets/css/panel-styles.css?v=<?= time() ?>">