<?php
require 'includes/db.php';

if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $pdo->prepare("DELETE FROM header_menu WHERE id = ?")->execute([$id]);
}

header("Location: manage_menu.php");
exit;
