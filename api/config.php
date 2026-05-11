<?php
// config.php - Database connection for Vercel + Aiven MySQL
$uri = getenv('DATABASE_URL');
if (!$uri) {
    // For local testing only – uncomment the line below with your real Aiven URL (remove before commit)
    // putenv("DATABASE_URL=mysql://avnadmin:AVNS_wlW1w1dfNjBY1mJsnhn@mysql-38b4a38c-alumnidatabase02-d3bb.i.aivencloud.com:22843/defaultdb?ssl-mode=REQUIRED");
    // $uri = getenv('DATABASE_URL');
}

if (!$uri) {
    die('DATABASE_URL environment variable not set. Please configure it in Vercel dashboard.');
}

$fields = parse_url($uri);
$dbname = ltrim($fields['path'], '/');
$dsn = "mysql:host={$fields['host']};port={$fields['port']};dbname=$dbname;sslmode=required";

try {
    $pdo = new PDO($dsn, $fields['user'], $fields['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('DB connection error: ' . $e->getMessage());
    die('Unable to connect to database. Please try again later.');
}
?>