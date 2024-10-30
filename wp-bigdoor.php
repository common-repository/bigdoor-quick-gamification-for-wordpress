<?php
/*
Plugin Name: BigDoor Quick Gamification for WordPress
Plugin URI: http://bigdoor.com
Version: 1.0.5
Description: Easily tie your BigDoor Media virtual economy directly into your WordPress site. REQUIRES WORDPRESS 2.9 OR NEWER. 
Author: Simpler Computing and Brian Oldfield | Contact Simpler Computing for custom BigDoor plugins!
Author URI: http://simplercomputing.net
Requires: 2.9+
License: GPL2
*/

require_once('wp-bigdoor-api-client.php');
require_once('wp-bigdoor-intensedebate.php');


if (!class_exists('WP_Big_Door')) {
    class WP_Big_Door {

	var $bd_plugin_url = '';
	var $bd_plugin_path = '';
	var $bd_options_array = array();
	var $localizationDomain = '';
	var $options_name = '';
	var $gen_options;
	var $levels;
	var $currencies;
	var $comment_trans_id;
	var $checkin_trans_id;
	var $default_xp_name;
	
	// PHP4 constructor
	function WP_Big_Door(){
	    $this->__construct();
	}

	// PHP5-style constructor 
	function __construct() {
	    global $wp_version;

	    // Initialize plugin URL and PATH vars 
	    $this->bd_plugin_url = WP_PLUGIN_URL . '/' . dirname(plugin_basename(__FILE__)).'/';
	    $this->bd_plugin_path = WP_PLUGIN_DIR . '/' . dirname(plugin_basename(__FILE__)).'/';

	    // Load class global var settings
	    $this->settings();
			    
	    // Language localization
	    $locale = get_locale();
	    $mo = dirname(__FILE__) . "/languages/" . $this->localizationDomain . "-".$locale.".mo";
	    load_textdomain($this->localizationDomain, $mo);

	    // make sure the site is running WP 2.9 or newer, if not then display a big red message
	    if ( !version_compare( $wp_version, "2.9", ">" ) ) {
		echo '<div style="width:100%; background-color: #cf0000; text-align:center; color:#fff; font-style: italic; font-weight: bold;font-size: 14px; padding: 4px 0">';
		_e('BigDoor plugin requires WordPress version 2.9 or newer. <a style="color:#fff !important" href="http://codex.wordpress.org/Upgrading_WordPress" target="_blank">Please upgrade!</a>', $this->localizationDomain);
		echo '</div>';
	    }
	    
	    // load plugin settings
	    $this->get_options();
	    
	    // Register admin pages and related scripts and CSS
	    add_action('admin_head', array(&$this,'queue_scripts_styles'));
	    add_action('admin_menu', array(&$this,'admin_menu_link'));

	    // Inject scripts when a page loads
	    add_action('init', array(&$this,'ensure_jquery'));
	    add_action('wp_head', array(&$this,'bd_site_scripts'));

	    // Widget registration
	    add_action('plugins_loaded', array(&$this,'register_widgets') );

	    // Header and footer hooks
	    add_action( 'wp_head', array(&$this,'bd_widget_css') ) ;
	    add_action( 'wp_footer' , array(&$this,'wp_bd_widget_injector') );

	    // Hook in when someone posts a comment so we can increment points via API call
	    add_action('wp_insert_comment', array(&$this,'bd_post_comment'), 2, 2);
	    
	}


	// Set the language localization variable, and options table entry name
	function settings() { 
	    $this->localizationDomain = "BigDoor";
	    $this->options_name = 'WP_Big_Door_plugin_options';
	}
	

	
	/**
	*   Load all the settings needed by the plugin
	*/
	function get_options() {

	    if (!$bd_options_array = get_option($this->options_name)) {
		$bd_options_array = array('default'=>'options');
		update_option($this->options_name, $bd_options_array);
	    }

	    $this->bd_options_array = $bd_options_array;

	    $this->gen_options = get_option('bd_gen_options',false);
	    $this->currencies = get_option('bd_currencies',false); 
	    $this->levels = get_option('bd_level_collection',false); 

	    $this->comment_trans_id = get_option('comment_trans_group','');
	    $this->checkin_trans_id = get_option('checkin_trans_group','');

	    if (is_array($this->comment_trans_id))
		$this->comment_trans_id = $this->comment_trans_id['id'];

	    if (is_array($this->checkin_trans_id))
		$this->checkin_trans_id = $this->checkin_trans_id['id'];

	    $this->default_xp_name = get_option('bd_default_xp_name','');

	}


	/**
	* Inject required scripts and styles into the admin panel pages
	*/
	function queue_scripts_styles() { 
	    global $bd_admin_class;
	    require_once($this->bd_plugin_path.'wp-bigdoor-admin.php');
	    $bd_admin_class->inject_admin_scripts_styles(); 
	}


	/**
	*  Add the admin menus
	*/
	function admin_menu_link() {
	    global $bd_admin_class;;

	    require_once($this->bd_plugin_path.'wp-bigdoor-admin.php');

	    add_menu_page( 'BigDoor', 'BigDoor', 10, 'bigdoor', array(&$bd_admin_class, 'admin_api_options') );
	    add_submenu_page('bigdoor', 'API Settings', 'API Settings', 10, 'bigdoor', array(&$bd_admin_class, 'admin_api_options'));

	    if ($this->currencies && $this->levels && $this->comment_trans_id && $this->checkin_trans_id) {
		add_submenu_page('bigdoor', 'Set Levels', 'Set Levels', 10, 'bigdoor_levels', array(&$bd_admin_class, 'admin_set_levels'));
		add_submenu_page('bigdoor', 'Options', 'Options', 10, 'bigdoor_options', array(&$bd_admin_class, 'admin_set_options'));
	    } else if ($this->bd_options_array['bdm_public_api_key'] && $this->bd_options_array['bdm_private_api_key']) { 
		add_submenu_page('bigdoor', 'Install Default Settings', 'Install Default Settings', 10, 'bigdoor_default_settings', array(&$bd_admin_class, 'admin_install_defaults'));
	    }

	    add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array(&$bd_admin_class, 'filter_plugin_actions'), 10, 2 );
	}


	/**
	*  Ensure that jQuery is loaded
	*/
	function ensure_jquery() { 
	    wp_enqueue_script('jquery');
	    if (is_admin()) { 
		wp_enqueue_script('thickbox');            
		wp_enqueue_style('thickbox'); 
	    }
	}


	/**
	*  Add JS to public WP pages
	*/
	function bd_site_scripts() { 
	    global $user_ID;
	    $nonce = wp_create_nonce( 'wp-bigdoor-ajax' );
	?>
	    <script>
			function bd_show_board(t) {
			    if ('award' == t) {
				jQuery('#bd_leader_wrap').hide("fast");
				jQuery('#bd_notice_wrap').hide("fast");
				jQuery('#bd_award_wrap').slideToggle("fast");
			    } 
			    if ('leader' == t) {
				jQuery('#bd_award_wrap').hide("fast");
				jQuery('#bd_notice_wrap').hide("fast");
				jQuery('#bd_leader_wrap').slideToggle("fast");
			    }
			}

		    <?php 
			if (!$user_ID) { // use a different bd_do_action function if someone isn't logged in.


			    // does this site require users to login before posting comments? 
			    if ( get_option('comment_registration') ) { 
			?>
				function bd_do_action() { 
					alert("<?php _e('You need to login before you can check-in or post a comment!', $this->localizationDomain); ?>");
				}
			<?php 

			    } else { 
			?>
				function bd_do_action() { 
					alert("<?php _e('You must sign in before you can check in, \n  and you must be logged in to earn points\n for posting comments to this site', $this->localizationDomain); ?>");
				}
			<?php 
			    }

		    ?>



		    <?php	
			} else { // bd_do_action function for users that are logged in
		    ?>

			function bd_do_action(t,u) { 
			    tmp_btn = jQuery('.bd_td_checkin_button_img').html;
			    jQuery('.bd_td_checkin_button_img').html('<img src="<?php echo $this->bd_plugin_url ?>images/spinner.gif" height="30"/>');
			    jQuery.ajax(
				    { url: "<?php echo $this->bd_plugin_url ?>/ajax/api.php", 
				    data: { u: u,  ui: <?php echo $user_ID ?>, t: t, _ajax_nonce: '<?php echo $nonce ?>' }, 
				    dataType: "json",
				    success: function(data){
					    if (data == null) return;
					    if (1 == data.error) { // error 
						alert("<?php _e('Ooops! Something went wrong.\n\nPlease try again in a few moments.', $this->localizationDomain) ?>");
					    } else { // is there a new level or award message to display? 
						if (data.msg != null) { 
						    jQuery('#bd_notice_wrap').html(data.msg);
						    jQuery('#bd_leader_wrap').hide("fast");
						    jQuery('#bd_award_wrap').hide("fast");
						    jQuery('#bd_notice_wrap').show();
						    jQuery('#bd_award_wrap').append('<img class="badge_img" src="'+data.url+'" alt=" '+data.title+' " title=" '+data.title+' "/> ');
						} 
						jQuery('#bd_checkin_area').html('<td width="100%" id="bd_already_checked_in_msg"><img id="bd_checked_in_img" src="<?php echo $this->bd_plugin_url ?>images/alreadyCheckedIn.png" align="absmiddle"/><?php _e("You checked in for today!",$this->localizationDomain);?><br/></td>');
						jQuery('#bd_xp_points').html(data.xp);
					    }
					}
				    });	
			}

			function get_user_timezone(u) { 
			    var current_date = new Date();
			    var gmt_offset = current_date.getTimezoneOffset() * 60; 
			    jQuery.get('<?php echo $this->bd_plugin_url ?>/ajax/api.php', { t: 'tz', u: u, tz: gmt_offset, _ajax_nonce: '<?php echo $nonce ?>' } );
			}

			jQuery(document).ready( function() { 
			    get_user_timezone('<?php global $user_ID; echo $user_ID;?>');
			});

		    <?php 
			}
		    ?>
	    </script>
	<?php
	}



	/**
	*  Save the admin and widget options to the database
	*/
	function saveAdminOptions() {
	    return update_option($this->options_name, $this->bd_options_array);
	}


	// add general widget css to the template
	function bd_widget_css() { 
	    echo '<link rel="stylesheet" href="'.$this->bd_plugin_url.'css/widget.css" type="text/css" media="screen" />'."\n";
	    if ( 0 !== $this->gen_options['bdm_static_placement'] && '' !== $this->gen_options['bdm_static_placement'] ) 
		echo '<link rel="stylesheet" href="'.$this->bd_plugin_url.'css/widget-static.css" type="text/css" media="screen" />'."\n";

	}

	// WordPress widget registrations
	function register_widgets() {
	    if ( function_exists('wp_register_sidebar_widget') ) {
		$widget_ops = array('classname' => 'bd_user_rank', 'description' => __("Displays the logged-in user's points and awards, and lets them checkin each day.", $this->localizationDomain) );
		wp_register_sidebar_widget('WP_Big_Door-bd_user_rank', 'BigDoor', array(&$this, 'display_bd_user_rank'),$widget_ops);
		wp_register_widget_control('WP_Big_Door-bd_user_rank', __('Widget Title', $this->localizationDomain), array($this, 'bd_user_rank_control'));
	    }
	}


	/**
	*  Widget settings that appear in the WordPress admin panel when configuring the widget 
	*/
	function bd_user_rank_control() {     
       
	    if ( $_POST["WP_Big_Door_bd_user_rank_submit"] ) {
		$this->bd_options_array['bd_user_rank-welcome_message'] = stripslashes($_POST["bd_user_rank-welcome_message"]);        
		$this->saveAdminOptions();
	    }                                                                  

	    $welcome_message = htmlspecialchars( $this->bd_options_array['bd_user_rank-welcome_message'], ENT_QUOTES );

	    if (strcmp($welcome_message,'')==0) {
		    $welcome_message = "Hi";
	    }

	?>
	    <p><label for="bd_user_rank-welcome_message"><?php _e('Greeting', $this->localizationDomain); ?> 
		<input style="width: 250px;" id="bd_user_rank-welcome_message" name="bd_user_rank-welcome_message" type="text" value="<?php echo $welcome_message; ?>" /><br/>
		<?php _e("This text is displayed before the user's name at the top of the widget. Keep it very short!", $this->localizationDomain); ?> 
		</label>
	    </p>

	    <input type="hidden" id="WP_Big_Door_bd_user_rank_submit" name="WP_Big_Door_bd_user_rank_submit" value="1" />
	<?php
	}


	// Function used by the widget to get the HTML
	function display_bd_user_rank($args) {

	    wp_get_current_user();

	    echo $before_widget;
	    echo $this->get_widget_html($args); 
	    echo $after_widget;
	}



	// get the default currency ID
	function get_default_currency_id() { 

	    //$default_xp_name = get_option('bd_default_xp_name','');
	    if ('' == $this->default_xp_name) return false;

	    if ( count($this->currencies[0]) < 3 ) return false;

	    for($i=0; $i<count($this->currencies[0]); $i++) { 
		if ( strtolower($this->default_xp_name) == strtolower($this->currencies[0][$i]['pub_title']) ) 
		    return $this->currencies[0][$i]['id'];
	    }

	    return false;
	}


	// Get the current leaderboard object
	function get_leaderboard() { 

	    if (!$currency = $this->get_default_currency_id()) 
		return false;

	    $c_board = & new BDM_WP_Client(
		    $user,
		    $this->bd_options_array,
		    'leader_board',
		    array('format'=>'json',
			'verbosity'=>'9',
			'filter_value' => $currency,
			'type' => 'currency'
		    )
		    );

	    $board = $c_board->do_request();

	    $board = json_decode($board,TRUE);

	    if (is_array($board)) {
		foreach($board[0]['results'] as $leader) 
		    $leaders[ $leader['rank'] ] = array( 'name' => $leader['end_user_login'], 'balance' => round($leader['curr_balance'],0) ); 
		return $leaders;
	    } else 
		return false;

	}


	// update the locally stored leaderboard option - we cache it
	function update_leaderboard_cache() { 
		$leaders = $this->get_leaderboard(); 
		if ($leaders) 
		    update_option('bd_leaderboard',$leaders);
	}


	// retrieve the user object from BigDoor to extract stats
	function get_user_stats($user) { 

	    $c_stats = & new BDM_WP_Client(
		    $user,
		    $this->bd_options_array,
		    'end_user_stats',
		    array('format'=>'json','verbosity'=>'9')
		    );

	    $stats = $c_stats->do_request();

	    $stats = json_decode($stats,TRUE);

	    $user_stats = array();

	    // get current XP points using whatever the admin named that currency
	    foreach($stats[0]['currency_balances'] as $c) { 
		    if ($c['pub_title'] == $this->default_xp_name) { 
			$user_stats['xp'] = round($c['current_balance'],0);
			break;
		    }
	    }
	    
	    // get the user's badges
	    foreach($stats[0]['level_summaries'] as $ls) {
		$user_stats['levels'][] = 
		    array('title' => $ls['pub_title'],
			  'badge_url' => $ls['urls'][0]['url'],
			  'threshold' => $ls['threshold'],
			  'id' => $ls['named_level_id']
			);
	    }

	    return $user_stats;
	}


	// find the user's highest XP-related badge and return the URL
	function get_users_highest_xp_badge($stats) { 
	    if (!$this->levels) return;

	    if (!is_array($stats)) return array();

	    // find the XP currency's levels
	    $xp_lvls = array();
	    foreach ($this->levels[0] as $lvl) {
		if ($lvl['pub_title'] == $this->default_xp_name.' levels') {
			$xp_lvls = $lvl['named_levels'];
			break;
		}
	    }

	    if ( count($xp_lvls) < 1) return; // something isn't right, so return;

	    // get the level IDs into an array
	    foreach($xp_lvls as $xpl) {
		$xp_ids[] = $xpl['id']; 
	    }

	    // compare to user's levels, find the highest
	    $highest = array();
	    if (is_array($stats)) 
	    foreach($stats['levels'] as $ulvl) { 

		    // is the $ulvl in the array of XP level ids? 
		   if ( in_array($ulvl['id'], $xp_ids) ) { 
			// is the threshold higher than the one we have stored now? 
			if ($ulvl['threshold'] > $highest['threshold'])
			    $highest = $ulvl;
		    }
	    }

	    return $highest;
	}


	// give the user the perfect attendance award
	function award_perfect_attendance() { 
	    global $current_user, $perfect_att_data;

	    if (!$current_user) wp_get_current_user();

	    if (!$current_user) return;

	    $parms = array(
			'format'=>'json', 
			'verbosity'=>'9', 
		    );

	    // get the cached award collection info
	    $award_collection = get_option('bd_award_collection');
	    $award_id = $award_collection[0][0]['named_awards'][0]['id'];

	    // this used by the ajax api.php script to alert the user when they receive the award: 
	    $perfect_att_data = array(
				    'title' => $award_collection[0][0]['named_awards'][0]['pub_title'], 
				    'url' => $award_collection[0][0]['named_awards'][0]['urls'][0]['url']
				);

	    $env = array('named_award_id' => $award_id);

	    $client = & new BDM_WP_Client(
		    $current_user->user_login, 
		    $this->bd_options_array, 
		    'award_grant',
		    $parms,
		    $env
	    );

	    $resp = $client->do_request(); 

	    if ('' != $resp) 
		$res = json_decode($resp, TRUE);
	    else
		$res = '';

	    return $res; 
	    
	}

	// Check if user has perfect Check In attendance over the past 30 days.
	// If they do then we need to give them the Perfect Attedance award
	function check_user_perfect_attendance() { 
	    global $user_ID;

	    // has the user already received the perfect attendance award? 
	    if (get_usermeta($user_ID, 'bd_perfect_attendance')) 
		    return false;

	    // get attendance record
	    $user_attendance = get_usermeta($user_ID, 'bd_user_attendance'); 

	    if (!is_array($user_attendance))
		    $user_attendance = array();

	    // add today's date to the attendance record
	    $user_attendance[] = date( 'm-d-Y', time() );
	    update_usermeta($user_ID, 'bd_user_attendance', $user_attendance); 

	    $check = 0;

	    // Check to see if every date for the past 30 days is in the user's attendance record.
	    // If the past 30 are in there then they've checked in every day for the past 30 days.
	    for($i = 0; $i <= 31; $i++) { 
		    if (0 == $i) 
			    $former_day = date('m-d-Y', time() ); // today's date
		    else if (1 == $i) 
			    $former_day = date('m-d-Y', strtotime("-".$i." day"));
		    else 
			    $former_day = date('m-d-Y', strtotime("-".$i." days"));

		    if (in_array($former_day, $user_attendance))
			    $check++;
	    }

	    // cleanup up the attendance record so that it only has entries for the past 31 days
	    $i = 0;
	    while ( count($user_attendance) > 31 ) { 
		    unset( $user_attendance[$i] );
		    $i++; 
	    }

	    // quickly renumber the array keys and update the record - we need keys with a zero offset.
	    $user_attendance = array_merge( array(), $user_attendance );
	    update_usermeta($user_ID, 'bd_user_attendance', $user_attendance);

	    // If there are 30 consecutive days then award perfect attendance!
	    if ($check >= 30) { 
		    // give user the perfect attendance award
		    $result = $this->award_perfect_attendance();
		    if (is_array($result)) { 
			// make a record that they've already received the award
			update_usermeta($user_ID, 'bd_perfect_attendance', 1);
			return true;
		    }
	    }

	    return false;
	}


	// user's can only checkin once per day. We store their timezone and day number, 
	// so figure out if it's a different day in the user's timezone. If so, they can checkin again
	function user_checked_in_today() { 
	    global $user_ID;
	    $last_checkin_day = get_usermeta($user_ID, 'bd_last_checkin_day');
	    $utz = get_usermeta($user_ID, 'bd_user_timezone');
	    $this_day = date('j', (time() - ($utz)) ); // get the current day number in the user's time zone
	    if ($this_day == $last_checkin_day) 
		return true;
	    else
		return false;
	}



	//  Helper function that generates the actual HTML for the widget
	function get_widget_html($args=false) { 

	    global $current_user, $user_ID;

	    if (!$current_user) wp_get_current_user();

	    $stats = get_usermeta($user_ID, 'bd_stats');

	    $highest_lvl = $this->get_users_highest_xp_badge($stats);

	    $leaders = get_option('bd_leaderboard','');

	    // The admin is using the theme widget, so we'll use the configured widget settings
	    if (is_array($args)) { 
		extract($args, EXTR_SKIP);
		echo $before_widget; 
		$welcome_msg = $this->bd_options_array['bd_user_rank-welcome_message'];
	    } else { 
		$welcome_msg = __('Hi',$this->localizationDomain);
	    } 

	    if (!$user_ID) $welcome_msg = '';

	    ob_start();

	    require_once( dirname(__FILE__) . '/wp-bigdoor-widget-template.php');

	    if (is_array($args))
		echo $after_widget;

	    $out = ob_get_contents();
	    ob_end_clean();

	    return $out; 
	}



	// Inject Widget into a corner of the screen if the admin set that option
	function wp_bd_widget_injector() { 

	    $pos = array('tl','tr','bl','br');

	    if ( !in_array($this->gen_options['bdm_static_placement'], $pos) ) return;

	    if ( 'tl' == $this->gen_options['bdm_static_placement'] )
		echo '<div id="bd_upperLeftCorner">';
	    else if ( 'tr' == $this->gen_options['bdm_static_placement'] )
		echo '<div id="bd_upperRightCorner">';
	    else if ( 'bl' == $this->gen_options['bdm_static_placement'] )
		echo '<div id="bd_lowerLeftCorner">';
	    else if ( 'br' == $this->gen_options['bdm_static_placement'] )
		echo '<div id="bd_lowerRightCorner">';

		echo $this->get_widget_html();
	    echo '</div>';
	}


	// Hook for when a user posts a comment
	// User must be logged in otherwise the function returns immediately
	function bd_post_comment($id='', $comment='') { 
	    global $user_ID;

	    if (!$user_ID) return; // must be logged in
	    if ('' == $comment->comment_author) return; // in case there's no author indicated (could happen due to some other plugin being fault)
	    if (!$this->comment_trans_id) return; // default must not be installed

	    // call the transaction
	    $client = & new BDM_WP_Client(
		    $comment->comment_author, 
		    $this->bd_options_array, 
		    'end_user_comment',
		    array(  'format'=>'json',
			    'verbosity'=>'9',
		    ),
		    array( 'verbosity' => '9' ), // gotta put it in the envelope for this call otherwise no object is returned
		    $this->comment_trans_id
	    );

	    $checkinresp = $client->do_request(); 

	    $checkinres = json_decode($checkinresp, TRUE);

	    // we get 0 if the transaction is complete; and we get an object back if they have a new level or award
	    if (0 == intval($checkinres) || is_array($checkinres) ) {

		    // update user's stats cache - this keeps API calls to a bare minimum since
		    // the call only happens when an action takes place, such as Checkin or commenting

		    $stats = $this->get_user_stats($comment->comment_author);
		    if (count($stats)>0) 
			    update_usermeta($comment->user_id, 'bd_stats', $stats);

		    // update leaderboard cache
		    $this->update_leaderboard_cache();

		    // look for new levels or awards, if found extract the pub_titles and descriptions to notify the user
		    if (is_array($checkinres)) { 
			    $msg = '';
			    // concatenate the messages and pass it back to the jQuery calling function via "echo"
			    foreach($checkinres[0]['end_user']['level_summaries'] as $item) { 
				    if (true == $item['leveled_up']) 
				    $msg .=         __("You've just unlocked ",$this->localizationDomain) .
						    $item['pub_title'] .
						    ' - ' . 
						    $item['pub_description'] .
						    '<br/>';
			    }
			    // we have to store this in the user's meta data, then display it when a page loads, 
			    // and after displaying it we erase the meta data so it doesn't repeated appear
			    update_usermeta($comment->user_id, 'bd_new_level_award', $msg);
			    return;
		    }
		    // no award or new level
		    return; 
	    }
	    return;
	}


    }
} // ------------ End Class ----------------



global $wp_bigdoor_var;

if (class_exists('WP_Big_Door')) {
    $wp_bigdoor_var = new WP_Big_Door();
}




/**
*  PHP TEMPLATE TAG function to generate HTML for widget for those that need a way to manually insert it into a theme.
*/
function insert_bigdoor_widget() { 
    global $wp_bigdoor_var;
    echo $wp_bigdoor_var->get_widget_html();
}

?>
