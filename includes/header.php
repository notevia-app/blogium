<?php
// header.php

require_once __DIR__ . '/../init.php';

// --- ZİYARETÇİ TAKİP SİSTEMİ BAŞLANGIÇ ---
// Bu blok, admin panelindeki sayaçların çalışmasını sağlar.
if (isset($pdo)) {
    try {
        $visitor_ip = $_SERVER['REMOTE_ADDR'];
        $current_url = $_SERVER['REQUEST_URI'];
        
        // Ziyaretçinin IP adresini alırken Proxy/Cloudflare kontrolü
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $visitor_ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $visitor_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        // Spam Kontrolü: Aynı IP, aynı sayfayı son 1 dakika içinde ziyaret ettiyse tekrar kaydetme.
        $check_stmt = $pdo->prepare("SELECT id FROM visitor_analytics WHERE ip_address = ? AND page_url = ? AND visit_date > (NOW() - INTERVAL 1 MINUTE)");
        $check_stmt->execute([$visitor_ip, $current_url]);

        if ($check_stmt->rowCount() == 0) {
            // Yeni ziyaretse veritabanına işle
            $log_stmt = $pdo->prepare("INSERT INTO visitor_analytics (ip_address, page_url) VALUES (?, ?)");
            $log_stmt->execute([$visitor_ip, $current_url]);
        }

    } catch (PDOException $e) {
        // Hata olursa sessizce logla, siteyi bozma
        error_log("Ziyaretçi takibi hatası: " . $e->getMessage());
    }
}
// --- ZİYARETÇİ TAKİP SİSTEMİ BİTİŞ ---


// --- TÜM SİTE İÇİN GEREKLİ GENEL VERİLER ---
$menu_items = $popular_posts = $recent_posts = [];
if (isset($pdo)) {
    try {
        $menu_stmt = $pdo->query("SELECT * FROM header_menu ORDER BY id ASC");
        $menu_items = $menu_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log("Header menü hatası: ".$e->getMessage()); }
    
    try {
        $popular_stmt = $pdo->query("SELECT id, title, slug, image_url FROM posts ORDER BY views DESC LIMIT 5");
        $popular_posts = $popular_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log("Popüler yazılar hatası: ".$e->getMessage()); }

    try {
        $recent_stmt = $pdo->query("SELECT id, title, slug, image_url FROM posts ORDER BY created_at DESC LIMIT 4");
        $recent_posts = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log("Son yazılar hatası: ".$e->getMessage()); }
}

