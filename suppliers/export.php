<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
if (!isAdmin()) { 
    header('Location: ' . BASE_URL . '/dashboard/index.php'); 
    exit; 
}

$db = getDB();
$suppliers = $db->query('SELECT * FROM suppliers ORDER BY created_at DESC')->fetchAll();

// Set headers to trigger file download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="suppliers_' . date('Y-m-d_His') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Write the CSV header row
fputcsv($output, ['ID', 'Name', 'Email', 'Website', 'Contacts Info', 'Added Date']);

// Write data rows
foreach ($suppliers as $s) {
    // Format contacts nicely into a readable string
    $contacts = json_decode($s['contacts'] ?? '[]', true) ?: [];
    $contactStrings = [];
    foreach ($contacts as $c) {
        $name = $c['name'] ?? 'N/A';
        $phone = $c['phone'] ?? 'N/A';
        $contactStrings[] = "$name ($phone)";
    }
    
    fputcsv($output, [
        $s['id'],
        $s['name'],
        $s['email'],
        $s['website'],
        implode(' | ', $contactStrings),
        date('Y-m-d', strtotime($s['created_at']))
    ]);
}

fclose($output);
exit;
