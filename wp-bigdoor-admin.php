<?php
/**
*  BigDoor Media for WordPress - Admin Functionality 
*  
*  @author Mark Edwards <mark@simplercomputing.net>
*  @version 0.1.0
*/

/**
* @package WP-BigDoor
* @subpackage BigDoor-Admin-Interface
* @class BD_Admin_Class
* 
* Admin screens and related helper functions
*/

class BD_Admin_Class { 

    var $localizationDomain;

    function BD_Admin_Class() {
	    __construct();
    }

    function __construct() { 
	global $wp_bigdoor_var;
	$this->localizationDomain = $wp_bigdoor_var->localizationDomain;
    }


    // built the preliminary API call and calls the lower level do_request function
    function bd_api_caller($type, $env=false, $id=false, $resname=false, $resid=false, $delt=false ) { 


	    global $wp_bigdoor_var;

	    $parms = array(
			'format'=>'json', 
			'verbosity'=>'9', 
		    );

	    // generate a delete token? 
	    if ($delt) 
		$parms['delete_token'] = md5(uniqid());

	    $client = & new BDM_WP_Client(
		    '', 
		    $wp_bigdoor_var->bd_options_array, 
		    $type,
		    $parms,
		    $env,
		    $id,
		    $resname,
		    $resid
	    );

	    $resp = $client->do_request(); 

	    if ('' != $resp) 
		$res = json_decode($resp, TRUE);
	    else
		$res = '';

	    return $res; 
    }


    // create a new currency
    function bd_create_currency($type,$name) { 

	    $res = $this->bd_api_caller(
		    'currency_create',
		    array(
			'pub_title' => $name,
			'currency_type_id' => $type, 
			'exchange_rate' => '1.00', 
			'relative_weight' => '1'
		    )
	    );

	    if (is_array($res)) { 
		$currencies = get_option('bd_currencies',false);
		if (!is_array($currencies)) $currencies = array();
		$res[0]['currency_name'] = $name;
		$currencies[] = $res; 
		update_option('bd_currencies',$currencies);
		return true;
	    } else { 
		return 'Error creating currency! Please try again in a moment'; 
	    }
    }


    function update_level($id, $args) { 
	$res = $this->bd_api_caller(
		'level_update',
		$args,
		$id
	);
	return $res;
    }

    function delete_level($idx, $id) { 
	$res = $this->bd_api_caller(
		'level_delete',
		false,
		$idx,
		'named_level',
		$id,
		true
	);
	return $res;
    }

    function update_url($id, $args) { 
	$res = $this->bd_api_caller(
		'url_update',
		$args,
		$id
	);
	return $res;
    }

    function award_update($id, $args) { 
	$res = $this->bd_api_caller(
		'award_update',
		$args,
		$id
	);
	return $res;
    }


    function create_transaction($cid, $name, $amt=false) { 

	$parms = array(
		    'pub_title' => $name,
		    'currency_id' => $cid
		 );

	if ($amt !== false)
	    $parms['default_amount'] = $amt;

	$res = $this->bd_api_caller(
		'named_transaction_create',
		$parms
	);
	if (!is_array($res)) return false;
	return $res;
    }

    function create_transaction_group( $name, $args = array() ) { 

	$args['pub_title'] = $name;

	$res = $this->bd_api_caller(
		'named_transaction_group_create',
		$args
	);
	if (!is_array($res)) return false;
	return $res;
    }

    function create_transaction_assoc($name, $tgroup_id, $tid, $primary=false) {
 
	$args = array('pub_title' => $name);
	if ($primary) $args['named_transaction_is_primary'] = 1;

	$res = $this->bd_api_caller(
		'named_transaction_assoc',
		$args,
		$tgroup_id,
		false,
		$tid
	);

	if (!is_array($res)) return false;
	return $res;
    }


    // create a new level 
    function create_attendance_award($name, $badge_url) {
	global $wp_bigdoor_var;

	_e('Creating award titled: ', $this->localizationDomain);
	echo ' '.$name.'...';

	$coll = $this->bd_api_caller(
		'award_collection_create',
		array(
		    'pub_title' => $name
		)
	);

	if (!is_array($coll)) return false;

	$res = $this->bd_api_caller(
		'award_create_and_assoc',
		array(
		    'pub_title' => $name,
		    'named_award_collection_id' => $coll[0]['id']
		),
		$coll[0]['id']
	);

	if (!is_array($res)) return false;


	_e('adding URL, ', $this->localizationDomain);

	// add url for award
	$url = $this->bd_api_caller(
		'url_create',
		array(
		    'pub_title' => $name.' image URL',
		    'is_media_url' => true,
		    'is_for_end_user_ui' => true,
		    'url' => $wp_bigdoor_var->bd_plugin_url.'images/badges/'.$badge_url
		)
	);

	if (!is_array($url)) return false;
	
	_e('associating URL to award ID: ', $this->localizationDomain);
	echo $url[0]['id'].' <-> '.$res[0]['id'];

	// associate with level
	$url_add = $this->bd_api_caller(
		'url_assoc',
		'', // no envelope
		$url[0]['id'], // no id
		'named_award', // resource type name
		$res[0]['id'] // resource id -- id of the award
		
	);

	if (is_array($url_add))
	    return true;
	else 
	    return $url_add; 

    }


    // create a level collect 
    function create_level_collection($currency_id, $name) { 

	    $res = $this->bd_api_caller(
		    'level_collection_create',
		    array(
			'currency_id' => $currency_id, 
			'pub_title' => $name.' levels'
		    )
	    );

	    if (is_array($res)) { 
		return true;
	    } else { 
		return __('Error creating level collection. Please try again in a moment', $this->localizationDomain);
	    }

    }

    // create a URL and associate it to an object
    function create_and_assoc_url($name, $url, $lid, $echo = true) { 

	// add url for level
	$xurl = $this->bd_api_caller(
		'url_create',
		array(
		    'pub_title' => $name.' badge URL',
		    'is_media_url' => true,
		    'is_for_end_user_ui' => true,
		    'url' => $url
		)
	);

	if (!is_array($xurl)) return false;
	
	if ($echo) { 
	    _e('associating URL to level ID: ', $this->localizationDomain);
	    echo $xurl[0]['id'].' <-> '.$lid.' ';
	}

	// associate with level
	$url_add = $this->bd_api_caller(
		'url_assoc',
		'', // no envelope
		$xurl[0]['id'], // url id
		'named_level', // resource type name
		$lid // resource id -- id of the level
		
	);

	if (!is_array($url_add)) return false;
	return $url_add;
    }

    // create a new level 
    function create_level($name, $desc, $threshold, $collection_id, $url) {

	_e('Creating level ->', $this->localizationDomain);
	echo ' '.$name.'...';

	$res = $this->bd_api_caller(
		'level_create',
		array(
		    'named_level_collection_id ' => $collection_id,
		    'threshold' => $threshold,
		    'pub_title' => $name,
		    'pub_description' => $desc
		),
		$collection_id
	);

	if (!is_array($res)) return false;

	_e('adding URL, ', $this->localizationDomain);

	$res = $this->create_and_assoc_url($name, $url, $res[0]['id'], true); 

	return $res; 

    }


    // create the default levels in the specified level collection
    function create_default_levels($coll, $total, $type) { 
	global $bd_default_parms, $wp_bigdoor_var;


	_e('<p>Generating default levels for level collection ID:', $this->localizationDomain);
	echo $coll['id'].' - '.$coll['pub_title'].'<p>';

	$lvls = $bd_default_parms[$type];

	// create the default levels for each level collection
	$x = 1;

	// are the levels already created? 
	// if so, there's nothing to do, so return true;
	$curr_cnt = count($coll['named_levels']);
	if ( $curr_cnt >= $total) { 
	    _e('<p>It looks like all default levels for this collect already exist ... skipping to the next.</p>', $this->localizationDomain);
	    return true;
	} else { 
	    // if some were created, but not all, created the remainder
	    $remainder = $total - $curr_cnt;
	    echo '<p>'.$total.' ';
	    _e('default levels to be created', $this->localizationDomain);
	    echo '<br/>';
	}


	// create levels 
	for ($i=$curr_cnt; $i<$remainder; $i++) { 

	    set_time_limit(90);

	    $res = $this->create_level($lvls[$i]['title'], $lvls[$i]['desc'], $lvls[$i]['threshold'], $coll['id'], $wp_bigdoor_var->bd_plugin_url.'images/badges/'.$type.'/'.$lvls[$i]['url']);

	    if (is_array($res)) {
		//$levels[] = $res;
		_e('... done<br/>', $this->localizationDomain);
		flush();
		ob_flush();
	    } else
		return false;
	}


	return true;

    }


