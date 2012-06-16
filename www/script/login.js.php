<?php
session_start();
if (isset($_SESSION['db_is_logged_in']) && $_SESSION['db_is_logged_in'] == true) { 
	$user_login = $_SESSION['db_login_user'];
	$user_name = $_SESSION['db_login_name'];
    $domain = $_SESSION['db_login_domain'];
	echo "TL_Auth.currentUser = \"$user_login\";\n";
	echo "TL_Auth.currentUserFullname = \"$user_name\";\n";
	echo "TL_Auth.currentDomain = \"$domain\";\n";
} else {
	echo "// Not logged in\n";
}
?>
