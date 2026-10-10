<?php
/**
 * api/serve_pdf.php — High-Performance Secure PDF Streaming Endpoint
 * Authenticates user, looks up pdf_path from DB by book_id, streams the file with:
 * - HTTP Byte-Range support (progressive page-by-page loading)
 * - ETag and Last-Modified conditional caching (304 Not Modified)
 * - Instant binary transfer (eliminates Base64 memory expansion and serialization lag)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
$conn   = getDbConnection();
$userId = (int)$_SESSION['user_id'];
$bookId = (int)($_GET['book_id'] ?? 0);

if ($bookId < 1) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid book_id']);
    exit;
}

// Look up pdf_path from DB
$stmt = $conn->prepare('SELECT pdf_path, title FROM library_books WHERE id = ? AND published_status = 1');
$stmt->bind_param('i', $bookId);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$book) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Book not found or not published']);
    exit;
}

// Construct safe path using basename() to strip any directory traversal
$safeName = basename($book['pdf_path']);
$pdfPath  = __DIR__ . '/../books/' . $safeName;

if (!file_exists($pdfPath) || !is_readable($pdfPath)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'PDF file not found on disk', 'path' => $safeName]);
    exit;
}

// Non-blocking gamification activity update
$conn->query("UPDATE gamification_profiles SET last_activity_date = CURDATE() WHERE user_id = $userId");

// Check format
$format = $_GET['format'] ?? '';
if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, max-age=3600');
    $content = file_get_contents($pdfPath);
    echo json_encode([
        'success'    => true,
        'book_id'    => $bookId,
        'title'      => $book['title'],
        'pdf_base64' => base64_encode($content)
    ]);
    exit;
}

// File metadata & Cache validation
$fileTime = filemtime($pdfPath);
$fileSize = filesize($pdfPath);
$etag     = '"' . md5($fileTime . '-' . $fileSize . '-' . $bookId) . '"';

header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $fileTime) . ' GMT');
header('Cache-Control: private, max-age=86400, stale-while-revalidate=604800');
header('Accept-Ranges: bytes');
header('X-Content-Type-Options: nosniff');
header('Content-Type: application/pdf');

// 304 Not Modified check
if (
    (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) ||
    (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $fileTime)
) {
    http_response_code(304);
    exit;
}

// Clear output buffers to prevent memory spikes
if (ob_get_level()) {
    ob_end_clean();
}

// Handle HTTP Byte-Range requests for fast progressive page rendering
if (isset($_SERVER['HTTP_RANGE'])) {
    $range = $_SERVER['HTTP_RANGE'];
    list(, $range) = explode('=', $range, 2);
    list($start, $end) = explode('-', $range, 2);
    $start  = (int)$start;
    $end    = ($end === '') ? $fileSize - 1 : (int)$end;
    $length = $end - $start + 1;

    http_response_code(206);
    header("Content-Range: bytes $start-$end/$fileSize");
    header("Content-Length: $length");

    $fp = fopen($pdfPath, 'rb');
    fseek($fp, $start);
    echo fread($fp, $length);
    fclose($fp);
} else {
    header('Content-Length: ' . $fileSize);
    readfile($pdfPath);
}
exit;
