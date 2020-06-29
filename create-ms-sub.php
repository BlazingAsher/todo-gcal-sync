<?php
require_once 'vendor/autoload.php';
require_once 'utils.php';
session_start();

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$logger = new Katzgrau\KLogger\Logger(__DIR__.'/logs');

if(isset($_SESSION['ms_id'])){
    try{
        $conn = fetchPDOConnection();
        $stmtCheckStatus = $conn->prepare('SELECT ms_sub_id FROM ms_subs WHERE ms_id =? LIMIT 1');
        $stmtCheckStatus->bindParam(1, $_SESSION['ms_id'], PDO::PARAM_STR);

        $stmtCheckStatus->execute();

        if($stmtCheckStatus->rowCount() > 0){
            $rowCheckStatus = $stmtCheckStatus->fetch(PDO::FETCH_ASSOC);
            echo 'User is subscribed with sub ID ' . $rowCheckStatus['ms_sub_id'];
        }
        else{
            try {
                $res = $client->request('POST', 'https://outlook.office.com/api/v2.0/me/subscriptions', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $_SESSION['ms_token']
                    ],
                    'json' => [
                        "@odata.type"=>"#Microsoft.OutlookServices.PushSubscription",
                        "Resource"=>"https://outlook.office.com/api/v2.0/me/tasks",
                        "NotificationURL"=>$_ENV['MS_SUB_NOTIFY_URL'],
                        "ChangeType"=>"Created, Updated, Deleted",
                        "ClientState"=>"2"
                    ]
                ]);

                $newSubInfo = json_decode($res->getBody(), true);

                $stmtAddSub = $conn->prepare('INSERT INTO ms_subs (ms_sub_id, ms_id) VALUES (:ms_s_i, :m_i)');
                $stmtAddSub->bindValue(':ms_s_i', $newSubInfo['Id'], PDO::PARAM_STR);
                $stmtAddSub->bindValue(':m_i', $_SESSION['ms_id'], PDO::PARAM_STR);

                $stmtAddSub->execute();

                echo '<br><br>Subscribed successfully';
            } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                echo 'Unable to subscribe to notifications. Try logging in again or check the log for details';
                $logger->error('Unable to subscript to notifications.');
                $logger->error($e);
            }
        }


    }
    catch (PDOException $e) {
        echo 'Error establishing database connection.';
        $logger->error('Error establishing database connection.');
        $logger->error($e);
    }
} else{
    echo 'Please login at <a href="signin-ms.php">signin-ms.php</a> first.';
}