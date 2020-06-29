<?php
require_once 'vendor/autoload.php';
require_once 'utils.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$logger = new Katzgrau\KLogger\Logger(__DIR__.'/logs');

try{
    $conn = fetchPDOConnection();
    $token = fetchMSUserToken($conn, $_GET['ms_id']);

    $client = new GuzzleHttp\Client();
    $res = $client->request('GET', 'https://outlook.office.com/api/v2.0/me/tasks', [
        'headers' => [
            'Authorization' => 'Bearer ' . $token
        ]
    ]);

    echo $res->getBody();
    $user = json_decode($res->getBody(), true);

    var_dump($user);

}
catch(PDOException $e){
    echo 'Error establishing database connection.';
    $logger->error('Error establishing database connection.');
    $logger->error($e);
}