<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['admin_logged_in'])) { header("Location: index.php"); exit; }

$cp = basename($_SERVER['PHP_SELF']); 
?>

<button class="mobile-toggle" onclick="document.querySelector('.sidebar').classList.toggle('open')">
    <i class="fas fa-bars fa-lg"></i>
</button>

<aside class="sidebar">
    <div class="sidebar-header">
        <a href="dashboard.php" class="brand">
            <i class="fas fa-bolt"></i> Blogium
        </a>
    </div>

    <div class="sidebar-menu">
        <div class="menu-group-title">GENEL</div>
        <a href="dashboard.php" class="menu-item <?= $cp == 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-home menu-icon"></i> Özet
        </a>

        <div class="menu-group-title">İÇERİK YÖNETİMİ</div>
        <a href="posts.php" class="menu-item <?= $cp == 'posts.php' || $cp == 'edit_post.php' ? 'active' : '' ?>">
            <i class="fas fa-pen-nib menu-icon"></i> Yazılar
        </a>
        <a href="add_post.php" class="menu-item <?= $cp == 'add_post.php' ? 'active' : '' ?>">
            <i class="fas fa-plus-circle menu-icon"></i> Yeni Yazı Ekle
        </a>
        <a href="categories.php" class="menu-item <?= $cp == 'categories.php' || $cp == 'edit_category.php' ? 'active' : '' ?>">
            <i class="fas fa-list menu-icon"></i> Kategoriler
        </a>
        <a href="comments.php" class="menu-item <?= $cp == 'comments.php' ? 'active' : '' ?>">
            <i class="fas fa-comments menu-icon"></i> Yorumlar
        </a>
        <a href="manage_images.php" class="menu-item <?= $cp == 'manage_images.php' ? 'active' : '' ?>">
            <i class="fas fa-images menu-icon"></i> Galeri
        </a>

        <div class="menu-group-title">SİTE AYARLARI</div>
        <a href="pages.php" class="menu-item <?= $cp == 'pages.php' || $cp == 'edit_page.php' ? 'active' : '' ?>">
            <i class="fas fa-file-contract menu-icon"></i> Sabit Sayfalar
        </a>
        <a href="manage_menu.php" class="menu-item <?= $cp == 'manage_menu.php' ? 'active' : '' ?>">
            <i class="fas fa-bars menu-icon"></i> Menü Düzeni
        </a>
        <a href="settings.php" class="menu-item <?= $cp == 'settings.php' ? 'active' : '' ?>">
            <i class="fas fa-cog menu-icon"></i> Ayarlar
        </a>
    </div>

    <div class="sidebar-footer">
        <a href="logout.php" class="btn btn-danger btn-sm" style="width: 100%;" onclick="return confirm('Çıkış yapmak istiyor musunuz?')">
            <i class="fas fa-sign-out-alt"></i> Çıkış Yap
        </a>
    </div>
</aside>