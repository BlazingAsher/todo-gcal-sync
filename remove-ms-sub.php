<?php
require_once 'vendor/autoload.php';
require 'utils.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$logger = new Katzgrau\KLogger\Logger(__DIR__.'/logs');

try {
    $conn = fetchPDOConnection();

    $tokenCache = array();

    // Refresh all refresh tokens
    $stmt = $conn->prepare('SELECT * FROM tokens');
    
    if ($stmt->execute()) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tokenCache[$row['ms_id']] = $row['ms_accesstoken'];
        }
    }
    
    $stmtGetSub = $conn->prepare('SELECT * FROM ms_subs');
    if($stmtGetSub->execute()){
        while ($row = $stmtGetSub->fetch(PDO::FETCH_ASSOC)) {
            $client = new GuzzleHttp\Client();

            try {
                $res = $client->request('DELETE', 'https://outlook.office.com/api/v2.0/me/subscriptions/' . $row['ms_sub_id'], [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $tokenCache[$row['ms_id']]
                    ],
                    'json' => [
                        "@odata.type"=>"#Microsoft.OutlookServices.PushSubscription"
                    ]
                ]);

            } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                $logger->error('Error removing MS sub ID ' . $row['ms_sub_id']);
                $logger->error($e);

                try {
                    sendPushoverMessage('Error removing MS sub ID ' . $row['ms_sub_id']);
                } catch (ToDoCalSyncException $e) {
                    $logger->error('Error sending message to Pushover');
                }
            }
        }
    }

}
catch (PDOException $e){
    $logger->error('Error establishing database connection during cron run.');
    $logger->error($e);

    try {
        sendPushoverMessage('Error establishing database connection during cron run.');
    } catch (ToDoCalSyncException $e) {
        $logger->error('Error sending message to Pushover');
    }
}

echo 'OK';
