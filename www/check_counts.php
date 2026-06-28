<?php
require 'includes/db.php';
echo "Items: " . $pdo->query("SELECT COUNT(*) FROM items")->fetchColumn() . "\n";
echo "Requisitions: " . $pdo->query("SELECT COUNT(*) FROM requisitions")->fetchColumn() . "\n";