    // check to see if a default collection level is created yet based on data returned by
    // the API query.  returns the index number of the object if it exists.
    function detect_default_object($coll, $name) { 
	if (!is_array($coll)) return false;	
	for($i=0; $i<count($coll); $i++) { ;
	    if (strtolower($name) == strtolower($coll[$i]['pub_title']) ) 
		return $i;
	}
	return false;
    }



    // main function that creates default objects
    function create_default_objects() { 


    // This function code is fairly linear and repetitive, which makes it easier to read and follow for people new to the API.
    // The function is also designed so that if it fails somewhere along the line it can be restarted again
    // and it will pick up where it left off in creating default objects etc.
    // For example, connectivity could break or a server could fail during default object creation. 
    // Recovery helps keep the user's API account clear of duplicate named items. 


    // First make sure we have the default currencies: 

	// get a list of currencies via the API 
	$curr = $this->bd_api_caller('currency_types');

	$firstc = $this->detect_default_object($curr[0], $_POST['bdm_default_xp_name']);
	if ($firstc !== false) {
		echo '<p>'.$_POST['bdm_default_xp_name'].' ';
		_e('currency already created.', $this->localizationDomain);
		echo ' ';
		_e('Currency ID: ', $this->localizationDomain);
		echo $curr[0][$firstc]['id'];
	    } else {
		_e('<p>Creating ', $this->localizationDomain);
		echo $_POST['bdm_default_xp_name'].' ';
		_e('currency...', $this->localizationDomain);
		$res = $this->bd_create_currency(5, $_POST['bdm_default_xp_name']);
		if ($res !== true) 
		    return $res; // return error message - can't continue
		echo 'done</p>';
	    }

	flush();
	set_time_limit(90);

	$secondc = $this->detect_default_object($curr[0], 'Comments');
	if ($secondc !== false) {
		echo '<p>';
		_e('Comment currency already created.', $this->localizationDomain);
		echo ' ';
		_e('Currency ID: ', $this->localizationDomain);
		echo $curr[0][$secondc]['id'];
	    } else {
		_e('<p>Creating Comment currency...', $this->localizationDomain);
		$res = $this->bd_create_currency(8, 'Comments');
		if ($res !== true) 
		    return $res; // return error message - can't continue
		echo 'done</p>';
	    }

	flush();
	set_time_limit(90);

	$thirdc = $this->detect_default_object($curr[0], 'Checkins');
	if ($thirdc !== false) {
		echo '<p>';
		_e('Checkins currency already created.', $this->localizationDomain);
		echo ' ';
		_e('Currency ID: ', $this->localizationDomain);
		echo $curr[0][$thirdc]['id'];
	    } else {
		_e('<p>Creating Comment currency...', $this->localizationDomain);
		$res = $this->bd_create_currency(8, 'Checkins');
		if ($res !== true) 
		    return $res; // return error message - can't continue
		echo 'done</p>';
	    }

	flush();
	set_time_limit(90);

	$curr = $this->bd_api_caller('currency_types');

	// get in indexes in the array for the currency objects
	$firstc = $this->detect_default_object($curr[0], $_POST['bdm_default_xp_name']);
	$secondc = $this->detect_default_object($curr[0], 'Comments');
	$thirdc = $this->detect_default_object($curr[0], 'Checkins');



    // Collections ----------------------------------------
    // Next make sure we have the default level collections and associated levels within those collections

	// do we have the level collections created yet? 
	// get a copy see we can check.
	$res = $this->bd_api_caller('level_collection_get');

	// First collection, should be the Experience level items
	$first = $this->detect_default_object($res[0], $_POST['bdm_default_xp_name'].' Levels');
	if ($first !== false) {
		echo '<p>'.$_POST['bdm_default_xp_name'].' ';
		_e('level collection already created.', $this->localizationDomain);
		echo ' ';
		_e('Currency name / ID: ', $this->localizationDomain);
		echo $curr[0][$first]['id']. ' / '.  $curr[0][$first]['pub_title'];
	    } else {
		_e('<p>Creating ', $this->localizationDomain);
		echo $_POST['bdm_default_xp_name'].' ';
		_e('collection level...', $this->localizationDomain);
		$res = $this->create_level_collection($curr[0][$first]['id'], $_POST['bdm_default_xp_name']);
		if ($res !== true) 
		    return $res; // return error message - can't continue
		echo 'done</p>';
	    }

	flush();
	set_time_limit(90);

	// Comments collection
	$second = $this->detect_default_object($res[0], 'Comments Levels');
	if ($second !== false) {
		_e('<p>Comments level collection already created.', $this->localizationDomain);
		echo ' ';
		_e('Currency name / ID: ', $this->localizationDomain);
		echo $curr[0][$secondc]['id']. ' / '. $curr[0][$secondc]['pub_title'];
	    } else {
		_e('<p>Creating Comments collection level...', $this->localizationDomain);
		$res = $this->create_level_collection($curr[0][$secondc]['id'], $curr[0][$secondc]['pub_title']);
		if ($res !== true) 
		    return $res; // return error message - can't continue
		echo 'done</p>';
	    }

	flush();
	set_time_limit(90);

	// Checkins collection 
	$third = $this->detect_default_object($res[0], 'Checkins Levels');
	if ($third !== false) {
		echo $_POST['bdm_default_xp_name'].' ';
		_e('<p>Checkins collection already created.', $this->localizationDomain);
		echo ' ';
		_e('Currency name / ID: ', $this->localizationDomain);
		echo $curr[0][$thirdc]['id']. ' / '.  $curr[0][$thirdc]['pub_title'];
	    } else {
		_e('<p>Creating Checkins collection level...', $this->localizationDomain);
		$res = $this->create_level_collection($curr[0][$thirdc]['id'], $curr[0][$thirdc]['pub_title']);
		if ($res !== true) 
		    return $res; // return error message - can't continue
		echo 'done</p>';
	    }


	flush();
	set_time_limit(90);


	// refresh this object again, in case we've created new collections
	$res = $this->bd_api_caller('level_collection_get');
	    if (!is_array($res)) 
		return $res;

	// get in indexes in the array for the collection objects
	$first = $this->detect_default_object($res[0], $_POST['bdm_default_xp_name'].' Levels');
	$second = $this->detect_default_object($res[0], 'Comments Levels');
	$third = $this->detect_default_object($res[0], 'Checkins Levels');

	global $bd_default_parms; 
	require_once( dirname(__FILE__).'/wp-bigdoor-defaults.php' );


	// create default levels within each collection
	// Experience - 20 lvls
	$ret = $this->create_default_levels($res[0][$first], 6, 'XP'); 
	if (!$ret)
	    return $ret;

	// Comments - 10 lvls
	$ret = $this->create_default_levels($res[0][$second],5, 'Comments'); 
	if (!$ret)
	    return $ret;

	// Checkins - 10 lvls
	$ret = $this->create_default_levels($res[0][$third], 5, 'Checkins'); 
	if (!$ret)
	    return $ret;

	set_time_limit(90);



    // Create Perfect Attendence Award ------------------------------------

	$res = $this->bd_api_caller('award_collection_get');

	$first = false;

	for($i=0; $i<count($res[0]); $i++) { 
	    if (strtolower($_POST['bdm_default_att_award_name']) == strtolower($res[0][$i]['pub_title']) )  {
		$first = $i;
		break;
	    }
	}

	if ($first !== false) {
		echo '<p>'.$_POST['bdm_default_xp_name'].' ';
		_e('award collection already created.', $this->localizationDomain);
		echo ' ';
		_e('Collection name / ID: ', $this->localizationDomain);
		echo $res[0][$first]['id']. ' / '.  $curr[0][$first]['pub_title'];
	    } else {
		_e('<p>Creating ', $this->localizationDomain);
		echo $_POST['bdm_default_xp_name'].' ';
		_e('award collection...', $this->localizationDomain);
		$res = $this->create_attendance_award( $_POST['bdm_default_att_award_name'], $bd_default_parms['attendance']['url']);
		if ($res !== true) 
		    return $res; // return error message - can't continue
		echo ' ... done</p>';
	    }

	$res = $this->bd_api_caller('award_collection_get');
	update_option('bd_award_collection', $res);

	
	flush();
	set_time_limit(90);



    // Create transactions and transaction groups


	// load currency to make sure we have an update to date object
	$curr = $this->bd_api_caller('currency_types');

	$xp = $this->detect_default_object($curr[0], $_POST['bdm_default_xp_name']);
	$com = $this->detect_default_object($curr[0], 'Comments');
	$chk = $this->detect_default_object($curr[0], 'Checkins');

	flush();
	set_time_limit(90);



	// transactions ----------------------------------------------

	$tres = $this->bd_api_caller('named_transaction_get');

	$chk_inc= $this->detect_default_object($tres[0], 'Checkin increment');
	if ($chk_inc === false) { 
	    _e('<p>Creating checkin increment transaction', $this->localizationDomain);
	    $res = $this->create_transaction($curr[0][$chk]['id'], 'Checkin increment');
	    if (!is_array($res)) 
		return $res;
	    _e(' - done. ID: ', $this->localizationDomain);
	    echo $res[0]['id'];
	    update_option('checkin_increment',$res);
	    $tres = $this->bd_api_caller('named_transaction_get');
	    $chk_inc= $this->detect_default_object($tres[0], 'Checkin increment');
	} else {
	    _e('<p>Checkin increment transaction already created', $this->localizationDomain);
	    update_option('checkin_increment',$tres);
	}

	flush();
	set_time_limit(90);

	$chk_xp= $this->detect_default_object($tres[0], 'Checkin XP');
	if ($chk_xp === false) { 
	    _e('<p>Creating checkin experience transaction', $this->localizationDomain);
	    $res = $this->create_transaction($curr[0][$xp]['id'], 'Checkin XP', $_POST['bdm_default_comment_action']);
	    if (!is_array($res)) 
		return $res;
	    _e(' - done. ID: ', $this->localizationDomain);
	    echo $res[0]['id'];
	    update_option('checkin_xp',$res);
	    $tres = $this->bd_api_caller('named_transaction_get');
	    $chk_xp= $this->detect_default_object($tres[0], 'Checkin XP');
	} else { 
	    _e('<p>Checkin experience transaction already created', $this->localizationDomain);
	    update_option('checkin_xp',$tres);
	}

	flush();
	set_time_limit(90);

	$com_xp =  $this->detect_default_object($tres[0], 'Comment XP');
	if ($com_xp === false) { 
	    _e('<p>Creating comment experience transaction', $this->localizationDomain);
	    $res = $this->create_transaction($curr[0][$xp]['id'], 'Comment XP', $_POST['bdm_default_checkin_action']);
	    if (!is_array($res)) 
		return $res;
	    _e(' - done. ID: ', $this->localizationDomain);
	    echo $res[0]['id'];
	    update_option('comment_xp',$res);
	    $tres = $this->bd_api_caller('named_transaction_get');
	    $com_xp =  $this->detect_default_object($tres[0], 'Comment XP');
	} else {
	    _e('<p>Comment experience transaction already created', $this->localizationDomain);
	    update_option('comment_xp',$tres);
	}

	flush();
	set_time_limit(90);

	$com_inc= $this->detect_default_object($tres[0], 'Comment increment');
	if ($com_inc === false) { 
	    _e('<p>Creating comment increment transaction', $this->localizationDomain);
	    $res = $this->create_transaction($curr[0][$com]['id'], 'Comment increment');
	    if (!is_array($res)) 
		return $res;
	    _e(' - done. ID: ', $this->localizationDomain);
	    echo $res[0]['id'];
	    update_option('comment_increment',$res);
	    $tres = $this->bd_api_caller('named_transaction_get');
	    $com_inc= $this->detect_default_object($tres[0], 'Comment increment');
	} else {
	    _e('<p>Comment increment transaction already created', $this->localizationDomain);
	    update_option('comment_increment',$tres);
	}

	flush();
	set_time_limit(90);




	// transaction groups ----------------------------------------------

	$tgres = $this->bd_api_caller('named_transaction_group_get');

	$com_trans_grp= $this->detect_default_object($tgres[0], 'Comment Group');

	if ($com_trans_grp === false) { 
	    _e('<p>Creating comment transaction group', $this->localizationDomain);
	    $res = $this->create_transaction_group('Comment Group');
	    if (!is_array($res)) 
		return $res;
	    _e(' - done. ID: ', $this->localizationDomain);
	    echo $res[0]['id'];
	    update_option('comment_trans_group',$res[0]);
	    $tgres = $this->bd_api_caller('named_transaction_group_get');
	    $com_trans_grp= $this->detect_default_object($tgres[0], 'Comment Group');
	} else {
	    _e('<p>Comment transaction group already created', $this->localizationDomain);
	    update_option('comment_trans_group',$tgres[0][$com_trans_grp]);
	}

	flush();
	set_time_limit(90);




	$chk_trans_grp = $this->detect_default_object($tgres[0], 'Checkin Group');

	if ($chk_trans_grp === false) { 
	    _e('<p>Creating checkin transaction group', $this->localizationDomain);
	    $res = $this->create_transaction_group('Checkin Group', array('end_user_cap' => 1, 'end_user_cap_interval' => 600) );
	    if (!is_array($res)) 
		return $res;
	    _e(' - done. ID: ', $this->localizationDomain);
	    echo $res[0]['id'];
	    update_option('checkin_trans_group',$res[0]);
	    $tgres = $this->bd_api_caller('named_transaction_group_get');
	    $chk_trans_grp = $this->detect_default_object($tgres[0], 'Checkin Group');
	} else {
	    _e('<p>Checkin transaction group already created', $this->localizationDomain);
	    update_option('checkin_trans_group',$tgres[0][$chk_trans_grp]);
	}

	flush();
	set_time_limit(90);




	// associate transactions to groups ----------------------------------------------


	$got_it = false;
	foreach($tgres[0][$chk_trans_grp]['named_transactions'] as $trans) { 
	    if ( $trans['id'] == $tres[0][$chk_inc]['id'] ) {
		$got_it = true;
		break;
	    }
	}


	if (!$got_it) { 
	//if (!get_option('checkin_inc_trans_group_assoc','')) { 
	    _e('<p>Creating checkin increment transaction group association:', $this->localizationDomain);
	    echo ' '.$tgres[0][$chk_trans_grp]['id'].' <-> '.$tres[0][$chk_inc]['id'];
	    $res = $this->create_transaction_assoc('Checkin Inc Assoc', $tgres[0][$chk_trans_grp]['id'], $tres[0][$chk_inc]['id'] );
	    if (!is_array($res)) 
		return $res;
	    _e(' - done. ID: ', $this->localizationDomain);
	    echo $res[0]['id'];
	    update_option('checkin_inc_trans_group_assoc',$res);
	} else 
	    _e('<p>Checkin increment transaction group association already created', $this->localizationDomain);


	flush();
	set_time_limit(90);

	$got_it = false;
	foreach($tgres[0][$chk_trans_grp]['named_transactions'] as $trans) { 
	    if ( $trans['id'] == $tres[0][$chk_xp]['id'] ) {
		$got_it = true;
		break;
	    }
	}

	if (!$got_it) { 	   
	    _e('<p>Creating checkin experience transaction group association', $this->localizationDomain);
	    echo ' '.$tgres[0][$chk_trans_grp]['id'].' <-> '.$tres[0][$chk_xp]['id'];
	    $res = $this->create_transaction_assoc('Checkin XP Assoc', $tgres[0][$chk_trans_grp]['id'], $tres[0][$chk_xp]['id'], true );
	    if (!is_array($res)) 
		return $res;
	    _e(' - done. ID: ', $this->localizationDomain);
	    echo $res[0]['id'];
	    update_option('checkin_xp_trans_group_assoc',$res);
	} else 
	    _e('<p>Checkin experience transaction group association already created', $this->localizationDomain);


	flush();
	set_time_limit(90);


	$got_it = false;
	foreach($tgres[0][$com_trans_grp]['named_transactions'] as $trans) { 
	    if ( $trans['id'] == $tres[0][$com_inc]['id'] ) {
		$got_it = true;
		break;
	    }
	}

	if (!$got_it) { 	
	    _e('<p>Creating comment increment transaction group association', $this->localizationDomain);
	    echo ' '.$tgres[0][$com_trans_grp]['id'].' <-> '.$tres[0][$com_inc]['id'];
	    $res = $this->create_transaction_assoc('Comment INC Assoc', $tgres[0][$com_trans_grp]['id'], $tres[0][$com_inc]['id'] );
	    if (!is_array($res)) 
		return $res;
	    _e(' - done. ID: ', $this->localizationDomain);
	    echo $res[0]['id'];
	    update_option('comment_inc_trans_group_assoc',$res);
	} else 
	    _e('<p>Comment increment transaction group association already created', $this->localizationDomain);


	flush();
	set_time_limit(90);

	$got_it = false;
	foreach($tgres[0][$com_trans_grp]['named_transactions'] as $trans) { 
	    if ( $trans['id'] == $tres[0][$com_xp]['id'] ) {
		$got_it = true;
		break;
	    }
	}

	if (!$got_it) { 	
	    _e('<p>Creating comment experience transaction group association', $this->localizationDomain);
	    echo ' '.$tgres[0][$com_trans_grp]['id'].' <-> '.$tres[0][$com_xp]['id'];
	    $res = $this->create_transaction_assoc('Comment XP Assoc', $tgres[0][$com_trans_grp]['id'], $tres[0][$com_xp]['id'], true );
	    if (!is_array($res)) 
		return $res;
	    _e(' - done. ID: ', $this->localizationDomain);
	    echo $res[0]['id'];
	    update_option('comment_xp_trans_group_assoc',$res);
	} else 
	    _e('<p>Comment experience transaction group association already created', $this->localizationDomain);


	flush();
	set_time_limit(90);


	// Done! 

	// Now that we have most likely created the default level collections, associated levels, currencies, etc
	// we can cache local copies of levels and currencies in the database. First refresh the objects, then store them in the WP options table.
	
	// reload the currency object
	$curr = $this->bd_api_caller('currency_types');
	update_option('bd_currencies', $curr);

	// levels collection
	$res = $this->bd_api_caller('level_collection_get');
	update_option('bd_level_collection',$res);

	return true;

    }





