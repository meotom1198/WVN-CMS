<?php
// /public_html/install.php
session_start();

// Kiểm tra nếu đã cài đặt
if (file_exists(__DIR__ . '/app/config/installed.lock')) {
    die('WVN CMS đã được cài đặt. Vui lòng xóa tệp <code>app/config/installed.lock</code> để cài đặt lại.');
}

// Xác định bước hiện tại
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Kiểm tra hệ thống (chạy ở tất cả các bước)
$requirements = [
    'php_version' => '7.4.0',
    'extensions' => ['pdo_mysql', 'mbstring', 'openssl'],
    'writable_dirs' => [
        __DIR__ . '/app/config/' => 'Thư mục app/config/ cần có quyền ghi để tạo tệp cấu hình.',
    ],
];

$errors = [];
$checks = [];

// Kiểm tra phiên bản PHP
if (version_compare(PHP_VERSION, $requirements['php_version'], '<')) {
    $errors[] = "Phiên bản PHP hiện tại (" . PHP_VERSION . ") không đủ. Yêu cầu tối thiểu: " . $requirements['php_version'];
} else {
    $checks[] = "Phiên bản PHP: " . PHP_VERSION . " (Đạt yêu cầu)";
}

// Kiểm tra extensions
foreach ($requirements['extensions'] as $ext) {
    if (!extension_loaded($ext)) {
        $errors[] = "Extension PHP <code>$ext</code> không được kích hoạt.";
    } else {
        $checks[] = "Extension <code>$ext</code>: Đã kích hoạt";
    }
}

// Kiểm tra quyền thư mục (chỉ kiểm tra /app/config/ ở đây, /logs/ sẽ kiểm tra ở bước 2)
foreach ($requirements['writable_dirs'] as $dir => $message) {
    if (!is_writable($dir)) {
        $errors[] = $message . " (Hiện tại không có quyền ghi)";
    } else {
        $checks[] = "Thư mục <code>$dir</code>: Có quyền ghi";
    }
}

// Bước 2: Kiểm tra và yêu cầu thiết lập quyền thư mục
if ($step == 2) {
    $permission_errors = [];
    
    // Kiểm tra thư mục /app/config/
    $config_dir = __DIR__ . '/app/config/';
    if (!is_dir($config_dir)) {
        $permission_errors[] = "Thư mục <code>app/config/</code> không tồn tại. Vui lòng tạo thư mục này.";
    } elseif (!is_writable($config_dir)) {
        $permission_errors[] = "Thư mục <code>app/config/</code> cần quyền <strong>755</strong> (hoặc 775/777 tùy vào máy chủ). Hiện tại không có quyền ghi.";
    } else {
        $checks[] = "Thư mục <code>app/config/</code>: Quyền hợp lệ (có quyền ghi).";
    }

    // Kiểm tra thư mục /logs/
    $logs_dir = __DIR__ . '/logs/';
    if (!is_dir($logs_dir)) {
        $permission_errors[] = "Thư mục <code>logs/</code> không tồn tại. Vui lòng tạo thư mục này.";
    } elseif (!is_writable($logs_dir)) {
        $permission_errors[] = "Thư mục <code>logs/</code> cần quyền <strong>777</strong>. Hiện tại không có quyền ghi.";
    } else {
        $checks[] = "Thư mục <code>logs/</code>: Quyền hợp lệ (có quyền ghi).";
    }

    // Nếu có lỗi quyền, không cho phép tiếp tục
    if (!empty($permission_errors)) {
        $errors = array_merge($errors, $permission_errors);
    }
}

