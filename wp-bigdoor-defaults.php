<?php
/**
*  BigDoor Media for WordPress - Default Settings
*  
*  @author Mark Edwards <mark@simplercomputing.net>
*  @version 0.1.0
*/


/**
* @package WP-BigDoor
* @subpackage BigDoor-Default-Settings
* 
* 
* This code provides an array of default currencies, level descriptions, and thresholds that are used during installation of default settings
*
*/

// --------------------------------------------------------
// This sets up the default XP level thresholds. 

// You can change these formulas if you want, but these 
// are recommended defaults, which based on the formulas
// creates threshold values as follows: 
// 	1, 125, 275, 605, 1331, 2928 
// These values makes is relatively easy for users to 
// make it to the Intermediate level, but more diffcult 
// to reach the levels of Expert, Master, and Maniac.

// get the values from the form that was posted in the WP admin panel: 
$comments_xp = $_POST['bdm_default_comment_action'];
$checkins_xp = $_POST['bdm_default_checkin_action'];

// start at 1 for newbies, then increment each level's threshold as follows: 
$newbie 	= 1;
$beginner 	= round( ( (3 * $comments_xp) + (5 * $checkins_xp) ), 0); // 125
$intermediate 	= round( ($beginner * 2.2), 0); // 275
$expert 	= round( ($intermediate * 2.2), 0); // 605
$master 	= round( ($expert * 2.2), 0); // 1331
$maniac 	= round( ($master * 2.2), 0); // 2928

// ---------------------------------------------------------


// These are the default settings for levels.
// DO NOT change the array key names!
// You can however change the values.

$bd_default_parms = 

    array(

	'attendance' => array(
		    'url' => '30DayPerfectAttendance.png'
	),

	'XP' => array(

	    array(	'title' => 'Newbie',
		    'desc' => 'Welcome to the fun!',
		    'url' => 'newbie.100.png',
		    'threshold' => 1
	    ),
	    array(	'title' => 'Beginner',
		    'desc' => 'You have now reached Beginner status',
		    'url' => 'beginner.100.png',
		    'threshold' => $beginner
	    ),
	    array(	'title' => 'Intermediate',
		    'desc' =>  'You have now reached Intermediate status',
		    'url' => 'intermediate.100.png',
		    'threshold' => $intermediate
	    ),
	    array(	'title' => 'Expert',
		    'desc' => ' You are now a site Expert',
		    'url' => 'expert.100.png',
		    'threshold' => $expert
	    ),
	    array(	'title' => 'Master', 
		    'desc' => 'You are now a site Master',
		    'url' => 'master.100.png',
		    'threshold' => $master
	    ),
	    array(	'title' => 'Maniac',
		    'desc' => 'You are now a site Maniac!',
		    'url' => 'maniac.100.png',
		    'threshold' => $maniac
	    ),
	),

	'Comments' => array(

	    array(	'title' => 'Level 1 Commenter',
		    'desc' => 'Thanks for the comment!',
		    'url' => 'lvl1.100.png',
		    'threshold' => 1
	    ),
	    array(	'title' => 'Level 2 Commenter',
		    'desc' => 'You have achieved Level 2 Commenter status',
		    'url' => 'lvl2.100.png',
		    'threshold' => 5
	    ),
	    array(	'title' => 'Level 3 Commenter',
		    'desc' => 'You have achieved Level 3 Commenter status',
		    'url' => 'lvl3.100.png',
		    'threshold' => 10
	    ),
	    array(	'title' => 'Level 4 Commenter',
		    'desc' => 'You have achieved Level 4 Commenter status',
		    'url' => 'lvl4.100.png',
		    'threshold' => 25
	    ),
	    array(	'title' => 'Level 5 Commenter',
		    'desc' => 'You have achieved Level 5 Commenter status',
		    'url' => 'lvl5.100.png',
		    'threshold' => 100
	    ),
	),

	'Checkins' => array(
	    array(	'title' => 'Just Checking In',
		    'desc' => 'Thanks for dropping by!',
		    'url' => 'lvl1.100.png',
		    'threshold' => 1
	    ),
	    array(	'title' => 'Comeback Kid',
		    'desc' => 'Thanks for checking in 10 times!',
		    'url' => 'lvl2.100.png',
		    'threshold' => 10
	    ),
	    array(	'title' => 'Frequent Guest',
		    'desc' => 'We love having you around - thanks for checking in 25 times!',
		    'url' => 'lvl3.100.png',
		    'threshold' => 25
	    ),
	    array(	'title' => 'Site Regular',
		    'desc' => 'Keep it up - thanks for checking in 50 times!',
		    'url' => 'lvl4.100.png',
		    'threshold' => 50
	    ),
	    array(	'title' => 'Site Lover',
		    'desc' => 'Thank you for checking in 100 times!',
		    'url' => 'lvl5.100.png',
		    'threshold' => 100
	    )
	)
    );
?>