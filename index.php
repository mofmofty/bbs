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

        //通常の投稿の場合、reply_post_idに値が入らないため、NULLをセットする
        if ($_POST['reply_post_id'] == '') {
            $_POST['reply_post_id'] = null;
        }

        $message = $db->prepare('INSERT INTO posts SET member_id=?, message=?, reply_post_id=?, created=NOW()');
        $message->execute(array($member['id'],$_POST['message'],$_POST['reply_post_id']));

        header('Location: index.php');
        exit();
    }
}

//投稿を取得する
$page = $_REQUEST['page'];
if ($page == '') {
    $page = 1;
}
$page = max($page, 1);

//最終ページを取得する
$counts = $db->query('SELECT COUNT(*) AS cnt FROM posts');
$cnt = $counts->fetch();
$maxPage = ceil($cnt['cnt'] / 5);
$page = min($page, $maxPage);

$start = ($page - 1) * 5;

//イイねボタンの機能追加によるクエリの変更　ここから
//投稿メッセージの一覧を取得。postsにmembersをJoinし、likes数をカウントしたデータをさらにJoinしている。Case文はリツイートされた場合、リツイート元のmember_idを取得することで元の投稿者の画像を表示する。
$posts = $db->prepare('SELECT B.name, B.picture, A.id, A.message, A.member_id,A.re_member_id, A.reply_post_id, A.created, C.count 
FROM 
 posts A
JOIN 
 members B ON (CASE WHEN A.re_member_id is NULL THEN A.member_id ELSE A.re_member_id END) = B.id 
LEFT JOIN 
 (SELECT post_id,COUNT(post_id) AS count FROM likes GROUP BY post_id) C
ON A.id=C.post_id
ORDER BY A.created DESC LIMIT ?, 5');
//イイねボタンの機能追加によるクエリの変更　ここまで

$posts->bindParam(1, $start, PDO::PARAM_INT);
$posts->execute();

//返信の場合
if (isset($_REQUEST['res'])) {
    $response = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=? ORDER BY p.created DESC');
    $response ->execute(array($_REQUEST['res']));

    $table = $response->fetch();
    $message = '@' . $table['name'] . ' ' . $table['message'];
}

//リツイート機能　ここから
if (isset($_REQUEST['reply'])) {
    $reply = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=? ORDER BY p.created DESC');
    $reply ->execute(array($_REQUEST['reply']));

    //リツイート主=ログインしている人の名前を取得
    $login_user = $db->prepare('SELECT name FROM members WHERE id=?');
    $login_user->execute(array($_SESSION['id']));
    $user = $login_user->fetch();

    //リツイート文章の整形
    $reply_table = $reply->fetch();
    $reply_message = '@' . $user['name'] . 'さんがリツイート : ' . $reply_table['message'];

    //返信用ではないので、reply_post_idはNULLをセット
    $reply_post_id = null;


    //リツイートのメイン処理
    if ($member['id'] == $_SESSION['id'] && is_null($reply_table['re_post_id']) == true) {
        //他人のメッセージを既に自分がリツイートしているかチェック。存在しているかをカウントする。
        $re_post_check = $db->prepare('SELECT COUNT(id) AS count FROM posts WHERE member_id = ? AND re_post_id = ?');
        $re_post_check->execute(array($member['id'], $_REQUEST['reply']));
        $re_post_count = $re_post_check->fetch();
        
        //リツイートがある場合、レコードを削除する。ない場合は、レコードを追加する。
        if ($re_post_count['count'] > 0) {
            //削除処理
            $message = $db->prepare('DELETE FROM posts WHERE member_id=? AND re_post_id=?');
            $message->execute(array($member['id'],$_REQUEST['reply']));
        } else {
            //追加処理
            $message = $db->prepare('INSERT INTO posts SET member_id=?, message=?, reply_post_id=?, re_member_id=?, re_post_id=?, created=NOW()');
            $message->execute(array($member['id'],$reply_message,$reply_post_id,$reply_table['member_id'],$_REQUEST['reply']));
        }
    }
    //自分で既に他人をリツイートしている場合
    elseif ($member['id'] == $_SESSION['id'] && is_null($reply_table['re_post_id']) == false) {
        $re_post_check2 = $db->prepare('SELECT COUNT(id) AS count FROM posts WHERE id = ?');
        $re_post_check2->execute(array($_REQUEST['reply']));
        $re_post_count2 = $re_post_check2->fetch();
        
        if ($re_post_count2['count'] > 0) {
            $message = $db->prepare('DELETE FROM posts WHERE id=?');
            $message->execute(array($_REQUEST['reply']));
        }
    }
    //リツイート機能　ここまで

    header('Location: index.php');
    exit();
}


//htmlspecialcharsのショートカット
function h($value)
{
    return htmlspecialchars($value, ENT_QUOTES);
}

//本文内のURLにリンクを設定します
function makeLink($value)
{
    return mb_ereg_replace("(https?)(://[[:alnum:]\+\$\;\?\.A%,!#~*/:@&=_-]+)", '<a href="\1\2">\1\2</a>', $value);
}
?>

<!doctype html>
<html lang="ja">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <link rel="stylesheet" type="text/css" href="style.css" />
    <title>ひとこと掲示板</title>
</head>

<body>
    <div id="wrap">
        <div id="head">
            <h1>ひとこと掲示板</h1>
        </div>
        <div id="content">
            <div style="text-align: right"><a href="logout.php">ログアウト</a></div>
            <form action="" method="post">
                <dl>
                    <dt><?php echo h($member['name']);?>さん、メッセージをどうぞ
                    </dt>
                    <dd>
                        <textarea name="message" cols="50"
                            rows="5"><?php echo h($message); ?></textarea>
                        <input type="hidden" name="reply_post_id"
                            value="<?php echo h($_REQUEST['res']); ?>" />

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
                <img src="member_picture/<?php echo h($post['picture']); ?>"
                    width="45" height="60"
                    alt="<?php echo h($post['name']); ?>" />
                <p><?php echo makeLink(h($post['message'])); ?><span
                        class="name">(<?php echo h($post['name']); ?>)
                    </span>
                    [<a
                        href="index.php?res=<?php echo h($post['id']); ?>">Re</a>]
                </p>
                <p class="day"><a
                        href="view.php?id=<?php echo h($post['id']); ?>"><?php echo h($post['created']); ?>
                    </a>
                    <?php
                    if ($post['reply_post_id'] > 0):
                    ?>
                    <a
                        href="view.php?id=<?php echo($post['reply_post_id']); ?>">返信元のメッセージ</a>
                    <?php
                    endif;
                    ?>
                    <?php
                    if ($_SESSION['id'] == $post['member_id']):
                    ?>
                    [<a href="delete.php?id=<?php echo h($post['id']); ?>"
                        style="color:#F33;">削除</a>]
                    <?php
                    endif;
                    ?>
                </p>

                <!-- イイねボタンの機能追加箇所 ここから -->
                <!-- イイねボタン処理 -->
                <a
                    href="likes.php?id=<?php echo h($post['id']); ?>">
                    <img src="./img/like.png" , width=25, height=25, alt="likes">
                </a>
                <!-- イイねボタンの表示部分 -->
                [
                <?php
                    if (isset($post['count'])) {
                        echo h($post['count']);
                    } else {
                        echo 0;
                    }
                ?>
                ]
                <!-- イイねボタンの機能追加箇所 ここまで -->

                <!-- リツイート機能実装箇所　ここから -->

                <a
                    href="index.php?reply=<?php echo h($post['id']); ?>">リツイート
                </a>

                <!-- リツイート機能実装箇所　ここまで -->
            </div>
            <?php
endforeach;
?>

            <ul class="paging">
                <?php
if ($page > 1) {
    ?>
                <li><a
                        href="index.php?page=<?php print($page - 1); ?>">前のページへ</a>
                </li>
                <?php
} else {
        ?>
                <li>前のページへ</li>
                <?php
    }
?>
                <?php
if ($page < $maxPage) {
    ?>
                <li><a
                        href="index.php?page=<?php print($page + 1); ?>">次のページへ</a>
                </li>
                <?php
} else {
        ?>
                <li>次のページへ</li>
                <?php
    }
?>
            </ul>
        </div>
    </div>
</body>

</html>