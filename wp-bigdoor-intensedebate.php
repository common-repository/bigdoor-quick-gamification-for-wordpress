<?php
/**
*  BigDoor Media for WordPress - IntenseDebate integration
*  
*  @author Mark Edwards <mark@simplercomputing.net>
*  @version 0.1.0
*/


/**
* @package WP-BigDoor
* @subpackage BigDoor-IntenseDebate
* 
* 
* This code allows the BigDoor plugin to detect when someone posts a new comment if IntenseDebate is being used.
*
*/

// Is the IntenseDebate plugin active? If so, add our hook to the page headers - but add it last if possible.
if ( defined( 'ID_PLUGIN_VERSION') || function_exists('id_activate_hooks') ) {
	add_action('wp_head','bd_add_intensedebate_hook',99999);
}



// Insert JS hooks to catch new IntenseDebate comments.
// See the IntenseDebate Plugin API for more info about hooking into their code:
// http://support.intensedebate.com/plugin-resources/plugin-api/
function bd_add_intensedebate_hook() { 
	global $wp_bigdoor_var, $user_ID, $current_user;

	// The Javascript below runs when a comment is posted via IntenseDebate. 
	// Arguments passed by IntenseDebate into the Javascript function include:
	//	comment_id, comment_userid, comment_rating, comment_time, comment_threadparentid, 
	//	comment_parentid, comment_nonuser_name, comment_nonuser_url
	// BUT none of those are of any use to us. We need the local WordPress user ID.
	// So we use an Ajax call to our own PHP script to handle that. 


	// The user must be logged into WordPress in order to get points for commenting! 
	if (!$current_user) wp_get_current_user();

	if ( !$user_ID || !$current_user->user_login ) { 

	// if the user isn't logged into the site, suggest that they do so: 
	?>
		<script>
		jQuery(document).ready( function() { 
		  function bd_notify_to_login() { 
			alert("You don't get points for commenting unless you login to this site first!");
		  }
		  id_add_action('comment_post', bd_notify_to_login);
		});
		</script>

	<?php 
	// if the user IS logged in then add the Javascript hooks for IntenseDebate comments: 
	} else {
 
		$nonce= wp_create_nonce('wp-bigdoor-ajax');
	?>

		<script>
		jQuery(document).ready( function() { 
		  function bd_new_comment(args) {
			jQuery.ajax(
			    { url: "<?php echo $wp_bigdoor_var->bd_plugin_url ?>ajax/api.php", 
			    data: { u: '<?php echo base64_encode($current_user->user_login);?>', ui: <?php echo $user_ID ?>, t: 'comment', _ajax_nonce: '<?php echo $nonce ?>' },
			    dataType: "json",
			    success: function(data){
				    if (data == null) return;
				    if (1 == data.error) { // error 
					alert("<?php _e('Ooops! Something went wrong.\n\nPlease try again in a few moments.', $wp_bigdoor_var->localizationDomain) ?>");
				    } else { 
					if (data.msg != null) { 
					    jQuery('#bd_checkin_area').html('<td width="100%" id="bd_already_checked_in_msg">'+data.msg+'</td>');
					}
					jQuery('#bd_xp_points').html(data.xp);
				    }
				}
			});	
		  }
		  id_add_action('comment_post', bd_new_comment);
		});
		</script>
<?php
	}

}
?>
