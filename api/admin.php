<?php
session_start();
require __DIR__ . '/config.php';

// Handle login from database
if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT id, username, password_hash, role, is_active FROM admins WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'];
            $updateStmt = $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$admin['id']]);
            header('Location: /api/admin.php');
            exit;
        } else {
            $login_error = 'Invalid username or password.';
        }
    } else {
        $login_error = 'Please enter username and password.';
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /api/admin.php');
    exit;
}

// Delete record (with confirmation handled by JS, but also ensure id is numeric)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM alumni WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: /api/admin.php?deleted=1");
    exit;
}

// Check login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Show login form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head><meta charset="UTF-8"><title>Admin Login - Alumni System</title>
    <style>body{font-family: 'Outfit',sans-serif; background:#f3f1ef; display:flex; justify-content:center; align-items:center; min-height:100vh; margin:0;}
    .login-card{background:white; padding:2rem; border-radius:1.5rem; box-shadow:0 20px 35px -12px rgba(0,0,0,0.15); width:100%; max-width:400px;}
    h2{margin-bottom:1.5rem; color:#1e293b;} input{width:100%; padding:12px; margin-bottom:1rem; border:1px solid #cbd5e1; border-radius:12px;}
    button{background:#0f172a; color:white; border:none; padding:12px; border-radius:40px; width:100%; font-weight:600; cursor:pointer;}
    .error{color:#b91c1c; margin-bottom:1rem;}</style>
    </head>
    <body><div class="login-card"><h2>Admin Access</h2><?php if (isset($login_error)) echo '<div class="error">' . htmlspecialchars($login_error) . '</div>'; ?>
    <form method="POST"><input type="text" name="username" placeholder="Username" required><input type="password" name="password" placeholder="Password" required><button type="submit" name="login">Login</button></form></div></body>
    </html>
    <?php
    exit;
}

// --- Filters and pagination ---
$selected_year = $_GET['year'] ?? '';
$selected_course = $_GET['course'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$count_sql = "SELECT COUNT(*) as total FROM alumni WHERE 1=1";
$params = [];
if (!empty($selected_year)) {
    $count_sql .= " AND year_graduated = ?";
    $params[] = $selected_year;
}
if (!empty($selected_course)) {
    $count_sql .= " AND course LIKE ?";
    $params[] = '%' . $selected_course . '%';
}
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

$data_sql = "SELECT * FROM alumni WHERE 1=1";
$params2 = [];
if (!empty($selected_year)) {
    $data_sql .= " AND year_graduated = ?";
    $params2[] = $selected_year;
}
if (!empty($selected_course)) {
    $data_sql .= " AND course LIKE ?";
    $params2[] = '%' . $selected_course . '%';
}
$data_sql .= " ORDER BY year_graduated DESC, last_name ASC LIMIT $limit OFFSET $offset";
$stmt2 = $pdo->prepare($data_sql);
$stmt2->execute($params2);
$alumni = $stmt2->fetchAll();

$course_stmt = $pdo->query("SELECT DISTINCT course FROM alumni ORDER BY course");
$courses = $course_stmt->fetchAll();
$year_stmt = $pdo->query("SELECT DISTINCT year_graduated FROM alumni ORDER BY year_graduated DESC");
$years = $year_stmt->fetchAll();
$total_stmt = $pdo->query("SELECT COUNT(*) as total FROM alumni");
$total_users = $total_stmt->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Alumni Registration</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Outfit', sans-serif; background: #f3f1ef; padding: 1rem; }
        .dashboard-container { max-width: 1400px; margin: 0 auto; }
        .header { background: white; border-radius: 1.5rem; padding: 1.5rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .header h1 { font-family: 'Caveat', cursive; font-size: 2rem; color: #1e293b; }
        .logout-btn { background: #dc2626; color: white; padding: 0.5rem 1.2rem; border-radius: 40px; text-decoration: none; font-weight: 500; }
        .stats-card { background: white; border-radius: 1.5rem; padding: 1.5rem; margin-bottom: 1.5rem; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .stats-number { font-size: 3rem; font-weight: 700; color: #7c3aed; }
        .filter-bar { background: white; border-radius: 1.5rem; padding: 1.5rem; margin-bottom: 1.5rem; display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label { display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.3rem; color: #475569; }
        .filter-group select { width: 100%; padding: 10px 12px; border: 1.5px solid #e2e8f0; border-radius: 12px; font-family: inherit; }
        .btn { background: #0f172a; color: white; border: none; padding: 10px 20px; border-radius: 40px; cursor: pointer; font-weight: 500; text-decoration: none; display: inline-block; }
        .btn-secondary { background: #64748b; }
        .btn-print { background: #10b981; }
        .btn-edit { background: #3b82f6; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; text-decoration: none; color: white; display: inline-block; }
        .btn-delete { background: #ef4444; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; text-decoration: none; color: white; display: inline-block; }
        .table-wrapper { background: white; border-radius: 1.5rem; overflow-x: auto; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; font-weight: 600; color: #1e293b; }
        tr:hover { background: #f1f5f9; }
        .pagination { display: flex; justify-content: center; gap: 0.5rem; margin-top: 1.5rem; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 8px 12px; border-radius: 8px; background: white; text-decoration: none; color: #1e293b; border: 1px solid #e2e8f0; }
        .pagination .active { background: #7c3aed; color: white; border-color: #7c3aed; }
        .action-buttons { display: flex; gap: 6px; flex-wrap: wrap; }
        @media print {
            body { background: white; padding: 0; }
            .header, .stats-card, .filter-bar, .pagination, .logout-btn, .btn-print, .btn-secondary, .no-print { display: none !important; }
            .table-wrapper { box-shadow: none; }
            th, td { border: 1px solid #ccc; }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <div class="header">
        <h1>🎓 Alumni Registration Dashboard</h1>
        <div><span style="margin-right: 1rem;">Welcome, <?= htmlspecialchars($_SESSION['admin_username']) ?> (<?= $_SESSION['admin_role'] ?>)</span><a href="?logout=1" class="logout-btn no-print">Logout</a></div>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div style="background: #d1fae5; color: #065f46; padding: 10px; border-radius: 8px; margin-bottom: 1rem;">Record deleted successfully.</div>
    <?php endif; ?>
    <?php if (isset($_GET['updated'])): ?>
        <div style="background: #d1fae5; color: #065f46; padding: 10px; border-radius: 8px; margin-bottom: 1rem;">Record updated successfully.</div>
    <?php endif; ?>

    <div class="stats-card"><div class="stats-number"><?= $total_users ?></div><div>Total Registered Alumni</div></div>

    <div class="filter-bar no-print">
        <div class="filter-group">
            <label>Filter by Year Graduated</label>
            <form method="GET" id="filterForm">
                <select name="year" onchange="this.form.submit()">
                    <option value="">All Years</option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?= $y['year_graduated'] ?>" <?= $selected_year == $y['year_graduated'] ? 'selected' : '' ?>><?= $y['year_graduated'] ?></option>
                    <?php endforeach; ?>
                </select>
        </div>
        <div class="filter-group">
            <label>Filter by Course</label>
                <select name="course" onchange="this.form.submit()">
                    <option value="">All Courses</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= htmlspecialchars($c['course']) ?>" <?= $selected_course == $c['course'] ? 'selected' : '' ?>><?= htmlspecialchars($c['course']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <div class="filter-group">
            <button class="btn btn-print" onclick="window.print()">🖨️ Print Report</button>
            <a href="?export=csv<?= $selected_year ? '&year='.$selected_year : '' ?><?= $selected_course ? '&course='.urlencode($selected_course) : '' ?>" class="btn btn-secondary" style="margin-left: 0.5rem;">📎 Export CSV</a>
        </div>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th><th>Last Name</th><th>First Name</th><th>Middle Name</th><th>Birthdate</th><th>Age</th><th>Gender</th>
                    <th>Civil Status</th><th>Address</th><th>Course</th><th>Year Graduated</th><th>Employment</th>
                    <th>Occupation</th><th>Company</th><th>Work Address</th><th>Years of Service</th><th>Recent Work</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($alumni) === 0): ?>
                    <tr><td colspan="18" style="text-align:center;">No alumni records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($alumni as $a): ?>
                        <tr>
                            <td><?= $a['id'] ?></td>
                            <td><?= htmlspecialchars($a['last_name']) ?></td>
                            <td><?= htmlspecialchars($a['first_name']) ?></td>
                            <td><?= htmlspecialchars($a['middle_name'] ?? '') ?></td>
                            <td><?= $a['birth_date'] ?></td>
                            <td><?= $a['age'] ?></td>
                            <td><?= $a['gender'] ?></td>
                            <td><?= $a['civil_status'] ?></td>
                            <td><?= htmlspecialchars($a['address']) ?></td>
                            <td><?= htmlspecialchars($a['course']) ?></td>
                            <td><?= $a['year_graduated'] ?></td>
                            <td><?= $a['employment_status'] ?></td>
                            <td><?= htmlspecialchars($a['occupation'] ?? '') ?></td>
                            <td><?= htmlspecialchars($a['company_agency'] ?? '') ?></td>
                            <td><?= htmlspecialchars($a['work_address'] ?? '') ?></td>
                            <td><?= $a['years_of_service'] ?></td>
                            <td><?= htmlspecialchars($a['recent_work'] ?? '') ?></td>
                            <td class="action-buttons">
                                <a href="edit_alumni.php?id=<?= $a['id'] ?>" class="btn-edit">✏️ Edit</a>
                                <a href="?delete=<?= $a['id'] ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this record?');">🗑️ Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination no-print">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?= $i ?>&year=<?= urlencode($selected_year) ?>&course=<?= urlencode($selected_course) ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php
// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $csv_sql = "SELECT * FROM alumni WHERE 1=1";
    $csv_params = [];
    if (!empty($selected_year)) {
        $csv_sql .= " AND year_graduated = ?";
        $csv_params[] = $selected_year;
    }
    if (!empty($selected_course)) {
        $csv_sql .= " AND course LIKE ?";
        $csv_params[] = '%' . $selected_course . '%';
    }
    $csv_sql .= " ORDER BY year_graduated DESC, last_name ASC";
    $csv_stmt = $pdo->prepare($csv_sql);
    $csv_stmt->execute($csv_params);
    $csv_data = $csv_stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=alumni_report_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID','Last Name','First Name','Middle Name','Birthdate','Age','Gender','Civil Status','Address','Course','Year Graduated','Employment Status','Occupation','Company','Work Address','Years of Service','Recent Work','Registered On']);
    foreach ($csv_data as $row) {
        fputcsv($output, [
            $row['id'],
            $row['last_name'],
            $row['first_name'],
            $row['middle_name'] ?? '',
            $row['birth_date'],
            $row['age'],
            $row['gender'],
            $row['civil_status'],
            $row['address'],
            $row['course'],
            $row['year_graduated'],
            $row['employment_status'],
            $row['occupation'] ?? '',
            $row['company_agency'] ?? '',
            $row['work_address'] ?? '',
            $row['years_of_service'],
            $row['recent_work'] ?? '',
            $row['created_at']
        ]);
    }
    fclose($output);
    exit;
}
?>
</body>
</html>