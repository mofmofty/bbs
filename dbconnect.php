<?php
    try {
        $db = new PDO('mysql:dbname=mini_bbs;host=127.0.0.1;charset=utf8', 'root', 'root');
    } catch (PDOException $e) {
        echo 'DB接続エラーです：　' . $e->getMessage();
    }
