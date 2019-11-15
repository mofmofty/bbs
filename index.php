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


//イイねボタンの機能追加によるSQLのクエリの変更　ここから
//投稿メッセージの一覧を取得。postsにmembersをJoinし、likes数をカウントしたデータをさらにJoinしている。できればサブクエリを無くしたい。
//$posts = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id ORDER BY p.created DESC LIMIT ?, 5');
$posts = $db->prepare('SELECT A.name, A.picture, A.id, A.message, A.member_id, A.reply_post_id, A.created, B.count FROM 
(SELECT m.name,m.picture, p.id, p.message, p.member_id,p.reply_post_id,p.created FROM posts p LEFT JOIN members m ON m.id=p.member_id) A
LEFT JOIN 
(SELECT post_id,COUNT(post_id) AS count FROM likes GROUP BY post_id) B
ON A.id=B.post_id
ORDER BY A.created DESC LIMIT ?, 5');
//イイねボタンの機能追加によるSQLのクエリの変更　ここまで

$posts->bindParam(1, $start, PDO::PARAM_INT);
$posts->execute();

//返信の場合
if (isset($_REQUEST['res'])) {
    $response = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=? ORDER BY p.created DESC');
    $response ->execute(array($_REQUEST['res']));

    $table = $response->fetch();
    $message = '@' . $table['name'] . ' ' . $table['message'];
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