    // ==================================================================
    // =========== ADMIN CONFIG SCREENS AND HELPER FUNCTIONS ============
    // ==================================================================


            
            /**
             *  Add a link to WP BigDoor options page that leads to the plugin API Settings page.
             */
            function filter_plugin_actions($links, $file) {
               $settings_link = '<a href="admin.php?page=bigdoor">' . __('Settings') . '</a>';
               array_unshift( $links, $settings_link ); // insert it before other links
               return $links;
            }
            

	    /**
	    * Used to draw level item form fields, and to clone level item divs in the admin panel 
	    */
	    function gen_level_item_clone($csid='',$lvl='',$url='', $idx='', $prefix='', $type='', $arr='', $title='') { 

		// DO NOT MODIFY THIS FUNCTION'S CODE unless you've read and understood the notes in 
		// the admin_set_levels() function and have thoroughly researched the 
		// implications of making changes.

		// Note: $type is the index number of the related named_level_collection array.
		// We store it in the form field name array so that we can retrieve it when the form is posted. 
		// Then we use it to put the posted data back into the collection array in the proper place.

		// $arr is a serialized version of the entire named_level array elements
		// $title is the title of the level

		if ('' == $csid) { 
		    $css = 'display:none';
		    $class = '';
		} else { 
		    $class = 'class="bd_level_item"';
		}

		if ('' == $csid) $csid = 'bd_level_item_clone';

		if ('' == $idx) {
		    $id = 'bd_edit_level_tmp';
		} else {
		    $id = $prefix.'_edit_level_'.$idx;
		}

		if ('' != $prefix) {
		    $pre = 'javascript:bd_level_edit(\'' . $prefix . '\', ' . $idx . ')'; 
		    $del = 'javascript:bd_level_del(\'' . $prefix . '\', ' . $idx . ')';
		} else 
		    $pre = '';

		return '
		<div id="'.$prefix.$csid.'" style="'.$css.'" '.$class.'>
			<div class="level_label" style="float:left; margin-left: 10px;">'.$title.'</div>
			<div style="float:right; margin-right: 10px">
				<a class="level_edit_link" href="'.$pre.'" title=" ' . __('Edit', $this->localizationDomain) . ' "></a>
				<a class="level_del_link"  href="'.$del.'" title=" ' . __('Delete', $this->localizationDomain).' "></a>
			</div>
			<div id="'.$id.'" style="display:none;">
			    <table class="bd_edit_table" width="100%" cellspacing="1" cellpadding="1">
			    <tr><td>'.__('Title', $this->localizationDomain).':</td><td><input class="bdtitle" type="text" name="'.$prefix.'[lvl_title]['.$type.'][]" value="'.$title.'" style="width: 80%" /></td></tr>
			    <tr><td>'.__('Threshold', $this->localizationDomain).':</td><td><input class="bdlvl" type="text" name="'.$prefix.'[level_val]['.$type.'][]" value="'.$lvl.'" style="width: 80%" /></td></tr>
			    <tr><td>'.__('Badge URL', $this->localizationDomain).':</td><td>
				<input type="hidden" name="'.$prefix.'[arr]['.$type.'][]" value="'.base64_encode($arr).'" />
				<input class="bdurl" type="text" name="'.$prefix.'[badge_url]['.$type.'][]" value="'.$url.'" style="width: 80%" />
			    </td></tr>
			    <tr><td></td><td><img style="margin-top: 5px" class="level_badge_preview" src="'.$url.'"></td><td>
			    </table>
			</div>
		</div>
		';
		

	    }



