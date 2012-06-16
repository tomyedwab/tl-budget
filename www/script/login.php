<?php

header('Content-Type: text/html');
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

include("tl_budget.php");

$_SESSION['db_is_logged_in'] = false;
$_SESSION['db_login_domain'] = Null;

$login = mysql_real_escape_string($_GET["user"]);
if (!$login) {
    echo '{"logout":1}';
    exit(0);
}

$store->setDomainOverride("admin");

$password = $_GET["pass"];
$password_hash = md5($password);
$users = $store->fetchObjectsFromIndex('user_login', $login, 0);
if ($users) {
    foreach ($users["user"] as $key => $data)
    {
        $user = json_decode($data[0]);
        if ($user->password == $password_hash)
        {
            $_SESSION['db_is_logged_in'] = true;
            $_SESSION['db_login_id'] = $key;
            $_SESSION['db_login_name'] = $user->realname;
            $_SESSION['db_login_user'] = $user->login;
            $_SESSION['db_login_domain'] = $user->domain;

            error_log("Login success! Domain: $user->domain");
            echo "{\"login\":1,\"user\":\"$user->realname\",\"domain\":\"$user->domain\"}";
        } else {
            error_log("Login failure! Bad password hash $password_hash ($user->password)");
            echo '{"login":0}';
        }
        exit();
    }
}
{
	error_log("Login failure! Bad username $login");
	echo '{"login":0}';
}

?>
