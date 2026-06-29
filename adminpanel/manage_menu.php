<?php
/**
 * Blogium - Menü Yönetimi v3.0
 * Sürükle-Bırak Destekli Modern Tasarım
 */

// --- OTURUM SÜRESİ AYARI (1 SAAT) ---
ini_set('session.gc_maxlifetime', 3600); // Sunucu tarafında oturumu 1 saat (3600 sn) tut
session_set_cookie_params(3600); // Tarayıcı çerezini 1 saat tut

session_start();

// Eğer son işlemden bu yana 1 saat (3600 saniye) geçtiyse oturumu kapat
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 3600)) {
    session_unset();     // Değişkenleri temizle
    session_destroy();   // Oturumu yok et
    header("Location: index.php?timeout=1"); // Giriş sayfasına yönlendir
    exit;
}

// Son işlem zamanını şu an olarak güncelle
$_SESSION['LAST_ACTIVITY'] = time();
// ------------------------------------
if (!isset($_SESSION['admin_logged_in'])) { header("Location: index.php"); exit; }
require 'includes/db.php';

// MESAJLARI AL
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// MENÜLERİ ÇEK
try {
    // order_index sütunu varsa ona göre, yoksa id'ye göre sırala
    $menus = $pdo->query("SELECT * FROM header_menu ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $menus = []; }

// KATEGORİLERİ ÇEK (Link eklerken kolaylık olsun diye)
try {
    $categories = $pdo->query("SELECT name, slug FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $categories = []; }

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <?php include 'includes/head.php'; ?>
    <style>
        /* Bu sayfaya özel stiller */
        .dash-content-grid {
            display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;
        }
        @media (max-width: 992px) {
            .dash-content-grid { grid-template-columns: 1fr; }
            .form-column { order: -1; margin-bottom: 20px; }
        }

        /* Liste Elemanları */
        .menu-list-item {
            background: #fff;
            border-bottom: 1px solid var(--border-color);
            padding: 15px;
            display: flex; align-items: center; gap: 15px;
            transition: background 0.2s;
        }
        .menu-list-item:last-child { border-bottom: none; }
        .menu-list-item:hover { background: #f8fafc; }
        
        .drag-handle {
            color: #cbd5e1; cursor: grab; font-size: 1.2rem;
            display: flex; align-items: center; padding: 5px;
        }
        .drag-handle:hover { color: var(--primary); }
        
        .menu-info { flex-grow: 1; }
        .menu-title { font-weight: 600; color: var(--text-main); font-size: 1rem; }
        .menu-url { font-family: monospace; color: var(--secondary); font-size: 0.85rem; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; }
        
        .menu-actions { display: flex; gap: 5px; }

        /* Inline Edit Formu */
        .edit-form-wrapper {
            display: none; background: #f1f5f9; padding: 15px; border-bottom: 1px solid var(--border-color);
        }
        .edit-grid { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        
        /* Sürükleme Efektleri */
        .dragging { opacity: 0.5; background: #eef2ff; border: 1px dashed var(--primary); }
    </style>
</head>
<body>

    <?php include 'includes/menu.php'; ?>

    <div class="main-content">
        
        <div class="page-header">
            <div>
                <h1 class="page-title">Menü Yönetimi</h1>
                <p class="text-muted">Üst menü linklerini düzenleyin ve sıralayın.</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert <?= $message_type == 'success' ? 'badge-success' : 'alert-error' ?>" style="padding:15px; margin-bottom:20px; border-radius:8px; background-color: #dcfce7; color: #166534;">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="dash-content-grid">
            
            <div class="list-column">
                <div class="card">
                    <div class="card-header">
                        <h3>Mevcut Menü (<?= count($menus) ?>)</h3>
                    </div>
                    <div class="card-body" style="padding:0;" id="menuContainer">
                        <?php if (empty($menus)): ?>
                            <div class="text-center p-5 text-muted">
                                <i class="fas fa-bars" style="font-size:3rem; margin-bottom:15px; opacity:0.3;"></i>
                                <p>Menüde hiç eleman yok.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($menus as $menu): ?>
                                <div class="menu-wrapper" data-id="<?= $menu['id'] ?>">
                                    <div class="menu-list-item" id="view-<?= $menu['id'] ?>" draggable="true">
                                        <div class="drag-handle"><i class="fas fa-grip-vertical"></i></div>
                                        <div class="menu-info">
                                            <div class="menu-title"><?= htmlspecialchars($menu['title']) ?></div>
                                            <span class="menu-url"><?= htmlspecialchars($menu['url']) ?></span>
                                        </div>
                                        <div class="menu-actions">
                                            <button class="btn btn-sm btn-secondary edit-btn" data-id="<?= $menu['id'] ?>">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                            <a href="delete_menu.php?id=<?= $menu['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Silmek istediğinize emin misiniz?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>

                                    <div class="edit-form-wrapper" id="edit-<?= $menu['id'] ?>">
                                        <form action="update_menu.php" method="POST" class="edit-grid">
                                            <input type="hidden" name="id" value="<?= $menu['id'] ?>">
                                            <input type="text" name="title" class="form-control form-control-sm" value="<?= htmlspecialchars($menu['title']) ?>" style="flex:1;" required>
                                            <input type="text" name="url" class="form-control form-control-sm" value="<?= htmlspecialchars($menu['url']) ?>" style="flex:2;" required>
                                            <button type="submit" class="btn btn-sm btn-primary">Kaydet</button>
                                            <button type="button" class="btn btn-sm btn-secondary cancel-btn" data-id="<?= $menu['id'] ?>">İptal</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="text-muted text-center text-sm mt-2">
                    <i class="fas fa-info-circle"></i> Sıralamayı değiştirmek için öğeleri sürükleyip bırakabilirsiniz.
                </p>
            </div>

            <div class="form-column">
                <div class="card" style="position:sticky; top:20px;">
                    <div class="card-header">
                        <h3><i class="fas fa-plus-circle"></i> Yeni Ekle</h3>
                    </div>
                    <div class="card-body">
                        <form action="insert_menu.php" method="POST">
                            <div class="form-group">
                                <label class="form-label">Başlık</label>
                                <input type="text" name="title" class="form-control" placeholder="Örn: Hakkımızda" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Bağlantı Türü</label>
                                <div style="display:flex; gap:10px; margin-bottom:10px;">
                                    <button type="button" class="btn btn-sm btn-secondary w-100 active" id="typeManual">Manuel</button>
                                    <button type="button" class="btn btn-sm btn-secondary w-100" id="typeCat">Kategori</button>
                                </div>
                            </div>

                            <div class="form-group" id="groupManual">
                                <label class="form-label">URL</label>
                                <input type="text" name="url" id="manualUrl" class="form-control" placeholder="https:// veya /sayfa">
                            </div>

                            <div class="form-group" id="groupCat" style="display:none;">
                                <label class="form-label">Kategori Seç</label>
                                <select id="catSelect" class="form-control">
                                    <option value="">Seçiniz...</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="/kategori/<?= htmlspecialchars($cat['slug']) ?>">
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary" style="width:100%;">Menüye Ekle</button>
                        </form>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        
        // --- TÜR SEÇİMİ (Manuel / Kategori) ---
        const btnManual = document.getElementById('typeManual');
        const btnCat = document.getElementById('typeCat');
        const groupManual = document.getElementById('groupManual');
        const groupCat = document.getElementById('groupCat');
        const manualUrl = document.getElementById('manualUrl');
        const catSelect = document.getElementById('catSelect');

        btnManual.addEventListener('click', function() {
            groupManual.style.display = 'block';
            groupCat.style.display = 'none';
            btnManual.classList.add('active'); // active stili CSS'de olmalı, yoksa btn-primary yapılabilir
            btnManual.style.borderColor = 'var(--primary)';
            btnCat.style.borderColor = 'var(--border-color)';
        });

        btnCat.addEventListener('click', function() {
            groupManual.style.display = 'none';
            groupCat.style.display = 'block';
            btnCat.style.borderColor = 'var(--primary)';
            btnManual.style.borderColor = 'var(--border-color)';
        });

        // Kategori seçilince URL inputuna yaz
        catSelect.addEventListener('change', function() {
            if (this.value) {
                manualUrl.value = this.value;
            }
        });

        // --- INLINE EDIT ---
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                document.getElementById('view-' + id).style.display = 'none';
                document.getElementById('edit-' + id).style.display = 'block';
            });
        });

        document.querySelectorAll('.cancel-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                document.getElementById('edit-' + id).style.display = 'none';
                document.getElementById('view-' + id).style.display = 'flex';
            });
        });

        // --- BASİT SÜRÜKLE BIRAK (DRAG & DROP) ---
        const container = document.getElementById('menuContainer');
        let draggedItem = null;

        document.querySelectorAll('.menu-list-item[draggable="true"]').forEach(item => {
            item.addEventListener('dragstart', function(e) {
                draggedItem = this;
                setTimeout(() => this.classList.add('dragging'), 0);
            });
            item.addEventListener('dragend', function() {
                this.classList.remove('dragging');
                draggedItem = null;
                // Burada AJAX ile yeni sıralamayı veritabanına gönderebilirsiniz.
            });
        });

        container.addEventListener('dragover', function(e) {
            e.preventDefault();
            const afterElement = getDragAfterElement(container, e.clientY);
            const draggable = document.querySelector('.dragging');
            if (draggable) {
                // Sürüklenen eleman 'menu-wrapper' içinde olduğu için wrapper'ı taşıyoruz
                const wrapper = draggable.parentElement; 
                if (afterElement == null) {
                    container.appendChild(wrapper);
                } else {
                    container.insertBefore(wrapper, afterElement);
                }
            }
        });

        function getDragAfterElement(container, y) {
            // Sadece .menu-wrapper class'ına sahip elemanları dikkate al
            const draggableElements = [...container.querySelectorAll('.menu-wrapper:not(:has(.dragging))')];

            return draggableElements.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                } else {
                    return closest;
                }
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        }

    });
    </script>

</body>
</html>