	    // Extract POST form data and and build a new set of named_level subarray elements.
	    // Then replace the existing subarray with the new subarray.
	    // This allows for edits and deletes and adding new levels. 
	    function local_update_named_levels($name, $idx) { 

		$named_levels = array();

		for($i=0; $i<count($_POST[$name]['level_val'][$idx]); $i++) { 

			// get the vals from the form fields
			$title = $_POST[$name]['lvl_title'][$idx][$i];
			$thresh = $_POST[$name]['level_val'][$idx][$i];
			$url = $_POST[$name]['badge_url'][$idx][$i];

			// get the array from the form field
			$arr = $_POST[$name]['arr'][$idx][$i];

			if ('' != $arr) { // if the level exists then there should be an encoded array for it
			    $arr = maybe_unserialize(base64_decode($arr));
			} else { // if this is a new level, there's no encoded array yet.
			    $arr = array();
			}

			// plug the new values into the named_level array, 
			// note that threshold and url cannot be blank
			$arr['pub_title'] = $title;

			if ('' != $thresh) 
			    $arr['threshold'] = $thresh;

			// there should be only one url element, so assume index 0 for that one: 
			if ('' != $url)
			    $arr['urls'][0]['url'] = $url;
		    
			// add it to our new set of named_levels
			$named_levels[] = $arr; 
		}

		return $named_levels;

	    }


	    /** 
	    * Updates remote BigDoor server is new values for levels
	    */
	    function remote_update_named_levels($levels, $idx, $collection_id) { 
		global $wp_bigdoor_var;

		    // update levels
		    foreach ($levels as $lvl) { 
			$id = $lvl['id'];
			$thresh = $lvl['threshold'];
			$title = $lvl['pub_title'];
			$url = $lvl['urls'][0]['url'];
			$url_id = $lvl['urls'][0]['id'];

			if ('' == trim($thresh) || '' == trim($title) || '' == trim($url) )
			    continue;

			set_time_limit(90);

			if ('' != $id) { // if there is an ID then the level exists, update it.

			    if ('' == $url_id) { // add one
				$res = $this->create_and_assoc_url($title, $url, $id, false); 
			    } else { // update one
				$res = $this->update_url( $url_id, array('url' => $url) );
				if (!is_array($res)) return false; 
			    }

			    set_time_limit(90);
			    $res = $this->update_level( $id, array('threshold' => $thresh, 'pub_title' => $title) );

			} else { // if there's no ID for this level then it's new, so create it

			    $res = $this->create_level($title, '', $thresh, $collection_id, $url);

			}


			echo '<li class="bd_progress">&nbsp </li>';

			flush();

			if (!is_array($res)) return false; 
		    }

		    // delete levels that no longer exist locally
		    foreach($wp_bigdoor_var->levels[0][$idx]['named_levels'] as $lvl) { 


			$exists = false;

			for ($i=0; $i<count($levels); $i++) { 
			    if ($levels[$i]['id'] == $lvl['id']) {
				    $exists = true;
				    continue;
			    }
			}

			if (!$exists) { 
			    $id = $lvl['id'];
			    $title = $lvl['pub_title'];
			    echo '<li class="bd_progress">&nbsp </li>';

			    flush();
			    set_time_limit(90);
			    $res = $this->delete_level($wp_bigdoor_var->levels[0][$idx]['id'], $id); 

			    if (!is_array($res)) return false; 
			} 
		    }


		return true;

	    }



