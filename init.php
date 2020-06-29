<?php
require_once 'utils.php';
try{
    $conn = fetchPDOConnection();

    $stmt = $conn->prepare('CREATE TABLE IF NOT EXISTS `ms_subs` (
  `id` int(11) NOT NULL,
  `ms_sub_id` mediumtext NOT NULL,
  `ms_id` mediumtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
    $stmt->execute();

    $stmt = $conn->prepare('CREATE TABLE IF NOT EXISTS `tasks` (
  `id` int(11) NOT NULL,
  `ms_task_id` mediumtext,
  `google_event_id` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
    $stmt->execute();

    $stmt = $conn->prepare('CREATE TABLE IF NOT EXISTS `tokens` (
  `id` int(11) NOT NULL,
  `ms_id` varchar(255) DEFAULT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `ms_accesstoken` mediumtext,
  `ms_accesstoken_expiry` bigint(20) DEFAULT NULL,
  `ms_refreshtoken` mediumtext,
  `ms_refreshtoken_issued` bigint(20) DEFAULT NULL,
  `google_accesstoken` mediumtext,
  `google_accesstoken_expiry` bigint(20) DEFAULT NULL,
  `google_refreshtoken` mediumtext,
  `google_refreshtoken_issued` bigint(20) DEFAULT NULL,
  `google_calendar_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
    $stmt->execute();

    echo 'Done.';
}
catch(PDOException $e) {
    echo 'Error establishing database connection.';
    $logger->error('Error establishing database connection.');
    $logger->error($e);
}