<?php
require_once 'vendor/autoload.php';
require_once 'utils.php';
session_start();

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

if(isset($_SESSION['ms_id'])){
    $conn = fetchPDOConnection();
    if($conn != null){
        $stmtCheckStatus = $conn->prepare('SELECT ms_sub_id FROM ms_subs WHERE user_id IN (SELECT id FROM tokens WHERE ms_id=?');
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
                echo $res->getBody();
                echo '<br><br>Subscribed successfully';
            } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                echo 'Unable to subscribe to notifications. Try logging in again or check the log for details';
                //log it
            }
        }


    }
    else {
        echo 'Cannot connect to the database';
    }
} else{
    echo 'Please login at signin-ms.php first.';
}