            /**
             *  Admin page for define user level settings ========= LEVELS ==========
             */
	    function admin_set_levels() { 
		global $wp_bigdoor_var;

		/*
		    Note: This function uses the stored level collection objects that are returned from the BigDoor API.
		    Each time this admin page loads it pulls the collection objects from BigDoor. 

		    When the settings are saved the objects are cached locally and sent back to BigDoor. 

		    This keeps the local server in sync with the BigDoor servers.

		    BE EXTREMELY CAREFUL if you decide to modify the form fields and related PHP code! 
		    Make sure you completely understand the relationships of form fields to collection object arrays
		    before you proceed to make modifications! 

		    Also note that if you decide to modify CSS classes and IDs then you might break the 
		    associated Javascript unless you adjust that too. 

		    There are 3 name_level_collection objects: XP, Comments, and Checkins
		    Each have their own set of levels. 
		
		*/

	    ?>
                <div class="wrap">
                <h2>BigDoor: Set Levels</h2>
	    <?php

		$msg = '';

		// get the named_level_colletions from the BigDoor server, store a copy locally
		$colls = $this->bd_api_caller('level_collection_get');
		if (is_array($colls)) {
		    update_option('bd_level_collection', $colls);
		    $wp_bigdoor_var->levels = $colls;
		} else { 
		    $msg = __('Error getting level collections from BigDoor server. Is there a connection problem?', $this->localizationDomain);
		}


		// updating settings?
                if($_POST['WP_Big_Door_save_levels']) {

			if (! wp_verify_nonce($_POST['_wpnonce'], 'WP_Big_Door-update-levels') ) 
			    die(__('Ooops! There was a problem with the data you posted. Please go back, reload the page, and try again.', $this->localizationDomain)); 

			// Quick hack to get the IDX values out of the POST array from where we stored them. 
			// We need these to know where to put the data back into the array of collection objects.
			foreach($_POST['bd_chk']['level_val'] as $key => $val) { 
			    $chk_idx = $key;
			    break;
			}
			foreach($_POST['bd_com']['level_val'] as $key => $val) { 
			    $com_idx = $key;
			    break;
			}
			foreach($_POST['bd_xp']['level_val'] as $key => $val) { 
			    $xp_idx = $key;
			    break;
			}


			// make a temp copy of the locally cached object collection
			$tmp_coll = $wp_bigdoor_var->levels[0];

			// merge POST data into named_level array
			$xp_named_levels = $this->local_update_named_levels('bd_xp', $xp_idx);
			$tmp_coll[$xp_idx]['named_levels'] = $xp_named_levels;

			$chk_named_levels = $this->local_update_named_levels('bd_chk', $chk_idx);
			$tmp_coll[$chk_idx]['named_levels'] = $chk_named_levels;

			$com_named_levels = $this->local_update_named_levels('bd_com', $com_idx);
			$tmp_coll[$com_idx]['named_levels'] = $com_named_levels;

			?>
			<div id="bd_update_levels_id">
			    <br/><br/>
				    <img src="<?php echo $wp_bigdoor_var->bd_plugin_url?>images/wait_spinner.gif" />
				    <strong><?php _e('Updating your settings and synchronizing with the BigDoor servers, please wait...', $this->localizationDomain) ?></strong>
				    <br/><br/>
				    <div id="bd_progress_wrap">
					<ul>
			<?php


			// send any updates to the remote BigDoor server using the temp level arrays
			$res = $this->remote_update_named_levels($xp_named_levels, $xp_idx, $tmp_coll[$xp_idx]['id']);
			if (!$res) {
				$msg .= __('Error updating default experience levels to BigDoor server<p>', $this->localizationDomain);
			}

			$res = $this->remote_update_named_levels($chk_named_levels, $chk_idx, $tmp_coll[$chk_idx]['id']);
			if (!$res) {
				$msg .= __('Error updating Checkins levels to BigDoor server<p>', $this->localizationDomain);
			}

			$res = $this->remote_update_named_levels($com_named_levels, $com_idx, $tmp_coll[$com_idx]['id']);
			if (!$res) {
				$msg .= __('Error updating Comments levels to BigDoor server<p>', $this->localizationDomain);
			}


			// if updates went Ok then update the local cache copy
			if ($res !== false) { 
			    $colls = $this->bd_api_caller('level_collection_get');
			    if (is_array($colls)) {
				update_option('bd_level_collection', $colls);
				$wp_bigdoor_var->levels = $colls;
				$msg = __('Level settings updated successfully!', $this->localizationDomain);
			    } else { 
				$msg .= __('Error getting level collections from BigDoor server.<p>', $this->localizationDomain);
			    }
			}

			?>
					</ul>
				    </div>
			</div>
			<script>
				jQuery('#bd_progress_wrap').css('background-color','#AAFFAA');
				remove_bd_update_div();
			</script>
			<?php



                } // end of update routine

 
		if ('' == $wp_bigdoor_var->default_xp_name) { 
		    _e('You must first install the default settings. Visit the API Settings screen to continue', $this->localizationDomain);
		    return;
		}

		$xp_idx = '';
		$com_idx = '';
		$chk_idx = '';

		// find the index of the default xp level and the comments and checkins levels within the array of objects
		// we can't assume that they're in order, so we have to find them.
		for ($i=0; $i<count($wp_bigdoor_var->levels[0]); $i++) { 
		    if ( strtolower($wp_bigdoor_var->levels[0][$i]['pub_title']) == strtolower($wp_bigdoor_var->default_xp_name.' Levels') ) { 
			$xp_idx = $i;
		    }
		    if ( strtolower($wp_bigdoor_var->levels[0][$i]['pub_title']) == strtolower('Comments Levels') ) { 
			$com_idx = $i;
		    }
		    if ( strtolower($wp_bigdoor_var->levels[0][$i]['pub_title']) == strtolower('Checkins Levels') ) { 
			$chk_idx = $i;
		    }
		}


		if ('' === $xp_idx) {
		    _e('Unable to locate your default experience level collection. Are the default settings installed yet?', $this->localizationDomain);
		    return;
		}
		if ('' === $com_idx) {
		    _e('Unable to locate your comments level collection. Are the default settings installed yet?', $this->localizationDomain);
		    return;
		}
		if ('' === $chk_idx) {
		    _e('Unable to locate your checkins levels collection. Are the default settings installed yet?', $this->localizationDomain);
		    return;
		}

	    ?>
 


		<?php // hidden div that we use for cloning new levels
		    echo $this->gen_level_item_clone();
		?>



		<?php if ('' !== $msg) { ?>
		    <div class="updated fade below-h2" id="message"><p><?php echo $msg ?></p></div>
		<?php } ?>

                <form id="bd_level_form" method="post" id="WP_Big_Door_levels">
                <?php wp_nonce_field('WP_Big_Door-update-levels'); ?>

                    <table width="100%" cellspacing="2" cellpadding="5" class="form-table"> 
                        <tr valign="top"> 
                            <td width="33%" scope="row" class="bd_headers"><?php _e('Check-ins', $this->localizationDomain); ?></td> 
                            <td width="33%" scope="row" class="bd_headers"><?php _e('Comments', $this->localizationDomain); ?></td> 
                            <td width="33%" scope="row" class="bd_headers"><?php _e('XP', $this->localizationDomain); ?></td> 
			</tr>
                        <tr valign="top"> 
                            <td>
                                <div id="bd_chk_items">
				<?php
				    $bd_level_options = $wp_bigdoor_var->levels[0][$chk_idx]['named_levels'];
				    for ($i=0; $i<count($bd_level_options); $i++) { 
					echo $this->gen_level_item_clone('bd_level_item_'.($i+1), $bd_level_options[$i]['threshold'], $bd_level_options[$i]['urls'][0]['url'], $i+1, 'bd_chk', $chk_idx, serialize($bd_level_options[$i]), $bd_level_options[$i]['pub_title']  );
				    }
				?>
				</div>
				<a href="#" onclick="return bd_add_level('chk',2)">Add Level</a>
                            </td> 

                            <td>
                                <div id="bd_com_items">
				<?php
				    $bd_level_options = $wp_bigdoor_var->levels[0][$com_idx]['named_levels'];
				    for ($i=0; $i<count($bd_level_options); $i++) { 
					echo $this->gen_level_item_clone('bd_level_item_'.($i+1), $bd_level_options[$i]['threshold'], $bd_level_options[$i]['urls'][0]['url'], $i+1, 'bd_com', $com_idx, serialize($bd_level_options[$i]), $bd_level_options[$i]['pub_title']);
				    }
				?>
				</div>
				<a href="#" onclick="return bd_add_level('com',1)">Add Level</a>

                            </td> 

                            <td>
                                <div id="bd_xp_items">
				<?php
				    for ($i=0; $i<count($bd_level_options); $i++) { 
					$bd_level_options = $wp_bigdoor_var->levels[0][$xp_idx]['named_levels'];
					echo $this->gen_level_item_clone('bd_level_item_'.($i+1), $bd_level_options[$i]['threshold'], $bd_level_options[$i]['urls'][0]['url'], $i+1, 'bd_xp', $xp_idx, serialize($bd_level_options[$i]), $bd_level_options[$i]['pub_title']);
				    }
				?>
				</div>
				<a href="#" onclick="return bd_add_level('xp',0)">Add Level</a>
                            </td> 
                        </tr>
                        <tr>
                            <th colspan=3><input class="thickbox button" id="bd_level_form_button" alt="#TB_inline?height=75&width=300&inlineId=bdwaitwindow&modal=false" type="button" name="Save Settings" value="<?php _e('Save Level Settings', $this->localizationDomain)?>" /></th>
			    <td><input type="hidden" name="WP_Big_Door_save_levels" value="1"><br/><br/></td>
                        </tr>
		    </table>
		</form>
		</div>

		<div id="bdwaitwindow" style="display:none">
		<p><img src="<?php echo $wp_bigdoor_var->bd_plugin_url ?>/images/wait_spinner.gif" > <?php _e('Saving your settings, please wait....', $this->localizationDomain)?></p>
		</div>

	    <?php
	    }



