<?php
require_once __DIR__ . '/../config/database.php';
$conn = getDbConnection();

$r1 = $conn->query("SELECT COUNT(*) as c FROM library_books");
echo "Total books: " . $r1->fetch_assoc()['c'] . "\n";

$r2 = $conn->query("SELECT COUNT(*) as c FROM quiz_questions");
echo "Total quiz questions: " . $r2->fetch_assoc()['c'] . "\n";

$r3 = $conn->query("SELECT id, title, page_count, pdf_path FROM library_books LIMIT 5");
while ($row = $r3->fetch_assoc()) {
    echo "Book {$row['id']}: {$row['title']} | Pages: {$row['page_count']} | PDF: {$row['pdf_path']}\n";
}

