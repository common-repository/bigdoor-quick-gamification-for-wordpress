<?php
/**
*  BigDoor Media for WordPress - API client
*  
*  @author Mark Edwards <mark@simplercomputing.net>
*  @author Brian Oldfield <brian.oldfield@gmail.com>
*  @version 0.2.0
*/


/**
* @package WP-BigDoor
* @subpackage BigDoor-API-Client
* @class BDM_WP_Client
* 
* 
* This class provides the overall connectivity layer that communicates with the Big Door API servers.
*
*/
       


if (!class_exists('BDM_WP_Client')) {
    class BDM_WP_Client {
	
	var $bdm_api_domain = '';
	var $bdm_request_endpoint = '';
	var $bdm_request_envelope;
	var $bdm_request_parameters;
	var $bdm_http_request;
	var $wp_bd_options;

	var $bdm_sig_exclude = array('format','sig');
	
	/**
	* Constructor - PHP4
	*/
	function BDM_WP_Client( $end_user_login, $wp_options_array, $request_type_key, $param_array=false, $env_array=false, $id=false ) {

	    $this->__construct( $end_user_login, $wp_options_array, $request_type_key, $param_array=false, $env_array=false, $id=false );

	}
	

	/**
	*  Class constructor (PHP 5)
	*/
	function __construct( $end_user_login, $wp_options_array, $request_type_key, $param_array=false, $env_array=false, $id=false, $resname=false, $resid=false ) {

	    $this->wp_bd_options = $wp_options_array;

	    $this->bdm_api_domain = 'http://api.bigdoor.com';
	    
	    if (!$env_array) {
		$this->bdm_request_envelope = array();
	    } else {
		$this->bdm_request_envelope = $env_array;
	    }
	    
	    if (!$param_array) {
		$this->bdm_request_parameters = array();
	    } else {
		$this->bdm_request_parameters = $param_array;
	    }
	    
	    if ($request_type_key) {
		$this->bdm_request_endpoint = $this->get_endpoint($end_user_login, $this->wp_bd_options, $request_type_key, $id, $resname, $resid);
	    } else {
		echo 'Request type error: You must specify a request type key!';
	    }

	}


	// Performs the actual connection request and returns the results
	function do_request() {
	    global $wp_bigdoor_var;

	    $parms = array('sslverify' => false, 'timeout' => 15, 'user-agent' => 'WP-BigDoor-Plugin/1.0 http://bigdoor.com');

	    $pars_env_array = $this->sign_request( $this->bdm_request_endpoint, $this->bdm_request_parameters, $this->bdm_request_envelope );

	    $this->bdm_request_parameters = $pars_env_array['ret_pars'];

	    $this->bdm_request_envelope = $pars_env_array['ret_env'];

	    $api_url = $this->bdm_api_domain . $this->bdm_request_endpoint['endpoint'];


	    foreach($this->bdm_request_parameters as $key=>$val) { 
		    $string[] = $key.'='.$val;
	    }


	    $req = implode('&', $string);

	    if ($this->bdm_request_endpoint['method'] == 'GET') { 

		    $res = wp_remote_get( $api_url . '?' . $req, $parms   );
		    if ( $res['response']['code'] >= '200' && $res['response']['code'] <= '299' )
			return $res['body'];
		    else 
			return $res['response']['message']; 

	    } else if ($this->bdm_request_endpoint['method'] == 'DELETE') { 

		    // Try CURL since WP_HTTP doesn't appear to natively support
		    // the DELETE method across transports equally. 

		    if (!function_exists('curl_init'))
			die(__('Your server must support CURL in order to use the WP Big Door plugin!',$wp_bigdoor_var->localizationDomain ) );

		    // If the server is using PHP 5.2 or newer use the built-in query builder function,
		    // otherwise use the function in Wordpress.
		    if ( ! version_compare(phpversion(), '5.1.2', '>=') )
			    $env = _http_build_query($this->bdm_request_envelope, null, '&');
		    else
			    $env = http_build_query($this->bdm_request_envelope, null, '&');


		    $ch = curl_init($api_url . '?' . $req);
		    curl_setopt($ch, CURLOPT_POSTFIELDS, $env);
		    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		    curl_setopt($ch, CURLOPT_HEADER, 0);
		    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
		    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
		    $res = curl_exec($ch);

		    // Check if an error occured
		    if(curl_errno($ch)) {
			return curl_error($ch);
		    } else { 
			return $res;
		    }


	    } else if ($this->bdm_request_endpoint['method'] == 'POST') {

		    $parms['body'] = $this->bdm_request_envelope;
		    $parms['headers'] = array('content_type' => 'application/x-www-form-urlencoded');

		    $res = wp_remote_post( 
			$api_url . '?' . $req,
			$parms
		    ) ;

		    if ( $res['response']['code'] >= '200' && $res['response']['code'] <= '299' ) 
			return $res['body'];
		    else
			return $res['response']['message']; 

	    } else if ($this->bdm_request_endpoint['method'] == 'PUT') { 

		    $parms['body'] = $this->bdm_request_envelope;
		    $parms['headers'] = array('content_type' => 'application/x-www-form-urlencoded');

		    $parms['method'] = 'PUT';

		    $res = wp_remote_request( 
			$api_url . '?' . $req,
			$parms
		    ) ;
		    if ( $res['response']['code'] >= '200' && $res['response']['code'] <= '299' ) 
			return $res['body'];
		    else
			return $res['response']['message']; 

	    }

	}


	
	// Generates a token for API requests
	function _get_token() {
	    return md5(uniqid());
	}
	

	// returns the time formatted with .00 appended
	function _get_time() {
	    return time() . '.00';
	}

		
	// generate an API signature
	function sign_request( $bdm_request_endpoint, $bdm_r_pars, $bdm_r_env ) {

	    // Insure we have a time stamp in the parameters 
	    if (!array_key_exists('time', $bdm_r_pars)) {
		$bdm_r_pars['time'] = $this->_get_time();
	    }

	    if (strcmp($bdm_request_endpoint['method'],'POST') == 0 || strcmp($bdm_request_endpoint['method'],'PUT') == 0) {
		    $bdm_r_env['time'] = $bdm_r_pars['time'];
		    $bdm_r_env['token'] = $this->_get_token();
	    }

	    // Add the signature to the parameters array. 
	    $bdm_r_pars['sig'] = $this->_do_sign_request($bdm_request_endpoint,$bdm_r_pars,$bdm_r_env);
	    
	    return array('ret_pars' => $bdm_r_pars, 'ret_env' => $bdm_r_env);
	}
	

	// Signs an API request
	function _do_sign_request( $bdm_request_endpoint, $bdm_r_pars, $bdm_r_env ) {
	    global $wp_bigdoor_var;

	    $sig_string = $bdm_request_endpoint['endpoint'];

	    $sig_string .= $this->_flatten_request_array($bdm_r_pars);

	    $sig_string .= $this->_flatten_request_array($bdm_r_env);
	    
	    if ($this->wp_bd_options['bdm_private_api_key']) {
		$bdm_secret_key = $this->wp_bd_options['bdm_private_api_key'];
	    } else { 
		_e('BDM_WP_Client request error: You need to specify your private key in the administration panel!', $wp_bigdoor_var->localizationDomain);
	    }

	    // To complete the signature, we need to tack the secret key on the end of the signature string.
	    $sig_string .= $bdm_secret_key;

	    
	    //  The hash method is only supported in PHP 5, so we use an external lib for PHP 4 if necessary. 
	    if ( !version_compare(phpversion(), '5.0', '>=' ) ) {
		    require_once( dirname(__FILE__).'/php4-sha256.php' );
	    }

	    return hash('sha256',$sig_string);

	}
	
	/**
	*  Prep input parameter and evelope values for signature creation
	*/
	function _flatten_request_array( $flat_array = array() ) {

	    if (count($flat_array)) {

		// Sort the keys prior to flattening.
		$sorted_keys = array();

		foreach ($flat_array as $flt_itm => $ign_val) {
		    $sorted_keys[] = $flt_itm;
		}

		sort($sorted_keys);
		
		// Setup an array to implode. 
		$imp_arr = array();

		foreach($sorted_keys as $key) {
		    // Make sure we're not getting things in the sig string that shouldn't be there.
		    if (!in_array( $key, $this->bdm_sig_exclude ) ) {
			$imp_arr[] = $key . $flat_array[$key];
		    }
		}

		$ret = implode('',$imp_arr);

	    } else {

		$ret = '';

	    }
	    
	    return $ret;
	}


	// Maps the given key name to the appropriate API endpoint 
	function get_endpoint( $end_user_login, $wp_bd_options, $key, $id=false, $resname=false, $resid=false ) {  

	    if ($wp_bd_options['bdm_public_api_key']) {

		$prefix = '/api/publisher/' . $wp_bd_options['bdm_public_api_key'];

		$bd_resource_endpoint = array(  

		    // User endpoints
		    'end_user' => array('endpoint' => $prefix . '/end_user/'.$end_user_login,
		    'method' =>   'GET'),

		    'end_user_check' =>	array('endpoint' => $prefix. '/end_user/'.$end_user_login ,
		    'method' =>   'PUT'),

		    'end_user_stats' =>	array('endpoint' => $prefix. '/end_user/'.$end_user_login ,
		    'method' =>   'GET'),

			// these next 2 are identical except for the key name - just to make it easier to read code elsewhere
		    'end_user_checkin' => array('endpoint' => $prefix. '/named_transaction_group/'.$id. '/execute/'.$end_user_login ,
		    'method' =>   'POST'),

		    'end_user_comment' => array('endpoint' => $prefix. '/named_transaction_group/'.$id. '/execute/'.$end_user_login ,
		    'method' =>   'POST'),

		    'end_user_currency_balance' => array('endpoint' => $prefix. '/end_user/'.$end_user_login. '/currency_balance/'.$resid,
		    'method' =>   'GET'),


		    // leaderboard endpoint
		    'leader_board' =>	array('endpoint' => $prefix. '/leaderboard/execute',
		    'method' =>   'GET'),


		    // currency endpoints
		    'currency_create' => array('endpoint' => $prefix. '/currency',
		    'method' =>   'POST'),

		    'currency_types' => array('endpoint' => $prefix. '/currency',
		    'method' =>   'GET'),


		    // level endpoints
		    'level_collection_get' => array('endpoint' => $prefix. '/named_level_collection',
		    'method' =>   'GET'),

		    'level_collection_create' => array('endpoint' => $prefix. '/named_level_collection',
		    'method' =>   'POST'),

		    'level_create' => array('endpoint' => $prefix. '/named_level_collection/'.$id.'/named_level',
		    'method' =>   'POST'),

		    'level_update' => array('endpoint' => $prefix. '/named_level/'.$id,
		    'method' =>   'PUT'),

		    'level_delete' => array('endpoint' => $prefix. '/named_level_collection/'.$id.'/named_level/'.$resid,
		    'method' =>   'DELETE'),


		    // url endpoints
		    'url_create' => array('endpoint' => $prefix. '/url',
		    'method' =>   'POST'),
		    
		    'url_assoc' => array('endpoint' => $prefix. '/url/'.$id.'/'.$resname.'/'.$resid,
		    'method' =>   'POST'),

		    'url_update' => array('endpoint' => $prefix. '/url/'.$id,
		    'method' =>   'PUT'),

		    'url_delete' => array('endpoint' => $prefix. '/url/'.$id,
		    'method' =>   'DELETE'),


		    // award endpoints
		    'award_collection_create' => array('endpoint' => $prefix. '/named_award_collection',
		    'method' =>   'POST'),

		    'award_collection_get' => array('endpoint' => $prefix. '/named_award_collection',
		    'method' =>   'GET'),

		    'award_create_and_assoc' => array('endpoint' => $prefix. '/named_award_collection/'.$id.'/named_award',
		    'method' =>   'POST'),

		    'award_update' => array('endpoint' => $prefix. '/named_award/'.$id,
		    'method' =>   'PUT'),

		    'award_grant' => array('endpoint' => $prefix . '/end_user/'.$end_user_login.'/award',
		    'method' =>   'POST'),



		    // transaction endpoints
		    'named_transaction_get' => array('endpoint' => $prefix. '/named_transaction',
		    'method' =>   'GET'),

		    'named_transaction_create' => array('endpoint' => $prefix. '/named_transaction',
		    'method' =>   'POST'),

		    'named_transaction_group_get' => array('endpoint' => $prefix. '/named_transaction_group',
		    'method' =>   'GET'),

		    'named_transaction_group_create' => array('endpoint' => $prefix. '/named_transaction_group',
		    'method' =>   'POST'),

		    'named_transaction_assoc' => array('endpoint' => $prefix. '/named_transaction_group/'.$id.'/named_transaction/'.$resid,
		    'method' =>   'POST'),

		    'named_transaction_group_execute'=> array('endpoint' => $prefix.'/named_transaction_group/'.$id. '/execute/'.$end_user_login,
		    'method' =>   'POST'),

	    );

	} else { 
	    echo _e('Missing necessary configuration. Please check your API Settings.', $this->localizationDomain); 
	}

	return $bd_resource_endpoint[$key];
    }


    } // ======== END Class ============
} // ---- end if class exists
   
?>
