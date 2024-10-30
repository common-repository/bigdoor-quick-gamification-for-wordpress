<?php
/**
*  BigDoor Media for WordPress - Widget Template
*  
*  @author Mark Edwards <mark@simplercomputing.net>
*  @version 0.1.0
*/


/**
* @package WP-BigDoor
* @subpackage BigDoor-Widget-HTML
* 
* 
*   Widget HTML code
*
*    This is used for the regular WordPress sidebar widget and for the floating widget if 
*    the admin chooses to have it float in a corner of the screen.
*
*/

global $wpdb;

?>
	    <div id="bd_widget_wrap">

		<noscript>
		    <div id="bd_no_script_msg"><?php _e('* You must enable Javascript to use this widget! *',$this->localizationDomain) ?></div>
		</noscript>

		<table width="100%" id="bd_widget_top">
		    <tr>
			<td width="70%" id="bd_welcome"><?php echo $welcome_msg.' '.$current_user->user_nicename; ?> </td>
			<td width="25%" class="bd_td_checkin_button">
			    <span id="bd_signout_link"><?php wp_loginout($_SERVER['REQUEST_URI']) ?></span>
			</td>
		    </tr>
		</table>

		<div id="bd_widget_inner">

		    <table id="bd_widget_main_points">
			<tr>
			    <?php if (!$current_user || !is_array($stats) ) { // not logged in or no user stats for logged in user ?>

				<td valign="top" width="30%">
				    <?php echo '<img align="left" hspace="1" vspace="1" src="'.$this->bd_plugin_url.'/images/badges/XP/newbie.100.png" class="highest_badge_img" align="absmiddle" />'; ?>
				</td>
				<td valign="top">
				    <span class="bd_label"><?php _e('Comment on a post or Check-in to unlock your Newbie badge!',$this->localizationDomain) ?></span><br/>
				</td>

			    <?php } else { // logged and we have some stats?>

				<td valign="top" width="15%">
				    <?php if ('' != $highest_lvl['badge_url']) echo '<img align="left" hspace="1" vspace="1" src="'.$highest_lvl['badge_url'].'" class="highest_badge_img" align="absmiddle" />'; ?>
				</td>
				<td valign="top">
				    <span class="bd_label"><?php _e('Level',$this->localizationDomain) ?></span>
				    <span class="bd_level_label"><?php echo $highest_lvl['title']?></span><br/>

				    <span class="bd_label"><?php _e('Total points',$this->localizationDomain) ?></span>
				    <span id="bd_xp_points" class="bd_level_label"><?php echo $stats['xp']?></span>
				    <span class="bd_label"><?php echo $this->default_xp_name?></span>
				</td>

			    <?php } ?>

			</td>

		    <table width="100%" id="bd_checkin_table">

			<?php
			// Is there any new message to display to the user? This happpens when the acheive a new level or award.
			$msg = get_usermeta($comment['user_id'], 'bd_new_level_award', ''); 
			if ('' != $msg) { 
			    delete_usermeta($comment['user_id'], 'bd_new_level_award');
			?>

			    <tr>
				<td width="100%" id="bd_new_level_award_msg">
				    <?php echo $msg; ?>
				</td>
			    </tr>

			<?php } else if ( !$this->user_checked_in_today() ) { ?>

			    <tr id="bd_checkin_area">
				<td id="bd_checkin_msg" valign="top">
				    <?php _e("Ready to check in?",$this->localizationDomain);?>
				</td>
				<td class="bd_td_checkin_button_img" valign="top"> 
				    <div id="bd_checkin_btn">
					<a href="javascript:bd_do_action('checkin','<?php echo base64_encode($current_user->user_login);?>')">Check In</a>
				    </div>

				</td>
			    </tr>

			<?php } else { ?>

			    <tr>
				<td width="100%" id="bd_already_checked_in_msg">
				    <img id="bd_checked_in_img" src="<?php echo $this->bd_plugin_url ?>images/alreadyCheckedIn.png" align="absmiddle"/>
				    <?php _e("You've checked in for today!",$this->localizationDomain);?>
				    <br/>
				</td>
			    </tr>

			<?php } ?>

		    </table>
		</div>
		<div id="bd_awards_leaderboard_links">
		    <?php if ($current_user && is_array($stats) ) { ?>
			<a href="javascript:bd_show_board('award')"><?php _e('My awards',$this->localizationDomain);?></a>
		    | 
		    <?php } ?>
		    <a href="javascript:bd_show_board('leader')"><?php _e('Leaderboard',$this->localizationDomain) ?></a>
		</div>

		<div id="bd_notice_wrap">
		</div>

		<div id="bd_award_wrap">
		    <?php
			if (is_array($stats)) 
			foreach($stats['levels'] as $badge) { 
			    echo '<img class="badge_img" src="'.$badge['badge_url'].'" alt=" '.$badge['title'].' " title=" '.$badge['title'].' "/> ';
			}
		    ?>
		</div>
		<div id="bd_leader_wrap">
			<table class="leaderboard_table">
			<th>Rank</th>
			<th>Name</th>
			<th><?php echo $this->default_xp_name ?></th>
			<?php
			$i = 1;
			if (is_array($leaders))
			foreach($leaders as $rank => $leader) {

			    $user = wp_cache_get( $leader['name'], 'users_by_login' );

			    if (!$user && '' != $leader['name']) { 
				$user = new WP_User( $leader['name'] ); // backward compatible
			    }


			    if ( !$user ) 
				$link = '';
			    else {
				$link = $user->data->user_url;
				wp_cache_set( $leader['name'], $user, 'users_by_login');
			    }

			    $is_admin = false; 

			    if ($user) { 
				if ( !empty( $user->roles ) && is_array( $user->roles ) ) {
				    foreach ( $user->roles as $role )
					if ('administrator' == $role) $is_admin = true;
				}
			    }
			    
			    if (!$is_admin) { 

				if ($i % 2) {
				    $alt_class = 'leaderboard_alt';
				} else {
				    $alt_class = '';
				}

				$i++;

				echo '<tr class="'.$alt_class.'">
				    <td width="25%" class="leaderboard_rank">'.$rank.'</td>';
				if ('' != $link) 
				    echo '<td width="50%" class="leaderboard_name"><a href="'.$link.'" target="_blank">'.$leader['name'].'</a></td>';
				else 
				    echo '<td width="50%" class="leaderboard_name">'.$leader['name'].'</td>';

				echo '
				    <td width="25%" class="leaderboard_balance">'.round($leader['balance'],0).'</td>
				    </tr>';
			    }

			} else { 
			    echo '<tr><td colspan="3">'.__('Leaderboards are updated periodically. Please check again in a few minutes.',$this->localizationDomain).'</td></tr>';
                        }

			?>
			</table>
		</div>

	    </div>
