<?php
$conn = new mysqli('localhost','root','','blood_connect');
echo "library_books: "  . $conn->query('SELECT COUNT(*) c FROM library_books WHERE published_status=1')->fetch_assoc()['c'] . "\n";
echo "quiz_questions: " . $conn->query('SELECT COUNT(*) c FROM quiz_questions')->fetch_assoc()['c'] . "\n";
echo "badges: "         . $conn->query('SELECT COUNT(*) c FROM badges')->fetch_assoc()['c'] . "\n";
echo "book_progress: "  . ($conn->query('SELECT 1 FROM book_progress LIMIT 1') ? 'table_exists' : 'MISSING') . "\n";
echo "quiz_attempts: "  . ($conn->query('SELECT 1 FROM quiz_attempts LIMIT 1') ? 'table_exists' : 'MISSING') . "\n";
echo "user_badges: "    . ($conn->query('SELECT 1 FROM user_badges LIMIT 1') ? 'table_exists' : 'MISSING') . "\n";
echo "PDF book_001: "   . (file_exists(__DIR__.'/../books/book_001.pdf') ? filesize(__DIR__.'/../books/book_001.pdf').' bytes' : 'MISSING') . "\n";
echo "PDF book_105: "   . (file_exists(__DIR__.'/../books/book_105.pdf') ? filesize(__DIR__.'/../books/book_105.pdf').' bytes' : 'MISSING') . "\n";

$sample = $conn->query("SELECT id, title, category, difficulty, page_count, pdf_path FROM library_books ORDER BY RAND() LIMIT 3")->fetch_all(MYSQLI_ASSOC);
echo "\nSample books:\n";
foreach ($sample as $b) { echo "  [{$b['id']}] {$b['title']} | {$b['category']} | {$b['difficulty']} | pages:{$b['page_count']} | pdf:{$b['pdf_path']}\n"; }