$is_logged_in = isset($_SESSION['user_id']);
$username = $_SESSION['username'] ?? '';
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?>Blogium</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f8fafc; color: #1e293b; }
        .header { background: #ffffff; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); border-bottom: 1px solid #e5e7eb; position: sticky; top: 0; z-index: 1000; }
        .header-top { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; align-items: center; justify-content: space-between; height: 70px; }
        .logo-section { display: flex; align-items: center; gap: 12px; }
        .logo { width: 40px; height: 40px; }
        .logo img { width: 100%; height: 100%; object-fit: contain; }
        .site-title { font-size: 24px; font-weight: 700; color: #1e293b; text-decoration: none; }
        .search-container { flex: 1; max-width: 600px; margin: 0 40px; position: relative; }
        .search-box { width: 100%; height: 45px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 25px; padding: 0 50px 0 20px; font-size: 16px; }
        .search-btn { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #6b7280; font-size: 18px; cursor: pointer; }
        .user-section { display: flex; align-items: center; }
        .mobile-icons { display: none; gap: 15px; }
        .icon-btn { background: none; border: none; color: #374151; font-size: 20px; cursor: pointer; padding: 10px; border-radius: 50%; }
        
        /* Dropdown Genel Stilleri */
        .profile-dropdown, .more-dropdown { position: relative; }
        .profile-btn { background: transparent; border: 1px solid #e2e8f0; color: #374151; padding: 8px 16px; border-radius: 20px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 14px; }
        .dropdown-menu { position: absolute; top: 100%; right: 0; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15); min-width: 200px; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.3s ease; margin-top: 10px; z-index: 1001; }
        .dropdown-menu.show { opacity: 1; visibility: visible; transform: translateY(0); }
        .dropdown-item { display: block; padding: 12px 16px; color: #374151; text-decoration: none; border-radius: 8px; margin: 4px; }
        .dropdown-item:hover { background-color: #f3f4f6; color: #667eea; }
        .dropdown-item i { margin-right: 8px; width: 16px; text-align: center; }
        
        /* Nav Menu Stilleri */
        .nav-menu {background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); border-top: 1px solid rgba(255, 255, 255, 0.1);}
        .nav-container {max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: center; align-items: center; min-height: 50px;}
        .nav-items {display: flex; list-style: none; gap: 30px;}
        .nav-item {position: relative;}
        .nav-link {color: #000; text-decoration: none; font-weight: 500; padding: 15px 0; transition: all 0.3s ease; position: relative; display: flex; align-items: center; gap: 5px;}
        .nav-link::after {content: ''; position: absolute; bottom: -1px; left: 0; width: 0; height: 2px; background: #000; transition: width 0.3s ease;}
        .nav-link:hover::after { width: 100%;}

        /* Navigasyon Dropdown Stili */
        .nav-dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0; 
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            min-width: 220px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: all 0.3s ease;
            z-index: 1002;
            padding: 5px;
            margin-top: 0;
        }
        
        .more-dropdown:hover .nav-dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .more-dropdown:hover .fa-chevron-down {
            transform: rotate(180deg);
            transition: transform 0.3s;
        }

        .mobile-search-bar { background: #f8fafc; padding: 0 20px; border-top: 1px solid #e5e7eb; max-height: 0; overflow: hidden; transition: max-height 0.3s ease-in-out, padding 0.3s ease-in-out; }
        .mobile-search-bar.show { max-height: 80px; padding: 15px 20px; }
        .mobile-search-bar form { display: flex; align-items: center; width: 100%; }
        .mobile-search-bar .search-box { height: 40px; border-radius: 20px; }
        .mobile-search-bar .search-btn { position: static; transform: none; color: #6b7280; margin-left: -40px; }

        /* Sidebar Stilleri */
        .mobile-sidebar { position: fixed; top: 0; right: -100%; width: 320px; height: 100vh; background: #ffffff; z-index: 2000; transition: right 0.4s ease-in-out; display: flex; flex-direction: column; box-shadow: -10px 0 30px rgba(0, 0, 0, 0.1); }
        .mobile-sidebar.show { right: 0; }
        .sidebar-header { padding: 15px 20px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
        .sidebar-title { font-size: 18px; font-weight: 600; }
        .close-btn { background: none; border: none; font-size: 24px; cursor: pointer; transition: transform 0.2s; }
        .close-btn:hover { transform: rotate(90deg); }
        .sidebar-content { flex-grow: 1; overflow-y: auto; padding: 15px 0; }
        .sidebar-user-profile { padding: 15px 20px; display: flex; align-items: center; gap: 15px; border-bottom: 1px solid #e5e7eb; }
        .sidebar-user-profile .avatar { width: 45px; height: 45px; background: #eef2ff; color: #667eea; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .sidebar-user-profile .user-info h4 { margin: 0 0 4px 0; font-weight: 600; }
        .sidebar-user-profile .user-info p { margin: 0; font-size: 13px; color: #64748b; }
        .sidebar-auth-buttons { display: flex; gap: 10px; padding: 20px; border-bottom: 1px solid #e5e7eb; }
        .sidebar-auth-buttons .btn { flex: 1; text-align: center; padding: 12px; border-radius: 8px; text-decoration: none; font-weight: 500; }
        .sidebar-auth-buttons .btn-login { background: #eef2ff; color: #4338ca; }
        .sidebar-auth-buttons .btn-signup { background: #667eea; color: #ffffff; }
        .sidebar-nav { list-style: none; }
        .sidebar-nav-item a, .accordion-toggle { display: flex; align-items: center; padding: 15px 20px; text-decoration: none; color: #334155; font-weight: 500; }
        .sidebar-nav-item i, .accordion-toggle i { width: 20px; margin-right: 15px; text-align: center; font-size: 16px; color: #94a3b8; }
        .accordion-toggle { cursor: pointer; justify-content: space-between; width: 100%; border: none; background: none; font-size: 1rem; }
        .accordion-toggle .fa-chevron-down { transition: transform 0.3s ease; }
        .accordion-toggle.active .fa-chevron-down { transform: rotate(180deg); }
        .accordion-content { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-in-out; background: #f8fafc; }
        .accordion-post-list { list-style: none; padding: 10px 20px 10px 35px; }
        .accordion-post-item { margin-bottom: 10px; }
        .accordion-post-item a { display: flex; align-items: center; gap: 10px; text-decoration: none; color: #475569; }
        .accordion-post-item img { width: 40px; height: 40px; border-radius: 6px; object-fit: cover; }
        .sidebar-divider { height: 1px; background: #e5e7eb; margin: 15px 20px; }
        .sidebar-footer { padding: 20px; text-align: center; font-size: 13px; color: #94a3b8; }
        .sidebar-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1500; opacity: 0; visibility: hidden; transition: all 0.3s ease; }
        .sidebar-overlay.show { opacity: 1; visibility: visible; }
        
        @media (max-width: 768px) { .search-container, .user-section, .nav-menu { display: none; } .mobile-icons { display: flex; } .header-top { padding: 0 15px; } .logo-section { flex-grow: 1; } }
        @media (max-width: 480px) { .mobile-sidebar { width: 100%; } }
    </style>
</head>
<body>

<header class="header">
    <div class="header-top">
        <div class="logo-section">
            <a href="/" class="logo"><img src="/logo.png" alt="Blogium Logo"></a>
            <a href="/" class="site-title">Blogium</a>
        </div>
        <div class="search-container">
            <form action="/search.php" method="GET">
                <input type="text" name="q" class="search-box" placeholder="Arama yapın...">
                <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
            </form>
        </div>
        <div class="user-section">
            <?php if ($is_logged_in): ?>
                <div class="profile-dropdown">
                    <button class="profile-btn" onclick="toggleDesktopDropdown(event)">
                        <i class="fas fa-user"></i><span><?= htmlspecialchars($username); ?></span><i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu" id="profileDropdown">
                        <a href="/liked_post.php" class="dropdown-item"><i class="fas fa-heart"></i> Beğenilenler</a>
                        <a href="/saved_post.php" class="dropdown-item"><i class="fas fa-bookmark"></i> Kaydedilenler</a>
                        <a href="/account_settings.php" class="dropdown-item"><i class="fas fa-cog"></i> Ayarlar</a>
                        <a href="/logout.php" class="dropdown-item"><i class="fas fa-sign-out-alt"></i> Çıkış</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="/signin.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="profile-btn"><i class="fas fa-sign-in-alt"></i> Giriş Yap</a>
            <?php endif; ?>
        </div>
        <div class="mobile-icons">
            <button class="icon-btn" onclick="toggleMobileSearch()"><i class="fas fa-search"></i></button>
            <button class="icon-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
        </div>
    </div>
    
    <nav class="nav-menu">
        <div class="nav-container">
            <ul class="nav-items">
                <?php foreach ($menu_items as $item): ?>
                    <li class="nav-item">
                        <a href="<?= htmlspecialchars($item['url']); ?>" class="nav-link"><?= htmlspecialchars($item['title']); ?></a>
                    </li>
                <?php endforeach; ?>

                <li class="nav-item more-dropdown">
                    <a href="javascript:void(0)" class="nav-link">
                        Daha Fazla <i class="fas fa-chevron-down" style="font-size: 9px; transition: transform 0.3s;"></i>
                    </a>
                    <div class="nav-dropdown-menu">
                        <a href="/hakkimizda" class="dropdown-item">Hakkımızda</a>
                        <a href="/iletisim" class="dropdown-item">İletişim</a>
                        <a href="/gizlilik-politikasi" class="dropdown-item">Gizlilik Politikası</a>
                        <a href="/kullanici-sozlesmesi" class="dropdown-item">Kullanıcı Sözleşmesi</a>
                    </div>
                </li>
            </ul>
        </div>
    </nav>
    
    <div class="mobile-search-bar" id="mobileSearchBar">
        <form action="/search.php" method="GET">
            <input type="text" name="q" class="search-box" placeholder="Arama yapın...">
            <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
        </form>
    </div>
</header>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeAllModals()"></div>

<aside class="mobile-sidebar" id="mobileSidebar">
    <div class="sidebar-header">
        <h3 class="sidebar-title">Menü</h3>
        <button class="close-btn" onclick="closeSidebar()"><i class="fas fa-times"></i></button>
    </div>
    <div class="sidebar-content">
        <?php if ($is_logged_in): ?>
            <div class="sidebar-user-profile">
                <div class="avatar"><i class="fas fa-user"></i></div>
                <div class="user-info">
                    <h4><?= htmlspecialchars($username); ?></h4>
                </div>
            </div>
        <?php else: ?>
            <div class="sidebar-auth-buttons">
                <a href="/signin.php" class="btn btn-login">Giriş Yap</a>
                <a href="/signup.php" class="btn btn-signup">Kayıt Ol</a>
            </div>
        <?php endif; ?>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item"><a href="/"><i class="fas fa-home"></i> Anasayfa</a></li>
            <?php if ($is_logged_in): ?>
                <li class="sidebar-nav-item"><a href="/account_settings.php"><i class="fas fa-cog"></i> Hesap Ayarları</a></li>
                <li class="sidebar-nav-item"><a href="/liked_post.php"><i class="fas fa-heart"></i> Beğenilenler</a></li>
                <li class="sidebar-nav-item"><a href="/saved_post.php"><i class="fas fa-bookmark"></i> Kaydedilenler</a></li>
            <?php endif; ?>
            <li class="sidebar-nav-item"><a href="/random.php"><i class="fas fa-random"></i> Rastgele Yazı</a></li>
            <li class="sidebar-divider"></li>
            <li class="sidebar-nav-item">
                <button class="accordion-toggle">
                    <span><i class="fas fa-tags"></i> Kategoriler</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="accordion-content">
                    <ul class="sidebar-nav" style="padding-left: 15px;">
                        <?php foreach ($menu_items as $item): ?>
                            <li class="sidebar-nav-item"><a href="<?= htmlspecialchars($item['url']); ?>"><i class="fas fa-hashtag"></i> <?= htmlspecialchars($item['title']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </li>
            
            <li class="sidebar-nav-item">
                <button class="accordion-toggle">
                    <span><i class="fas fa-circle-info"></i> Kurumsal</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="accordion-content">
                    <ul class="sidebar-nav" style="padding-left: 15px;">
                        <li class="sidebar-nav-item"><a href="/hakkimizda"><i class="fas fa-info"></i> Hakkımızda</a></li>
                        <li class="sidebar-nav-item"><a href="/iletisim"><i class="fas fa-envelope"></i> İletişim</a></li>
                        <li class="sidebar-nav-item"><a href="/gizlilik-politikasi"><i class="fas fa-shield-alt"></i> Gizlilik Politikası</a></li>
                        <li class="sidebar-nav-item"><a href="/kullanici-sozlesmesi"><i class="fas fa-file-contract"></i> Kullanıcı Sözleşmesi</a></li>
                    </ul>
                </div>
            </li>

            <li class="sidebar-nav-item">
                <button class="accordion-toggle">
                    <span><i class="fas fa-fire"></i> Popüler Yazılar</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="accordion-content">
                    <ul class="accordion-post-list">
                         <?php foreach ($popular_posts as $post): ?>
                         <li class="accordion-post-item">
                            <a href="/yazi/<?= urlencode($post['slug']) ?>">
                                <img src="/<?= htmlspecialchars($post['image_url'] ?? 'assets/images/default.png'); ?>" alt="">
                                <span><?= htmlspecialchars($post['title']); ?></span>
                            </a>
                         </li>
                         <?php endforeach; ?>
                    </ul>
                </div>
            </li>
            <li class="sidebar-nav-item">
                <button class="accordion-toggle">
                    <span><i class="fas fa-clock-rotate-left"></i> Son Yazılar</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="accordion-content">
                    <ul class="accordion-post-list">
                         <?php foreach ($recent_posts as $post): ?>
                         <li class="accordion-post-item">
                            <a href="/yazi/<?= urlencode($post['slug']) ?>">
                                <img src="/<?= htmlspecialchars($post['image_url'] ?? 'assets/images/default.png'); ?>" alt="">
                                <span><?= htmlspecialchars($post['title']); ?></span>
                            </a>
                         </li>
                         <?php endforeach; ?>
                    </ul>
                </div>
            </li>
            <?php if ($is_logged_in): ?>
                <li class="sidebar-divider"></li>
                <li class="sidebar-nav-item"><a href="/logout.php"><i class="fas fa-sign-out-alt"></i> Çıkış Yap</a></li>
            <?php endif; ?>
        </ul>
    </div>
    <footer class="sidebar-footer">
        Blogium © <?= date('Y') ?>
    </footer>
</aside>


<script>
function toggleDesktopDropdown(event) { event.stopPropagation(); document.getElementById('profileDropdown').classList.toggle('show'); }
function toggleSidebar() { 
    closeAllModals(true); 
    document.getElementById('mobileSidebar').classList.add('show'); 
    document.getElementById('sidebarOverlay').classList.add('show'); 
    document.body.style.overflow = 'hidden'; 
}
function closeSidebar() { document.getElementById('mobileSidebar').classList.remove('show'); document.getElementById('sidebarOverlay').classList.remove('show'); document.body.style.overflow = 'auto'; }
function closeDesktopDropdown() { const dropdown = document.getElementById('profileDropdown'); if(dropdown) dropdown.classList.remove('show'); }

function toggleMobileSearch() { 
    const searchBar = document.getElementById('mobileSearchBar'); 
    const isOpening = !searchBar.classList.contains('show'); 
    closeAllModals(true); 
    searchBar.classList.toggle('show'); 
    if (isOpening) { setTimeout(() => searchBar.querySelector('input').focus(), 300); } 
}

function closeAllModals(keepSearchOpen = false) { 
    closeSidebar(); 
    closeDesktopDropdown(); 
    if (!keepSearchOpen) {
        const searchBar = document.getElementById('mobileSearchBar');
        if (searchBar) searchBar.classList.remove('show');
    }
}

document.addEventListener('click', function(event) { 
    const dropdown = document.getElementById('profileDropdown'); 
    const profileBtn = document.querySelector('.profile-btn'); 
    if (dropdown && profileBtn && !profileBtn.contains(event.target) && !dropdown.contains(event.target)) { 
        dropdown.classList.remove('show'); 
    } 
});

window.addEventListener('resize', function() { 
    if (window.innerWidth > 768) { 
        closeAllModals(); 
    } 
});

document.querySelectorAll('.accordion-toggle').forEach(button => {
    button.addEventListener('click', () => {
        const content = button.nextElementSibling;
        button.classList.toggle('active');
        if (content.style.maxHeight) { content.style.maxHeight = null; } 
        else { content.style.maxHeight = content.scrollHeight + "px"; }
    });
});

// YENİ EKLENEN KISIM: Çıkış yaptıktan sonra scroll konumunu geri yükle
document.addEventListener('click', function(e) {
    // Tıklanan element veya ebeveyni "logout.php" linki mi kontrol et
    const target = e.target.closest('a[href*="logout.php"]');
    
    if (target) {
        // Mevcut scroll pozisyonunu tarayıcı hafızasına kaydet
        sessionStorage.setItem('scrollPosition', window.scrollY);
    }
});

// Sayfa yüklendiğinde kayıtlı konum var mı diye bak
window.addEventListener('load', function() {
    const scrollPos = sessionStorage.getItem('scrollPosition');
    
    // Eğer kayıtlı bir pozisyon varsa oraya git ve hafızayı temizle
    if (scrollPos) {
        window.scrollTo(0, parseInt(scrollPos));
        sessionStorage.removeItem('scrollPosition');
    }
});
</script>

</body>
</html>