// Xử lý từng bước
if (empty($errors)) {
    // Bước 3: Xử lý thông tin database (trước đây là bước 2)
    if ($step == 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $db_host = $_POST['db_host'] ?? '';
        $db_name = $_POST['db_name'] ?? '';
        $db_user = $_POST['db_user'] ?? '';
        $db_pass = $_POST['db_pass'] ?? '';

        if (empty($db_host) || empty($db_name) || empty($db_user)) {
            $error = 'Vui lòng nhập đầy đủ thông tin cơ sở dữ liệu.';
        } else {
            try {
                $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Lưu thông tin database vào session
                $_SESSION['db_config'] = [
                    'host' => $db_host,
                    'dbname' => $db_name,
                    'username' => $db_user,
                    'password' => $db_pass,
                ];

                // Chuyển sang bước 4
                header('Location: install.php?step=4');
                exit;
            } catch (PDOException $e) {
                $error = 'Lỗi kết nối cơ sở dữ liệu: ' . $e->getMessage();
            }
        }
    }

    // Bước 4: Xử lý thông tin cài đặt (trước đây là bước 3)
    if ($step == 4 && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = $_POST['title'] ?? '';
        $keywords = $_POST['keywords'] ?? '';
        $description = $_POST['description'] ?? '';
        $timezone = $_POST['timezone'] ?? 'Asia/Ho_Chi_Minh';

        if (empty($title) || empty($keywords) || empty($description)) {
            $error = 'Vui lòng nhập đầy đủ thông tin.';
        } else {
            // Lưu thông tin cài đặt vào session
            $_SESSION['settings'] = [
                'title' => $title,
                'keywords' => $keywords,
                'description' => $description,
                'timezone' => $timezone,
            ];

            // Chuyển sang bước 5
            header('Location: install.php?step=5');
            exit;
        }
    }

    // Bước 5: Xử lý thông tin tài khoản admin (trước đây là bước 4)
    if ($step == 5 && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $admin_user = $_POST['admin_user'] ?? '';
        $admin_pass = $_POST['admin_pass'] ?? '';
        $admin_confirm_pass = $_POST['admin_confirm_pass'] ?? '';
        $admin_email = $_POST['admin_email'] ?? '';

        // Kiểm tra thông tin
        if (empty($admin_user) || empty($admin_pass) || empty($admin_confirm_pass) || empty($admin_email)) {
            $error = 'Vui lòng nhập đầy đủ thông tin tài khoản admin.';
        } elseif ($admin_pass !== $admin_confirm_pass) {
            $error = 'Mật khẩu và xác nhận mật khẩu không khớp.';
        } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email không hợp lệ.';
        } else {
            try {
                // Lấy thông tin database từ session
                if (!isset($_SESSION['db_config']) || !isset($_SESSION['settings'])) {
                    header('Location: install.php?step=3');
                    exit;
                }
                $db_config = $_SESSION['db_config'];
                $settings = $_SESSION['settings'];

                $pdo = new PDO("mysql:host={$db_config['host']};dbname={$db_config['dbname']}", $db_config['username'], $db_config['password']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Tạo các bảng
                $sql = "
                    CREATE TABLE IF NOT EXISTS users (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        username VARCHAR(50) NOT NULL UNIQUE,
                        password VARCHAR(255) NOT NULL,
                        email VARCHAR(100) NOT NULL UNIQUE,
                        role ENUM('admin', 'user') DEFAULT 'user',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    );
                    CREATE TABLE IF NOT EXISTS posts (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        title VARCHAR(255) NOT NULL,
                        content TEXT NOT NULL,
                        category_id INT,
                        parent_category_id INT,
                        user_id INT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id)
                    );
                    CREATE TABLE IF NOT EXISTS categories (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(100) NOT NULL,
                        parent_id INT,
                        FOREIGN KEY (parent_id) REFERENCES categories(id)
                    );
                    CREATE TABLE IF NOT EXISTS comments (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        post_id INT NOT NULL,
                        user_id INT NOT NULL,
                        content TEXT NOT NULL,
                        is_approved BOOLEAN DEFAULT FALSE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (post_id) REFERENCES posts(id),
                        FOREIGN KEY (user_id) REFERENCES users(id)
                    );
                    CREATE TABLE IF NOT EXISTS messages (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        sender_id INT NOT NULL,
                        recipient_id INT NOT NULL,
                        content TEXT NOT NULL,
                        is_read BOOLEAN DEFAULT FALSE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (sender_id) REFERENCES users(id),
                        FOREIGN KEY (recipient_id) REFERENCES users(id)
                    );
                    CREATE TABLE IF NOT EXISTS settings (
                        `key` VARCHAR(50) PRIMARY KEY,
                        value TEXT NOT NULL
                    );
                ";
                $pdo->exec($sql);

                // Thêm tài khoản admin
                $hashed_pass = password_hash($admin_pass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'admin')");
                $stmt->execute([$admin_user, $hashed_pass, $admin_email]);

                // Thêm cài đặt mặc định
                $stmt = $pdo->prepare("INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
                $stmt->execute(['title', $settings['title'], $settings['title']]);
                $stmt->execute(['keywords', $settings['keywords'], $settings['keywords']]);
                $stmt->execute(['description', $settings['description'], $settings['description']]);
                $stmt->execute(['timezone', $settings['timezone'], $settings['timezone']]);
                $stmt->execute(['theme_mode', 'light', 'light']);

                // Ghi tệp cấu hình database.php
                $config_content = "<?php
return [
    'host' => '{$db_config['host']}',
    'dbname' => '{$db_config['dbname']}',
    'username' => '{$db_config['username']}',
    'password' => '{$db_config['password']}'
];";
                file_put_contents(__DIR__ . '/app/config/database.php', $config_content);

                // Đánh dấu đã cài đặt
                file_put_contents(__DIR__ . '/app/config/installed.lock', 'Installed on ' . date('Y-m-d H:i:s'));

                // Lưu thông tin admin vào session để hiển thị ở bước 6
                $_SESSION['admin_info'] = [
                    'username' => $admin_user,
                    'email' => $admin_email,
                ];

                // Chuyển sang bước 6
                header('Location: install.php?step=6');
                exit;
            } catch (PDOException $e) {
                $error = 'Lỗi trong quá trình cài đặt: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Cài đặt WVN CMS - Bước <?php echo $step; ?></title>
    <link href="/assets/bootstrap.min.css" rel="stylesheet">
    <style>
        .container { max-width: 800px; margin: 50px auto; }
        .alert { color: red; }
        .success { color: green; }
        .requirements { margin-bottom: 20px; }
        .steps { margin-bottom: 20px; }
        .steps span { padding: 5px 10px; margin-right: 10px; border-radius: 5px; }
        .steps .current { background-color: #007bff; color: white; }
        .steps .completed { background-color: #28a745; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Cài đặt WVN CMS</h2>

        <!-- Hiển thị các bước -->
        <div class="steps">
            <span class="<?php echo $step >= 1 ? 'completed' : ''; ?> <?php echo $step == 1 ? 'current' : ''; ?>">Bước 1: Giới thiệu</span>
            <span class="<?php echo $step >= 2 ? 'completed' : ''; ?> <?php echo $step == 2 ? 'current' : ''; ?>">Bước 2: Quyền thư mục</span>
            <span class="<?php echo $step >= 3 ? 'completed' : ''; ?> <?php echo $step == 3 ? 'current' : ''; ?>">Bước 3: Cơ sở dữ liệu</span>
            <span class="<?php echo $step >= 4 ? 'completed' : ''; ?> <?php echo $step == 4 ? 'current' : ''; ?>">Bước 4: Cài đặt</span>
            <span class="<?php echo $step >= 5 ? 'completed' : ''; ?> <?php echo $step == 5 ? 'current' : ''; ?>">Bước 5: Tài khoản Admin</span>
            <span class="<?php echo $step >= 6 ? 'completed' : ''; ?> <?php echo $step == 6 ? 'current' : ''; ?>">Bước 6: Hoàn tất</span>
        </div>

        <!-- Kiểm tra yêu cầu hệ thống -->
        <div class="requirements">
            <h4>Kiểm tra yêu cầu hệ thống</h4>
            <?php if (!empty($errors)): ?>
                <div class="alert">
                    <strong>Có lỗi:</strong>
                    <ul>
                        <?php foreach ($errors as $err): ?>
                            <li><?php echo $err; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <div class="success">
                    <strong>Đạt yêu cầu:</strong>
                    <ul>
                        <?php foreach ($checks as $check): ?>
                            <li><?php echo $check; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($errors)): ?>
            <?php if (isset($error)): ?>
                <div class="alert"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($step == 1): ?>
                <!-- Bước 1: Chào hỏi và giới thiệu -->
                <h4>Bước 1: Chào mừng bạn đến với WVN CMS</h4>
                <p>
                    WVN CMS là một hệ thống quản lý nội dung đơn giản, nhẹ nhàng, được thiết kế để giúp bạn dễ dàng tạo và quản lý blog cá nhân hoặc trang tin tức. Phiên bản 0.1.1 hiện hỗ trợ các tính năng cơ bản như quản lý bài viết, danh mục, bình luận, nhắn tin thời gian thực (với Pusher), và chế độ sáng/tối.
                </p>
                <p>
                    Quá trình cài đặt sẽ bao gồm 6 bước:
                    <ul>
                        <li>Bước 1: Giới thiệu về WVN CMS (bạn đang ở đây).</li>
                        <li>Bước 2: Cấu hình quyền thư mục.</li>
                        <li>Bước 3: Cấu hình kết nối cơ sở dữ liệu.</li>
                        <li>Bước 4: Thiết lập thông tin trang web.</li>
                        <li>Bước 5: Tạo tài khoản admin.</li>
                        <li>Bước 6: Hoàn tất cài đặt.</li>
                    </ul>
                </p>
                <a href="install.php?step=2" class="btn btn-primary">Tiếp theo</a>

            <?php elseif ($step == 2): ?>
                <!-- Bước 2: Cấu hình quyền thư mục -->
                <h4>Bước 2: Cấu hình quyền thư mục</h4>
                <p>Để WVN CMS hoạt động chính xác, bạn cần thiết lập quyền truy cập cho các thư mục sau:</p>
                <ul>
                    <li><strong>app/config/</strong>: Cần quyền <strong>755</strong> (hoặc 775/777 tùy vào máy chủ) để tạo tệp cấu hình.</li>
                    <li><strong>logs/</strong>: Cần quyền <strong>777</strong> để ghi log các yêu cầu API.</li>
                </ul>
                <p><strong>Hướng dẫn thiết lập quyền:</strong></p>
                <ol>
                    <li>Truy cập vào File Manager của máy chủ (hoặc sử dụng SSH/FTP).</li>
                    <li>Điều hướng đến thư mục gốc của WVN CMS (thường là <code>/public_html/</code>).</li>
                    <li>Đảm bảo các thư mục <code>app/config/</code> và <code>logs/</code> đã tồn tại. Nếu chưa, hãy tạo chúng:
                        <ul>
                            <li><code>mkdir app/config</code></li>
                            <li><code>mkdir logs</code></li>
                        </ul>
                    </li>
                    <li>Thiết lập quyền:
                        <ul>
                            <li>Đối với <code>app/config/</code>: Đặt quyền <strong>755</strong> (hoặc 775/777 nếu cần).</li>
                            <li>Đối với <code>logs/</code>: Đặt quyền <strong>777</strong>.</li>
                        </ul>
                        Nếu sử dụng SSH, bạn có thể chạy các lệnh sau:
                        <pre>
chmod 755 app/config/
chmod 777 logs/
                        </pre>
                    </li>
                    <li>Nhấn nút "Kiểm tra và tiếp tục" để xác nhận quyền đã được thiết lập đúng.</li>
                </ol>
                <a href="install.php?step=3" class="btn btn-primary">Kiểm tra và tiếp tục</a>

            <?php elseif ($step == 3): ?>
                <!-- Bước 3: Nhập thông tin database (trước đây là bước 2) -->
                <h4>Bước 3: Cấu hình cơ sở dữ liệu</h4>
                <p>Vui lòng nhập thông tin kết nối cơ sở dữ liệu MySQL. Bạn cần tạo sẵn một cơ sở dữ liệu trống trước khi tiếp tục.</p>
                <form method="post">
                    <div class="mb-3">
                        <label for="db_host" class="form-label">Host</label>
                        <input type="text" class="form-control" id="db_host" name="db_host" value="localhost" required>
                    </div>
                    <div class="mb-3">
                        <label for="db_name" class="form-label">Tên cơ sở dữ liệu</label>
                        <input type="text" class="form-control" id="db_name" name="db_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="db_user" class="form-label">Tên người dùng</label>
                        <input type="text" class="form-control" id="db_user" name="db_user" required>
                    </div>
                    <div class="mb-3">
                        <label for="db_pass" class="form-label">Mật khẩu</label>
                        <input type="password" class="form-control" id="db_pass" name="db_pass">
                    </div>
                    <button type="submit" class="btn btn-primary">Tiếp theo</button>
                </form>

            <?php elseif ($step == 4): ?>
                <!-- Bước 4: Nhập thông tin cài đặt (trước đây là bước 3) -->
                <h4>Bước 4: Thiết lập thông tin trang web</h4>
                <p>Vui lòng nhập thông tin cơ bản cho trang web.</p>
                <form method="post">
                    <div class="mb-3">
                        <label for="title" class="form-label">Tiêu đề trang web</label>
                        <input type="text" class="form-control" id="title" name="title" value="WVN CMS" required>
                    </div>
                    <div class="mb-3">
                        <label for="keywords" class="form-label">Từ khóa (SEO)</label>
                        <input type="text" class="form-control" id="keywords" name="keywords" value="blog, CMS, WVN CMS, tin tức" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Mô tả (SEO)</label>
                        <textarea class="form-control" id="description" name="description" required>WVN CMS - Hệ thống quản lý nội dung đơn giản và hiệu quả.</textarea>
                    </div>
                    <div class="mb-3">
                        <label for="timezone" class="form-label">Múi giờ</label>
                        <select class="form-control" id="timezone" name="timezone" required>
                            <option value="Asia/Ho_Chi_Minh" selected>GMT+7 (Hà Nội)</option>
                            <option value="UTC">UTC</option>
                            <option value="Asia/Bangkok">GMT+7 (Bangkok)</option>
                            <option value="Asia/Singapore">GMT+8 (Singapore)</option>
                            <option value="America/New_York">GMT-5 (New York)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Tiếp theo</button>
                </form>

            <?php elseif ($step == 5): ?>
                <!-- Bước 5: Nhập thông tin tài khoản admin (trước đây là bước 4) -->
                <h4>Bước 5: Tạo tài khoản Admin</h4>
                <p>Vui lòng nhập thông tin tài khoản admin để quản trị hệ thống.</p>
                <form method="post">
                    <div class="mb-3">
                        <label for="admin_user" class="form-label">Tên đăng nhập Admin</label>
                        <input type="text" class="form-control" id="admin_user" name="admin_user" required>
                    </div>
                    <div class="mb-3">
                        <label for="admin_pass" class="form-label">Mật khẩu Admin</label>
                        <input type="password" class="form-control" id="admin_pass" name="admin_pass" required>
                    </div>
                    <div class="mb-3">
                        <label for="admin_confirm_pass" class="form-label">Xác nhận mật khẩu</label>
                        <input type="password" class="form-control" id="admin_confirm_pass" name="admin_confirm_pass" required>
                    </div>
                    <div class="mb-3">
                        <label for="admin_email" class="form-label">Email Admin</label>
                        <input type="email" class="form-control" id="admin_email" name="admin_email" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Tiếp theo</button>
                </form>

            <?php elseif ($step == 6): ?>
                <!-- Bước 6: Hoàn tất và hiển thị thông tin admin (trước đây là bước 5) -->
                <h4>Bước 6: Hoàn tất cài đặt</h4>
                <p>Chúc mừng bạn! WVN CMS đã được cài đặt thành công.</p>
                <p>Vui lòng ghi nhớ thông tin tài khoản admin của bạn:</p>
                <ul>
                    <li><strong>Tên đăng nhập:</strong> <?php echo htmlspecialchars($_SESSION['admin_info']['username']); ?></li>
                    <li><strong>Mật khẩu:</strong> [Đã được mã hóa - Vui lòng ghi nhớ mật khẩu bạn đã nhập]</li>
                    <li><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['admin_info']['email']); ?></li>
                </ul>
                <p>
                    <strong>Lưu ý:</strong> Để tăng cường bảo mật, vui lòng xóa hoặc đổi tên tệp <code>install.php</code>.
                </p>
                <a href="/?controller=Home&action=index" class="btn btn-primary">Về trang chủ</a>
                <a href="/?controller=Setting&action=index" class="btn btn-success">Vào trang quản trị</a>
            <?php endif; ?>
        <?php else: ?>
            <p>Vui lòng khắc phục các lỗi hệ thống trên trước khi tiếp tục cài đặt.</p>
        <?php endif; ?>
    </div>
</body>
</html>