            /**
             *  Admin page for misc options ========= MISC OPTIONS ==========
             */
	    function admin_set_options() { 
		global $wp_bigdoor_var;

		$ac = get_option('bd_award_collection', '');

		$msg = true;

                if($_POST['WP_Big_Door_save_gen_opts']) {

                    if (! wp_verify_nonce($_POST['_wpnonce'], 'WP_Big_Door-update-general-options') ) 
			die('Whoops! There was a problem with the data you posted. Please go back and try again.'); 

		    $place = $_POST['bdm_static_placement'];

		    $bd_gen_options = array('bdm_static_placement' => $place,
					    'bdm_attendance_award_name' => $_POST['bdm_attendance_award_name'],
					    'bdm_attendance_award_url' => $_POST['bdm_attendance_award_url']
				    );
		    
		    $resn = $this->award_update($ac[0][0]['named_awards'][0]['id'], 
					array(
					    'pub_title' => $_POST['bdm_attendance_award_name']
					));
					
		    $resa = $this->update_url($ac[0][0]['named_awards'][0]['urls'][0]['id'], 
					array(
					    'url' => $_POST['bdm_attendance_award_url'],
					));

		    if (!$resn || !$resa) { 

			$msg = __('Error synchronizing with the BigDoor servers.', $this->localizationDomain);

		    } else { 

			$msg = __('Settings updated!', $this->localizationDomain);
			update_option('bd_gen_options', $bd_gen_options);

			$res = $this->bd_api_caller('award_collection_get');
			update_option('bd_award_collection', $res);
			$ac = get_option('bd_award_collection', '');
		    }


                }


		$bd_gen_options = get_option('bd_gen_options',true);

		$checked = "checked='checked'";

                ?>                                   
                <div class="wrap">
                <h2><?php _e('BigDoor: Miscellaneous Options',$this->localizationDomain) ?></h2>
		    <?php if ($msg !== true) { ?>
			<div class="updated fade below-h2" id="message"><p><?php echo $msg ?></p></div>
		    <?php } ?>
                <form method="post" id="WP_Big_Door_general_options">
                <?php wp_nonce_field('WP_Big_Door-update-general-options'); ?>
                    <table width="100%" cellspacing="2" cellpadding="5" class="form-table"> 
                        <tr valign="top"> 
                            <th width="33%" scope="row"><?php _e('Use static non-moving widget placement:', $this->localizationDomain); ?></th> 
                            <td>
                                <input name="bdm_static_placement" type="radio" id="bdm_user_login_trans_id" <?php if ($bd_gen_options['bdm_static_placement'] == 0) echo $checked ?> value="0"/> None <br/>
                                <input name="bdm_static_placement" type="radio" id="bdm_user_login_trans_id" <?php if ($bd_gen_options['bdm_static_placement'] == 'tl') echo $checked ?> value="tl"/> Top left<br/>
                                <input name="bdm_static_placement" type="radio" id="bdm_user_login_trans_id" <?php if ($bd_gen_options['bdm_static_placement'] == 'tr') echo $checked ?> value="tr"> Top right<br>
                                <input name="bdm_static_placement" type="radio" id="bdm_user_login_trans_id" <?php if ($bd_gen_options['bdm_static_placement'] == 'bl') echo $checked ?> value="bl"/> Bottom left<br/>
                                <input name="bdm_static_placement" type="radio" id="bdm_user_login_trans_id" <?php if ($bd_gen_options['bdm_static_placement'] == 'br') echo $checked ?> value="br"/> Bottom right<br/>
                            </td> 
                        </tr>

                        <tr valign="top"> 
                            <th width="33%" scope="row"><?php _e('Perfect Attendance Award:', $this->localizationDomain); ?><br/>
				<span style="font-size: 11px"><?php _e('Users who check in every day for 30 days will get this special award', $this->localizationDomain); ?></span>
			    </th> 
                            <td>
				<table>
				<tr><td>Name</td><td><input type="text" name="bdm_attendance_award_name" value="<?php echo $ac[0][0]['named_awards'][0]['pub_title'] ?>" /></td></tr>
				<tr><td>Badge URL:</td><td><input type="text" name="bdm_attendance_award_url" value="<?php echo $ac[0][0]['named_awards'][0]['urls'][0]['url']?>" /></td></tr>
				<tr><td></td><td><img class="level_badge_preview" src="<?php echo $ac[0][0]['named_awards'][0]['urls'][0]['url']?>" /></td></tr>
				</table>
			    </td>
			<tr>

                        <tr>
                            <th colspan=3><input class="button" type="submit" name="WP_Big_Door_save_gen_opts" value="Save Options" /></th>
                        </tr>
		    </table>
		</div>
		<?php
	    }



