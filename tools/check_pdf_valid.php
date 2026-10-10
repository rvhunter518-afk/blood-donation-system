<?php
$pdf = file_get_contents('books/book_001.pdf');
echo "Size: " . strlen($pdf) . " bytes\n";
echo "Header: " . substr($pdf, 0, 8) . "\n";
echo "Tail: " . substr($pdf, -30) . "\n";

