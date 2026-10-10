<?php
$conn = new mysqli('localhost', 'root', '', 'blood_connect');
$conn->query("ALTER TABLE quiz_questions ADD COLUMN IF NOT EXISTS question_text TEXT NULL");
$conn->query("ALTER TABLE quiz_questions ADD COLUMN IF NOT EXISTS points INT NOT NULL DEFAULT 10");
echo "quiz_questions table altered successfully.\n";