            /**
             *  Admin page for installing defaults  ========= INSTALL DEFAULTS  ==========
             */
	    function admin_install_defaults() { 
		global $wp_bigdoor_var;

		if (!$wp_bigdoor_var->bd_options_array['bdm_public_api_key'] || !$wp_bigdoor_var->bd_options_array['bdm_private_api_key']) { 
		    $msg = __('You must configure your API keys before you can install the default options!',$this->localizationDomain);
		}

		?>
                <div class="wrap">
                <h2>BigDoor: Install Default Settings</h2>
		<?php 

		$msg = true;


		if($_POST['WP_Big_Door_GenLvl']) {

                    if (! wp_verify_nonce($_POST['_wpnonce'], 'WP_Big_Door-update-options') ) die('Whoops! There was a problem. Try refreshing the page first.'); 

		    if ('' != $_POST['bdm_default_xp_name']) {


			if ('' == $_POST['bdm_default_att_award_name'])  	    
				$_POST['bdm_default_att_award_name'] = __('Perfect Attendance Award', $this->localizationDomain);

			// gotta store this value so we have it available when handling the "Set levels" admin page:
			update_option('bd_default_xp_name', trim($_POST['bdm_default_xp_name']) );
			update_option('bdm_default_att_award_name', trim($_POST['bdm_default_att_award_name']) );

			require_once('wp-bigdoor-admin.php');

			echo '<p><img id="bd_defaults_spinner" src="'.$wp_bigdoor_var->bd_plugin_url.'images/wait_spinner.gif"> ';
			_e('Creating default settings, please wait, this could take a few minutes. You will see a confirmation below when complete. DO NOT navigate away from this page until the process is complete!</p>', $this->localizationDomain);
			flush();
			@ob_flush(); // just in case

			if ('' == trim($_POST['bdm_default_comment_action']) ) 
			    $_POST['bdm_default_comment_action'] = '25';

			if ('' == trim($_POST['bdm_default_checkin_action']) ) 
			    $_POST['bdm_default_checkin_action'] = '10';

			$res = $this->create_default_objects();

			if ($res === true) 
			    $msg = __('Default settings created! Click the BigDoor menu item to see the new menu options.', $this->localizationDomain);
			else 
			    $msg = __('<strong><em>AN ERROR OCCURRED during processing. Please wait a moment then try again</em></strong>', $this->localizationDomain);

			echo '<script>remove_default_install_spinner()</script>';

			echo '<div class="updated fade below-h2" id="message"><p>'.$msg.'</p></div>';

			return;

		    } else { 

			$msg = __('You must specify a name for your Experience currency', $this->localizationDomain);

		    }

		} 

		$def_name = get_option('bd_default_xp_name', '');

		if ('' == $_POST['bdm_default_xp_name'] && '' != $def_name)
		    $_POST['bdm_default_xp_name'] = $def_name;
		else if ('' == $_POST['bdm_default_xp_name']) { 	    
		    $_POST['bdm_default_xp_name'] = __('XP', $this->localizationDomain);

		}

		// perfect attendance award name
		$def_att_name = get_option('bdm_default_att_award_name', '');

		if ('' == $_POST['bdm_default_att_award_name'] && '' != $def_att_name)
		    $_POST['bdm_default_att_award_name'] = $def_att_name;
		else if ('' == $_POST['bdm_default_att_award_name']) { 	    
		    $_POST['bdm_default_att_award_name'] = __('Perfect Attendance Award', $this->localizationDomain);
		}


		?>

		    <?php if ($msg !== true) { ?>

			<div class="updated fade below-h2" id="message"><p><?php echo $msg ?></p></div>

		    <?php } ?>

			<form method="post" id="bd_defaults_form">
			<?php wp_nonce_field('WP_Big_Door-update-options'); ?>

			<?php 
			if ( 
			    ($wp_bigdoor_var->bd_options_array['bdm_public_api_key'] && $wp_bigdoor_var->bd_options_array['bdm_private_api_key']) 
			    && (!$wp_bigdoor_var->currencies || !$wp_bigdoor_var->levels || 
			    !$wp_bigdoor_var->comment_trans_id && !$wp_bigdoor_var->checkin_trans_id)
			    ) { 
			?> 
			    <p><strong><?php _e('Before you can configure other options, including levels, you must generate the default settings.', $this->localizationDomain);?></strong></p>
			    <p><?php _e('This process will create 3 types of level groups, each with its own currency, as follows:', $this->localizationDomain);?></p>
			    <ul style="list-style:circle; margin-left: 40px">
			    <li><?php _e('Experience: XP currency (or whatever name you give it) with 6 levels', $this->localizationDomain);?></li>
			    <li><?php _e('Checkin: with 5 levels', $this->localizationDomain);?></li>
			    <li><?php _e('Comments: with 5 levels', $this->localizationDomain);?></li>
			    </ul>
			    <p><?php _e('After the process completes you will find new menu items in the BigDoor section of the admin sidebar, and this particular screen will no longer be available.', $this->localizationDomain);?></p>

			    <table width="100%" cellspacing="2" cellpadding="5" class="form-table"> 

				<tr valign="top"> 
				    <th width="33%" scope="row"><?php _e('What would you like to call your overall site Experience currency?', $this->localizationDomain); ?></th> 
				    <td>
					<input name="bdm_default_xp_name" type="text" class="bdm_user_login_trans_id" value="<?php echo $_POST['bdm_default_xp_name'] ?>"/><br/>
					<em><?php _e('Hint: this should probably be short, maybe 3 letters or less', $this->localizationDomain); ?></em>
				    </td> 
				</tr>

				<tr valign="top"> 
				    <th width="33%" scope="row"><?php _e('How much Experience currency does a user get for posting a comment?', $this->localizationDomain); ?></th> 
				    <td>
					<input name="bdm_default_comment_action" type="text" class="bdm_user_login_trans_id" value="25"/><br/>
					<em><?php _e('If you leave this blank a default value of 25 will be used', $this->localizationDomain); ?></em>
				    </td> 
				</tr>

				<tr valign="top"> 
				    <th width="33%" scope="row"><?php _e('How much Experience currency does a user get for checking in?', $this->localizationDomain); ?></th> 
				    <td>
					<input name="bdm_default_checkin_action" type="text" class="bdm_user_login_trans_id" value="10"/><br/>
					<em><?php _e('If you leave this blank a default value of 10 will be used', $this->localizationDomain); ?></em>
				    </td> 
				</tr>

				<tr valign="top"> 
				    <th width="33%" scope="row"><?php _e('What do you want to call your perfect attendance award?', $this->localizationDomain); ?></th> 
				    <td>
					<input name="bdm_default_att_award_name" type="text" class="bdm_user_login_trans_id" value="<?php echo $_POST['bdm_default_att_award_name'] ?>"/><br/>
					<em><?php _e('If you leave this blank a default value of 10 will be used', $this->localizationDomain); ?></em>
				    </td> 
				</tr>


				<tr valign="top"> 
				    <th width="33%" scope="row">
					<input class="thickbox button" id="bd_defaults_form_button" alt="#TB_inline?height=75&width=300&inlineId=bdwaitwindow&modal=false" type="button" name="Generate Default Settings" value="<?php _e('Generate Default Settings', $this->localizationDomain) ?>" />
					<input type="hidden" name="WP_Big_Door_GenLvl" value="1">
				    </th>
				    <td>
				    <p><?php _e('NOTE: Generating default settings requires connectivity to the BigDoor API server. The overall process could take up to a minute or longer. Please be patient and wait for the processing to complete!', $this->localizationDomain) ?></p>
				    </td>
			    </table>
			</form>



			<div id="bdwaitwindow" style="display:none">
			<p><img src="<?php echo $wp_bigdoor_var->bd_plugin_url ?>/images/wait_spinner.gif" > <?php _e('Generating defaults settings, please wait, this could take several minutes...', $this->localizationDomain) ?></p>
			</div>


		    <?php }

		?>
		</div>
		<?php
	    }


