<?php
/**
 * Auth Check API for B.E.N.T.A
 * Business Expense and Net Transaction Analyzer
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../includes/auth.php';

$auth = new Auth();

if ($auth->isLoggedIn()) {
    $user = $auth->getCurrentUser();
    echo json_encode([
        'authenticated' => true,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email']
        ]
    ]);
} else {
    echo json_encode([
        'authenticated' => false,
        'message' => 'Not authenticated'
    ]);
}
?>
