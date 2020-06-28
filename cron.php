<?php
require_once 'vendor/autoload.php';
require 'utils.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$conn = fetchPDOConnection();

// Refresh all refresh tokens
if($conn != null){
    $stmt = $conn->prepare('SELECT * FROM tokens');

    if ($stmt->execute()) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $token = refreshMSToken($conn, $row['ms_id'], $row['ms_refreshtoken']);
            echo $token;
            echo '<br><br></br>updated';
        }
    }


}