	    // Helper function: check for a valid URL
	    function isValidURL($url) {
		return preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $url);
	    }
            

            /**
             *  Define the WP BigDoor API settings page ==================== API SETTINGS =================
             */
            function admin_api_options() { 
		global $wp_bigdoor_var;

		if (!$wp_bigdoor_var->bd_options_array) {
		    $wp_bigdoor_var->settings();
		    $wp_bigdoor_var->getOptions();
		}

                if($_POST['WP_Big_Door_save']){
                    if (! wp_verify_nonce($_POST['_wpnonce'], 'WP_Big_Door-update-options') ) die('Whoops! There was a problem with the data you posted. Please go back and try again.'); 
                    
                    $wp_bigdoor_var->bd_options_array['bdm_user_login_trans_id'] = $_POST['bdm_user_login_trans_id'];

                    $wp_bigdoor_var->bd_options_array['bdm_user_login_currency_id'] = $_POST['bdm_user_login_currency_id'];

                    $wp_bigdoor_var->bd_options_array['bdm_public_api_key'] = $_POST['bdm_public_api_key'];

                    $wp_bigdoor_var->bd_options_array['bdm_leaderboard_display_all'] = ($_POST['bdm_leaderboard_display_all']=='on')?true:false;

		    if (!preg_match('/^\*/',$_POST['bdm_private_api_key'])) {
			    $wp_bigdoor_var->bd_options_array['bdm_private_api_key'] = $_POST['bdm_private_api_key'];
		    }

		    if ($this->isValidURL($_POST['bdm_api_domain']) || $_POST['bdm_api_domain'] == '') {
			    $wp_bigdoor_var->bd_options_array['bdm_api_domain'] = $_POST['bdm_api_domain'];
		    }

                    $wp_bigdoor_var->saveAdminOptions();
                    
                    echo '<div class="updated"><p>Success! Your changes were sucessfully saved!</p></div>';
                }

		$msg = '';

		if($_POST['WP_Big_Door_GenCur']) {
                    if (! wp_verify_nonce($_POST['_wpnonce'], 'WP_Big_Door-update-options') ) die('Whoops! There was a problem. Try refreshing the page first.'); 
		    require_once('wp-bigdoor-admin.php');
		    $res = $this->bd_generate_currencies();
		    if (!$res) 
			$msg = $res; 
		    else 
			$msg = 'Currencies created!';
		}


                ?>                                   
                <div class="wrap">
                <h2>BigDoor: API Settings</h2>
		<?php if ('' != $msg) { ?>
		<div class="updated fade below-h2" id="message"><p><?php echo $msg ?></p></div>
		<?php } ?>
                <form method="post" id="WP_Big_Door_options">
                <?php wp_nonce_field('WP_Big_Door-update-options'); ?>
                    <table width="100%" cellspacing="2" cellpadding="5" class="form-table"> 
                        <tr valign="top"> 
                            <th width="33%" scope="row"><?php _e('BigDoor public API Key:', $this->localizationDomain); ?></th> 
                            <td>
                                <input name="bdm_public_api_key" type="text" id="bdm_public_api_key" size="45" value="<?php echo $wp_bigdoor_var->bd_options_array['bdm_public_api_key'] ;?>"/>
                            </td> 
                        </tr>
                        <tr valign="top">
                            <th width="33%" scope="row"><?php _e('BigDoor private API Key:', $this->localizationDomain); ?></th> 
                            <td>
                                <input name="bdm_private_api_key" type="text" id="bdm_private_api_key" size="45" value="<?php if ($wp_bigdoor_var->bd_options_array['bdm_private_api_key']) {echo '******SECRET KEY HIDDEN******';}?>"/>
                            </td> 
                        </tr>
                        <tr valign="top"> 
                            <th>
                            </th>
                                <td>
                                <a href="http://publisher.bigdoor.com/signup?affid=wpp" target="_blank"><?php _e('Create Account at BigDoor ', $this->localizationDomain); ?></a>
                                </td>
                            </td>
                        </tr>
                        <tr>
                            <th colspan=2><input class="button" type="submit" name="WP_Big_Door_save" value="<?php _e('Save Settings', $this->localizationDomain); ?>" /></th>
                        </tr>

			<?php 
			    if ( 
			    ($wp_bigdoor_var->bd_options_array['bdm_public_api_key'] && $wp_bigdoor_var->bd_options_array['bdm_private_api_key']) 
			    && (!$wp_bigdoor_var->currencies || !$wp_bigdoor_var->levels || !$wp_bigdoor_var->comment_trans_id || !$wp_bigdoor_var->checkin_trans_id ) 
			    ) 
			    { ?>  

                         <tr valign="top"> 
                            <th>
				<strong>DEFAULT SETTINGS</strong>
                            </th>
                                <td>
					<p><strong><?php _e('Before you can configure other options, including levels, you must generate the default settings', $this->localizationDomain);?></strong></p>
        	                        <a href="<?php bloginfo('siteurl')?>/wp-admin/admin.php?page=bigdoor_default_settings"><?php _e('Generate Default Settings', $this->localizationDomain); ?></a>
                                </td>
                            </td>
                        </tr>

			<?php } ?>

                    </table>
                </form>
                <?php
            }

            
	    function inject_admin_scripts_styles() { 
		global $wp_bigdoor_var;  
		?>
		    <style>
		    .bd_level_item { 
			background-color:#1D507D; 
			-moz-border-radius: 7px; 
			min-height: 25px;
			font-size: 14px;
			font-weight:bold;
			width: 75%; 
			margin: 0 0 10px 0;
			color: white; 
			padding: 5px 0;
		    }
		    .bd_level_item a { 
			color:#fff;
			font-size: 12px;
		    }

		    .bd_headers { 
			font-size: 16px !important;
			font-weight: bold !important; 
			color: #000 !important;
		    }
		    .bd_edit_table { 
			margin-top: 15px;
			margin-bottom: 5px;
			clear:both;
			margin-left: 20px;
		    }
		    .bd_edit_table td { 
			padding: 0px !important;
		    }
		    .level_edit_link { 
			background-image:url("<?php echo $wp_bigdoor_var->bd_plugin_url ?>images/edit.png");
			background-position:left center;
			background-repeat:no-repeat;
			margin:0 3px;
			padding:0 8px;
			width: 16px;
		    }
		    .level_del_link { 
			background-image:url("<?php echo $wp_bigdoor_var->bd_plugin_url ?>images/remove.png");
			background-position:left center;
			background-repeat:no-repeat;
			margin:0 3px;
			padding:0 8px;
			width: 16px;
		    }
		    #bd_update_levels_id {
			width: 50%;
			height: 70px;
		    }
		    .bd_progress { 
			background-color: #afa;
			width: 5px;
			height: 10px;
			display: inline;
		    }
		    #bd_progress_wrap { 
			border: 1px solid #aaa;
			padding: 0px;
			height: 18px;
			width: 450px;
		    }
		    .level_badge_preview { 
			    width: 100px !important;
			    height: 100px !important;
		    }
		    </style>

		    <script>
			function bd_do_submit(form) { 
				jQuery(form).submit();
			}
			// display the form fields when someone clicks to edit a level
			function bd_level_edit(d, itm) { 
				if ( jQuery('#'+d+'_edit_level_'+itm).is(':visible')) { 
				    jQuery('#'+d+'_edit_level_'+itm).hide(); 
				} else { 
				    jQuery('#'+d+'_edit_level_'+itm).show(); 
				}
			}
			// confirms before deleting a level
			function bd_level_del(d, itm) { 
			    var answer = confirm("<?php _e('Are you sure that you want to delete this level?', $this->localizationDomain)?>");
			    if (answer){
				jQuery('#'+d+'bd_level_item_'+itm).remove();
			    }
			}
			// clones a new level div and inserts it into the appropriate column of levels
			function bd_add_level(t, n) { 

				if ('chk' == t) 
				    d = 'bd_chk';
				else if ('com' == t) 
				    d = 'bd_com';
				else if ('xp' == t)
				    d = 'bd_xp';

				cnt = jQuery('#'+d+'_items > div').size() + 1;

				jQuery('#bd_edit_level_tmp').attr('id', d+'_edit_level_'+cnt);

				cln = jQuery('#bd_level_item_clone').clone(false);

				jQuery(cln).find('.level_edit_link').attr('onclick','javascript:bd_level_edit("'+d+'",'+cnt+')');
				jQuery(cln).find('.level_del_link').attr('onclick','javascript:bd_level_del("'+d+'",'+cnt+')');

				jQuery(cln).find('.bdtitle').attr('name', d+'[lvl_title]['+n+'][]');
				jQuery(cln).find('.bdlvl').attr('name', d+'[level_val]['+n+'][]');
				jQuery(cln).find('.bdurl').attr('name',d+'[badge_url]['+n+'][]');

				jQuery(cln).addClass('bd_level_item');
				jQuery(cln).attr('id', d+'bd_level_item_'+cnt);
				jQuery(cln).css('display', 'block');
				jQuery(cln).find('#'+d+'_edit_level_'+cnt ).css('display', 'block');

				jQuery('#'+d+'_items').append(cln);

				jQuery('#'+d+'_edit_level_'+cnt).attr('id', 'bd_edit_level_tmp');

				return false;

			}
			// removes the update div
			function remove_bd_update_div() {
				jQuery('#bd_update_levels_id').remove();
			}
			function remove_default_install_spinner() {
				jQuery('#bd_defaults_spinner').remove();
			}    
			jQuery(document).ready( function() { 
				jQuery('#bd_level_form_button').click( function() { 
					jQuery('#bd_level_form').submit();
				});
				jQuery('#bd_defaults_form_button').click( function() { 
					jQuery('#bd_defaults_form').submit();
				});
			});
		    </script>

		<?php
	    }
    

}
global $bd_admin_class;
$bd_admin_class = new BD_Admin_Class();

?>
