<?php

function fetchPDOConnection($DB_HOST, $DB_NAME, $DB_USER, $DB_PASSWORD){
    try{
        $conn = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME", $DB_USER, $DB_PASSWORD);

        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $conn;

    } catch(PDOException $e){
        echo 'Connection failed: ' . $e->getMessage();
        return null;
    }
}