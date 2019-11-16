<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id']) && isset($_REQUEST['id'])) {
    $member_id = $_SESSION['id'];
    $post_id = $_REQUEST['id'];

    $check_likes = $db->prepare('SELECT COUNT(*) AS count FROM likes WHERE member_id = ? and post_id = ?');
    $check_likes ->execute(array($member_id,$post_id));
    $check = $check_likes->fetch();

    //イイねが0ならデータを追加、0以外なら削除
    if ($check['count'] == 0) {
        $add_likes = $db->prepare('INSERT INTO likes SET member_id=?, post_id=?');
        $add_likes ->execute(array($member_id,$post_id));
    } else {
        $del_likes = $db->prepare('DELETE FROM likes WHERE member_id=? AND post_id=?');
        $del_likes ->execute(array($member_id,$post_id));
    }
}

header('Location: index.php'); exit();
