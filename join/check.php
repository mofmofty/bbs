<?php
session_start();
require('../dbconnect.php');

if (!isset($_SESSION['join'])) {
    header('Location: index.php');
    exit();
}
if (!empty($_POST)) {
    //登録処理をする
    $statement = $db->prepare('INSERT INTO members SET name=?, email=?, password=?, picture=?, created=NOW()');
    echo $ret = $statement->execute(array(
    $_SESSION['join']['name'],
    $_SESSION['join']['email'],
    sha1($_SESSION['join']['password']),
    $_SESSION['join']['image']
));
    unset($_SESSION['join']);
    header('Location: thanks.php');
    exit();
}

//htmlspecialcharsのショートカット
function h($value)
{
    return htmlspecialchars($value, ENT_QUOTES);
}

?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <link rel="stylesheet" type="text/css" href="../style.css" />>
    <title>ひとこと掲示板</title>
    <link rel="stylesheet" href="../style.css">
</head>

<body>
    <div id="wrap">
        <div id="head">
            <h1>会員登録</h1>
        </div>
        <div id="content">
            <p>次のフォームに必要事項をご記入ください。</p>
            <form action="" method="post">
                <input type="hidden" name="action" value="submit">
                <dl>
                    <dt>ニックネーム</dt>
                    <dd>
                        <?php echo h($_SESSION['join']['name']); ?>
                    </dd>
                    <dt>メールアドレス</dt>
                    <dd>
                        <?php echo h($_SESSION['join']['email']); ?>
                    </dd>
                    <dt>パスワード</dt>
                    <dd>【表示されません】</dd>
                    <dt>写真など</dt>
                    <dd>
                        <img src="../member_picture/<?php echo h($_SESSION['join']['image']); ?>"
                            width="100" height="100" alt="">
                    </dd>
                </dl>
                <div><a href="index.php?action=rewrite">&laquo;&nbsp;書き直す</a> | <input type="submit" value="登録する"></div>
            </form>
        </div>
    </div>
</body>

</html>