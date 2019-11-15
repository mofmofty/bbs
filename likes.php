<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id'])) {
    $id = $_SESSION['id']; //member_id
    $post = $_REQUEST['id']; //post_id

    $check_likes = $db->prepare('SELECT COUNT(*) AS count FROM likes WHERE member_id = ? and post_id = ?');
    $check_likes ->execute(array($id,$post));
    $check = $check_likes->fetch();

    //イイねが0ならデータを追加、0以外なら削除
    if ($check['count'] == 0) {
        $add_likes = $db->prepare('INSERT INTO likes SET member_id=?, post_id=?');
        $add_likes ->execute(array($id,$post));
    } else {
        $del_likes = $db->prepare('DELETE FROM likes WHERE member_id=? AND post_id=?');
        $del_likes ->execute(array($id,$post));
    }
}

header('Location: index.php'); exit();
