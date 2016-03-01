<?php

	include("include/init.php");

	loadlib("http");
	loadlib("random");
	loadlib("github_api");
	loadlib("github_users");

	# Some basic sanity checking like are you already logged in?

	if ($GLOBALS['cfg']['user']['id']){
		header("location: {$GLOBALS['cfg']['abs_root_url']}");
		exit();
	}


	if (! $GLOBALS['cfg']['enable_feature_signin']){
		$GLOBALS['smarty']->display("page_signin_disabled.txt");
		exit();
	}

	$code = get_str("code");

	if (! $code){
		error_404();
	}

	$rsp = github_api_get_auth_token($code);

	if (! $rsp['ok']){
		$GLOBALS['error']['oauth_access_token'] = 1;
		$GLOBALS['smarty']->display("page_auth_callback_github_oauth.txt");
		exit();
	}

	$oauth_token = $rsp['oauth_token'];

	$github_user = github_users_get_by_oauth_token($oauth_token);

	if (($github_user) && ($user_id = $github_user['user_id'])){
		$user = users_get_by_id($user_id);
	}

	# If we don't ensure that new users are allowed to create
	# an account (locally).

	else if (! $GLOBALS['cfg']['enable_feature_signup']){
		$GLOBALS['smarty']->display("page_signup_disabled.txt");
		exit();
	}

	# Hello, new user! This part will create entries in two separate
	# databases: Users and GithubUsers that are joined by the primary
	# key on the Users table.

	else {

		dumper("WHAT NOW?");
		dumper($github_user);
		exit();

		$args = array(
			'oauth_token' => $oauth_token,
		);

		$rsp = github_api_call('users/self', $args);

		if (! $rsp['ok']){
			$GLOBALS['error']['github_userinfo'] = 1;
			$GLOBALS['smarty']->display("page_auth_callback_github_oauth.txt");
			exit();
		}

		$github_id = $rsp['rsp']['user']['id'];
		$username = $rsp['rsp']['user']['firstName'];
		$email = $rsp['rsp']['user']['contact']['email'];

		if (! $email){
			$email = "{$github_id}@donotsend-github.com";
		}

		if (isset($rsp['rsp']['user']['lastName'])){
			$username .= " {$rsp['rsp']['user']['lastName']}";
		}

		$password = random_string(32);

		$user = users_create_user(array(
			"username" => $username,
			"email" => $email,
			"password" => $password,
		));

		if (! $user){
			$GLOBALS['error']['dberr_user'] = 1;
			$GLOBALS['smarty']->display("page_auth_callback_github_oauth.txt");
			exit();
		}

		$github_user = github_users_create_user(array(
			'user_id' => $user['id'],
			'oauth_token' => $oauth_token,
			'github_id' => $github_id,
		));

		if (! $github_user){
			$GLOBALS['error']['dberr_githubuser'] = 1;
			$GLOBALS['smarty']->display("page_auth_callback_github_oauth.txt");
			exit();
		}
	}

	# Okay, now finish logging the user in (setting cookies, etc.) and
	# redirecting them to some specific page if necessary.

	$redir = (isset($extra['redir'])) ? $extra['redir'] : '';

	login_do_login($user, $redir);
	exit();
?>