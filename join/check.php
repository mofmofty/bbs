<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id']) && $_SESSION['time'] + 3600 > time()) {
    //ログインしている
    $_SESSION['time'] = time();

    $members = $db->prepare('SELECT * FROM members WHERE id=?');
    $members->execute(array($_SESSION['id']));
    $member = $members->fetch();
} else {
    //ログインしていない
    header('Location: login.php');
    exit();
}

//投稿を記録する
if (!empty($_POST)) {
    if ($_POST['message'] != '') {
        $message = $db->prepare('INSERT INTO posts SET member_id=?, message=?, created=NOW()');
        $message->execute(array($member['id'],$_POST['message']));

        header('Location: index.php');
        exit();
    }
}

//投稿を取得する
$posts = $db->query('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id ORDER BY p.created DESC');


// if (!empty($_POST)) {
// if ($_POST['message'] != '') {
// $sql = sprintf(
// 'INSERT INTO posts SET member_id=%d, message="%s", reply_post_id=%d, created=NOW()',
// mysql_real_escape_string($member['id']),
// mysql_real_escape_string($_POST['message']),
// mysql_real_escape_string($_POST['reply_post_id'])
// );
// mysql_query($sql) or die(mysql_error());

// header('Location: index.php');
// exit();
// }
// }

// $session = $_SESSION['time']+3600;
// $time = $_SESSION['time']+3600;

?>

<!doctype html>
<html lang="ja">

<form action="" method="post">
    <dl>
        <dt><?php echo htmlspecialchars($member['name'], ENT_QUOTES);?>さん、メッセージをどうぞ
        </dt>
        <dd>
            <textarea name="message" cols="50" rows="5"></textarea>
        </dd>
    </dl>
    <div>
        <input type="submit" value="投稿する">
    </div>
</form>

<?php
foreach ($posts as $post):
?>

<div class="msg">
    <img src="member_picture/<?php echo htmlspecialchars($post['picture'], ENT_QUOTES); ?>"
        width="45" height="60"
        alt="<?php echo htmlspecialchars($post['name'], ENT_QUOTES); ?>" />
    <p><?php echo htmlspecialchars($post['message'], ENT_QUOTES); ?><span
            class="name">(<?php echo htmlspecialchars($post['name'], ENT_QUOTES); ?>)
        </span></p>
    <p class="day"><?php echo htmlspecialchars($post['created'], ENT_QUOTES);?>
    </p>
</div>
<?php
endforeach;
