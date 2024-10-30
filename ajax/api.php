<?php
/**
*  BigDoor Media for WordPress - Ajax Functionality 
*  
*  @author Mark Edwards <mark@simplercomputing.net>
*  @version 0.1.0
*/

/**
* @package WP-BigDoor
* @subpackage BigDoor-Ajax-Interface
* 
* Provides the LOCAL endpoint called by jQuery functions for check-in, comments, and storing the user's timezone
*/


// grab this value first BEFORE WordPress loads because WordPress modifies the timezone seen by PHP:
global $bd_server_time_zone_offset;
$bd_server_time_zone_offset = date('Z'); 


require_once('../../../../wp-config.php');

if (!check_ajax_referer('wp-bigdoor-ajax')) die;

if ('' == $_GET['t']) die;
if ('' == $_GET['u']) die;


switch ($_GET['t']) {
	case 'checkin': 
		bd_do_action('checkin');
		break;
	case 'comment': 
		bd_do_action('comment');
		break;
	case 'tz':
		bd_set_user_tz();
		break;
}
exit;



// Worker function ------------------------------
function bd_do_action($type) { 
	global $wp_bigdoor_var;

	$u = base64_decode($_GET['u']);
	$ui = $_GET['ui'];

	if ('checkin' == $type)
		$type = 'end_user_checkin';

	if ('comment' == $type)
		$type = 'end_user_comment';

        $client = & new BDM_WP_Client(
		$u, 
		$wp_bigdoor_var->bd_options_array, 
		$type,
		array(	'format'=>'json',
			'verbosity'=>'9',
		),
		array( 'verbosity' => '9' ), // gotta put it in the envelope for this call otherwise no object is returned
		$wp_bigdoor_var->checkin_trans_id
	);
	

	$checkinresp = $client->do_request(); 

	$checkinres = json_decode($checkinresp, TRUE);

	// we get 0 if the transaction is complete; and we get an object back if they have a new level or award
	if (0 == intval($checkinres) || is_array($checkinres) ) {

		// user is checking in, so store the date - only one checkin per day!
		if ($type == 'end_user_checkin') { 
			$tz = get_usermeta($ui, 'bd_user_timezone');
			$t = ( time() - ($tz) ) ; // get the timestamp in the user's timezone
			update_usermeta($ui, 'bd_last_checkin_day', date('j',$t) ); // store the current day number of the user's timezone
		}

		// update user's stats cache - this keeps API calls to a bare minimum since
		// the call only happens when an action takes place, such as Checkin or commenting

		$stats = array();

		$stats = $wp_bigdoor_var->get_user_stats($u);
		if (count($stats)>0) 
			update_usermeta($ui, 'bd_stats', $stats);

		// update leaderboard cache
		$wp_bigdoor_var->update_leaderboard_cache();

		// look for new levels or awards, if found extract the pub_titles and img urls to notify the user
		if (is_array($checkinres)) { 

			$msg = '';
			$url = '';
			$title = '';

			// find the first new badge, if any exist
			foreach($checkinres[0]['end_user']['level_summaries'] as $item) { 
				if (true == $item['leveled_up']) {
					$msg = 	' <img src="' . $item['urls'][0]['url'] .
						'" align="right" vspace="1" hspace="1" class="highest_badge_img bd_award_badge"/>' .
						'<div id="new_award_msg">' .
						__("Congratulations!",$wp_bigdoor_var->localizationDomain) .
						'<br/>' .
						__("You've just unlocked the ",$wp_bigdoor_var->localizationDomain) .
						$item['pub_title'] .
						__(' badge!',$wp_bigdoor_var->localizationDomain) . 
						'</div>';
					$url = $item['urls'][0]['url'];
					$title = $item['pub_title'];
					break;
				}
			}

			// check if the user has perfect attendance (e.g.  whether they've checked in for 30 days in a row)
			if ( $wp_bigdoor_var->check_user_perfect_attendance() ) { 
				global $perfect_att_data;
				$msg = 	' <img src="' . $perfect_att_data['url'] .
					'" align="right" vspace="1" hspace="1" class="highest_badge_img bd_award_badge"/>' .
					'<div id="new_award_msg">' .
					__("Congratulations!",$wp_bigdoor_var->localizationDomain) .
					'<br/>' .
					__("You now have the ",$wp_bigdoor_var->localizationDomain) .
					$perfect_att_data['title'] . 
					'!</div>';
				$url = $perfect_att_data['url'];
				$title = $perfect_att_data['title'];
			} 

			// If there is a new level or award message then pass back the message plus the URL and title.
			// jQuery will use that to insert the content into the message area, plus the awards display area.
			if ('' != $msg) {
				$stats['msg'] = $msg;
				$stats['url'] = $url;
				$stats['title'] = $title;
			}

		}

		$ret = json_encode( $stats );

		if ('' != $ret) 
			echo $ret;
		else 
			echo json_encode( array( 'error' => '0' ) ) ; // ERROR occurred
		exit;
	}
	
	echo json_encode( array( 'error' => '1' ) ) ; // ERROR occurred
	exit;
}

// update user's stored timezone offset ------------------------
function bd_set_user_tz() { 
	global $bd_server_time_zone_offset;

	// if there's no timezone offset available from the user's browser then use the server's timezone offset
	if ('' == $_GET['tz']) { 

		// trim off any + or - sign, we don't need it.
		if (substr($bd_server_time_zone_offset,0,1) == '-') 
			$bd_server_time_zone_offset = substr($bd_server_time_zone_offset,1);

		$tz = $bd_server_time_zone_offset;

	} else { 

		$tz = $_GET['tz'];

	}

	$uid = $_GET['u'];
	update_usermeta($uid, 'bd_user_timezone', $tz);
	echo json_encode( array( 'error' => '0' ) ) ; // ERROR occurred
	exit;
}

?>
