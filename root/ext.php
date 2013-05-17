<?php

define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup('viewforum');

// require admins
if (!$user->data['is_registered']) {
	login_box(request_var('redirect', append_sid("{$phpbb_root_path}".basename(__FILE__))));
}
else if ($user->data['user_type'] != 3) {
	meta_refresh(3, append_sid("{$phpbb_root_path}index.$phpEx"));
	$message = 'Founder rights required for the extension';
	trigger_error($message);	
}

// Generate logged in/logged out status
$u_login_logout = ($user->data['user_id'] != ANONYMOUS) ?
	append_sid("{$phpbb_root_path}ucp.$phpEx", 'mode=logout', true, $user->session_id) :
	append_sid("{$phpbb_root_path}ucp.$phpEx", 'mode=login&redirect='.basename(__FILE__));

$u_csv = append_sid("{$phpbb_root_path}".basename(__FILE__), 'format=csv', true, $user->session_id);

//Create Groups map from db
$sql = 'SELECT * FROM ' . GROUPS_TABLE;
$result = $db->sql_query($sql);
$_groups = $db->sql_fetchrowset($result);

$groups_map = array();
foreach ($_groups as $_group) 
{
	$groups_map[$_group['group_id']] = $_group['group_name'];
}

// Prettify
$pretty_groups_map = array(
	'1' => 'Guests',
	'2' => 'Registered',
	'3' => 'Coppa',
	'4' => 'Global Moderators',
	'5' => 'Administrators',
	'6' => 'Bots',
	'7' => 'Newly Registered',
);

$groups_map = $pretty_groups_map + $groups_map;

//var_dump($groups_map);die;

//dbal, when common is not included
//require($phpbb_root_path . 'includes/db/' . $dbms . '.' . $phpEx);
//$db = new $sql_db();

//Users
$sql = 'SELECT * FROM ' . USERS_TABLE;
$result = $db->sql_query($sql);
$_users = $db->sql_fetchrowset($result);
//$db->sql_freeresult($result)

//User Group rels
//$ugs has user_id => value1, group_id => value2
//group_leader => value3, user_pending => value4
$sql = 'SELECT * FROM ' . USER_GROUP_TABLE;
$result = $db->sql_query($sql);
$ugs = $db->sql_fetchrowset($result);
$db->sql_freeresult($result);

// for easy csv output
$_users_csv = array();

foreach ($_users as $_user) 
{
	//filter bots, anonymous
	if (!(($_user['group_id'] == 6) or ($_user['user_id'] == ANONYMOUS)))
	{
		//next, identify groups that the user has
		$_user['group_ids'] = array();
		foreach ($ugs as $ug)
			if($_user['user_id'] === $ug['user_id'])
				$_user['group_ids'][] = $ug['group_id'];


		//change group_ids to str
		$_user['group_ids'] = join($_user['group_ids'],', ');

		//add group and groups keys
		$_user['group'] = str_replace(array_keys($groups_map), array_values($groups_map), (string)$_user['group_id']);
		$_user['groups'] = str_replace(array_keys($groups_map), array_values($groups_map), $_user['group_ids']);

		//$_u has user_id => value1, username => value2
		//phpbb assign_block_vars wants uppercased keys 
		//for the passed array be accessible in template
		//here, we strip user_ too
		$_u = array();
		foreach ($_user as $key => $value){		
			$_u[strtoupper(str_replace('user_', '', $key))] = $value;
		}
		//now that it is normalized
		$template->assign_block_vars('_u', $_u);
		//append to csv array
		$_users_csv[] = $_u;
	} 
	//if ($_user['user_id'] == ANONYMOUS) 
		//break;
		//var_dump($_user);


}

if (isset($_GET['format'])) {

	if ($_GET['format'] == 'json') {
		//echo json_encode($_users);
		echo json_encode($_users_csv);
	}
	elseif ($_GET['format'] == 'csv') 
	{
		$output = fopen("php://output",'w') or die("Can't open php://output");
		header("Content-Type:application/csv"); 
		header("Content-Disposition:attachment;filename=users.csv");
		foreach ($_users_csv as $user) {
			$filter = array('ID', 'USERNAME', 'EMAIL', 'GROUP', 'GROUPS');
			$user_filtered = array_intersect_key($user, array_flip($filter));
		    fputcsv($output, $user_filtered);
		}
		fclose($output) or die("Can't close php://output");
	}
/*
	$out = fopen('php://output', 'w');
	fputcsv($out, array('this','is some', 'csv "stuff", you know.'));
	fclose($out);
*/
} else {

	// Assign index specific vars
	$template->assign_vars(array(
		'U_LOGIN_LOGOUT' => $u_login_logout,
		'U_CSV' => $u_csv,
	));

	$template->set_filenames(array(
	    'body' => 'ext.html',
	));

	page_header();
	make_jumpbox(append_sid("{$phpbb_root_path}viewforum.$phpEx"));
	page_footer();		
}


//