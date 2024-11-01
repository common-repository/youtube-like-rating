<?php
/*
Plugin Name: Youtube-like-rating
Description: Youtube Like Thumbs Up Or Thumbs Down System For Voting On Posts And Comments
Version: 1.0
Author: Ruchita Ladha
Author Email: ruchitaladha28@gmail.com
*/

//******************************************************************************************************
// YoutubeLikeRating CLASS
//******************************************************************************************************		
class YoutubeLikeRating {

		
	function __construct()
	{
		add_action( 'init', array( &$this, 'init_plugin' ));
		add_action( 'wp_ajax_process_vote', array( &$this, 'process_vote' ));
		add_action( 'wp_ajax_nopriv_process_vote', array( &$this, 'process_vote' ));
		add_action( 'admin_menu', 'youtubeLikeRating_admin_menu' );
	}
	
	//===============================================================================
	// Create the database tables needed for the plugin to run. Called on activation
	// @return void 
	// ===============================================================================
	
	public function setup_plugin() {
		global $wpdb;
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php');

		if ( !empty($wpdb->charset) )
			$charset_collate = "DEFAULT CHARACTER SET ".$wpdb->charset;

		$sql = "CREATE TABLE ".$wpdb->base_prefix."post_vote_totals (
			`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`post_id` bigint(20) NOT NULL,
			`vote_count_up` bigint(20) NOT NULL DEFAULT '0',
			`vote_count_down` bigint(20) NOT NULL DEFAULT '0',
			`date_time` datetime NOT NULL,
			KEY `post_id` (`post_id`),
			CONSTRAINT UNIQUE (`post_id`)
		) ".$charset_collate.";";
		dbDelta($sql);

		$sql = "CREATE TABLE ".$wpdb->base_prefix."post_votes (
			`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`post_id` bigint(20) unsigned NOT NULL,
			`voter_id` varchar(32) NOT NULL DEFAULT '',
			`vote_value` int(11) NOT NULL DEFAULT '0',
			`date_time` datetime NOT NULL,
			KEY `post_id` (`post_id`),
			KEY `voter_id` (`voter_id`),
			KEY `post_voter` (`post_id`, `voter_id`),
			CONSTRAINT UNIQUE (`post_id`, `voter_id`)
		) ".$charset_collate.";";
		dbDelta($sql);

		$sql = "CREATE TABLE ".$wpdb->base_prefix."comment_vote_totals (
			`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`comment_id` bigint(20) unsigned NOT NULL,
			`post_id` bigint(20) unsigned NOT NULL,
			`vote_count_up` bigint(20) NOT NULL DEFAULT '0',
			`vote_count_down` bigint(20) NOT NULL DEFAULT '0',
			`date_time` datetime NOT NULL,
			KEY `post_id` (`post_id`),
			KEY `comment_id` (`comment_id`),
			CONSTRAINT UNIQUE (`comment_id`)
		) ".$charset_collate.";";
		dbDelta($sql);

		$sql = "CREATE TABLE ".$wpdb->base_prefix."comment_votes (
			`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`comment_id` bigint(20) unsigned NOT NULL,
			`post_id` bigint(20) unsigned NOT NULL,
			`voter_id` varchar(32) NOT NULL DEFAULT '',
			`vote_value` int(11) NOT NULL DEFAULT '0',
			`date_time` datetime NOT NULL,
			KEY `comment_id` (`comment_id`),
			KEY `post_id` (`post_id`),
			KEY `voter_id` (`voter_id`),
			KEY `post_voter` (`post_id`, `voter_id`),
			KEY `comment_voter` (`comment_id`, `voter_id`),
			CONSTRAINT UNIQUE (`comment_id`, `voter_id`)
		) ".$charset_collate.";";
		dbDelta ($sql);

		
	}
	
	public function init_plugin()
	{		
		wp_enqueue_script('YoutubeLikeRating-ajax-request', plugins_url( '/js/YoutubeLikeRating.js', __FILE__),
		array( 'jquery' ), '1.0' );
		wp_localize_script( 'YoutubeLikeRating-ajax-request', 'ratingAjax', 
		array( 'ajaxurl' => admin_url( 'admin-ajax.php' )));
		wp_enqueue_script('jquery.tipTip', plugins_url('/js/jquery.tipTip.minified.js', __FILE__), array('jquery'), '1.3');
		wp_enqueue_style( 'YoutubeLikeRating', plugins_url( '/style/YoutubeLikeRating.css', __FILE__) );
		wp_enqueue_style('tipTip', plugins_url('/style/tipTip.css', __FILE__));
	}
	
	
	public function guest_allowed()
	{
		return get_option ("ylr_guest_allowed") == "allowed"? 1 : 0;
	}

	public function get_user_ip() {
			
		$ip = '';
		if (isset($_SERVER['HTTP_CLIENT_IP']) && $_SERVER['HTTP_CLIENT_IP']) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		}else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR']){
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		}else if(isset($_SERVER['HTTP_X_FORWARDED']) && $_SERVER['HTTP_X_FORWARDED']){
			$ip = $_SERVER['HTTP_X_FORWARDED'];
		}else if(isset($_SERVER['HTTP_FORWARDED_FOR']) && $_SERVER['HTTP_FORWARDED_FOR']){
			$ip = $_SERVER['HTTP_FORWARDED_FOR'];
		}else  if(isset($_SERVER['HTTP_FORWARDED']) && $_SERVER['HTTP_FORWARDED']){
			$ip = $_SERVER['HTTP_FORWARDED'];
		}else{
			$ip = $_SERVER['REMOTE_ADDR'];
		}
			return $ip;
	}
	
	public function get_user_id()
	{
		if (is_user_logged_in())
			return get_current_user_id();
		
		// Return Ip Address for guest user
		if ($this->guest_allowed())
			return $this->get_user_ip();
		
		return 0;
	}
	
	public function get_post_votes_total ( $post_id )
	{
		global $wpdb;
		if ( !$post_id )
			return false;

		$result_query = $wpdb->get_results($wpdb->prepare("SELECT vote_count_up, vote_count_down 
				FROM ".$wpdb->base_prefix."post_vote_totals 
				WHERE post_id = %d", $post_id));
		return ( count($result_query) == 1 ? array( "up" => $result_query[0]->vote_count_up,
		"down" => $result_query[0]->vote_count_down ) : array( "up" => 0, "down" => 0 ));
	}

	public function get_post_user_vote( $user_id, $post_id ) {
		global $wpdb;
		return $wpdb->get_var($wpdb->prepare("SELECT vote_value 
		FROM ".$wpdb->base_prefix."post_votes
		WHERE voter_id = %s AND post_id = %d", $user_id, $post_id));
	}
	
	public function add_vote_in_post($content){

	
		$post_id = get_the_ID();
		if ( !$post_id )
			return false;
		
		$vote_counts = $this->get_post_votes_total( $post_id );
		$existing_vote = $this->get_post_user_vote( $this->get_user_id(), $post_id );
		echo $content;
		echo '<div style="clear:both;padding-top:5px;"></div><div class="" id="post-'.$post_id.'" post-id="'.$post_id.'">';
		$this->load_post_votes( $vote_counts["up"], $vote_counts["down"], $existing_vote );
		echo  '</div><div style="clear:both"></div>';
	
	}

	public function load_post_votes( $thumb_up_count = 0, $thumb_down_count = 0,$existing_vote = 0 ) { 
		
		$thumb_up_status = '';
		$thumb_down_status = '';
		$votable = $this->get_user_id();
		
		if ( $existing_vote > 0 ){
			$thumb_up_status = '-on';
			$button_style = 'class="up-like-active"';
			$button_down_style = 'class="down-like"';
		}elseif ( $existing_vote < 0 ){
			$thumb_down_status = '-on';
			$button_down_style = 'class="down-like-active"';
			$button_style = 'class="up-like" ';
		}else{
			$button_style = 'class="up-like" ';
			$button_down_style = 'class="downn-like"';
		}		

		$thumb_up_image = plugins_url( '/images/thumb-up'.$thumb_up_status.'.png', __FILE__);
		$thumb_down_image = plugins_url( '/images/thumb-down'.$thumb_down_status.'.png', __FILE__);

		$vote_total_count = $thumb_up_count + $thumb_down_count;
		$vote_percent =floor( ($thumb_up_count*100)/($vote_total_count?$vote_total_count:1));

		if ($votable){
			echo '<div class="rating-button" title="I like this"><img class="thumb-up-button" 
			vote-direction="1"   src="'.$thumb_up_image.'"> <div '.$button_style.' >Like </div></div>
			<div class="rating-button" title="I dislike this ">
				<img class="thumb-down-button" vote-direction="-1" src="'.$thumb_down_image.'"><div '.$button_down_style.' >Dislike </div>
			</div>';
			echo '<div style="float:right" class="post-vote-count">
			<div class="thumb-total-count" 			                						 				style="width:100px;float:right;text-align:right;padding-right:5px;font-size:19px;padding-bottom:10px;" 	title="'.$vote_total_count.' vote'.($vote_total_count != 1 ? 's' : '').' so far" > '.$vote_total_count.'
			</div>';
			echo '<div style="clear:both"></div>';
			if($thumb_up_count>$thumb_down_count){
				echo '<div style="width:100px; height:2px;background-color:#52b646;" >';
			}elseif($thumb_up_count<$thumb_down_count){
				echo '<div style="width:100px; height:2px;background-color:red;" >';
			}else{
				echo '<div style="width:100px; height:2px;background-color:#929292;" >';
			}
			
			echo '<div style="width:'.$vote_percent.'px; height:2px;background-color:#52b646" >&nbsp;</div></div>';


			echo '<div class="thumb-down-count" 
			style="float:right;">'.($thumb_down_count?$thumb_down_count:0) .'</div>
			<img src="'.plugins_url( '/images/down.png', __FILE__).'" 
			style="float:right;margin-top:5px;margin-left:10px;">';
		
			echo '<div class="thumb-up-count" 
			style="float:right;">'.($thumb_up_count?$thumb_up_count:'0').'</div>
			<img src="'.plugins_url( '/images/up.png', __FILE__).'" style="float:right;"></div>';
			
		
						
		}else{

			echo '<div  class="rating-button-inactive vote-up-click" title="Please Signup/Login to Vote UP">
			<img class="thumb-up-button" src="'.$thumb_up_image.'"> 
			<div '.$button_style.' >Like </div></div> 
			<div class="rating-button-inactive vote-down-click" title="Please Signup/Login to Vote Down">
			<img class="thumb-down-button" src="'.$thumb_down_image.'">
			<div '.$button_down_style.' >Dislike </div>
			</div>';

			echo '<div class="post-vote-count">
			<div class="thumb-total-count" 
			style="width:100px;float:right;text-align:right;padding-right:5px;font-size:19px;padding-bottom:10px;" 	title="'.$vote_total_count.' vote'.($vote_total_count != 1 ? 's' : '').' so far">'.
			$vote_total_count.'</div>';
			echo '<div style="clear:both"></div>';
			echo '<div style="width:100px; height:2px;background-color:#EDEDED;" >
			<div style="width:'.$vote_percent.'px; height:2px;background-color:#5EBC40" >&nbsp;
			</div></div>';


			echo '<div class="thumb-down-count" 
			style="float:right;">'.($thumb_down_count?$thumb_down_count:0) .'</div>
			<img src="'.plugins_url( '/images/down.png', __FILE__).'" 
			style="float:right;margin-top:5px;margin-left:10px;">';
		
			echo '<div class="thumb-up-count" 
			style="float:right;">'.($thumb_up_count?$thumb_up_count:'0').'</div>
			<img src="'.plugins_url( '/images/up.png', __FILE__).'" style="float:right;"></div>';
			

		}

	}

	public function get_comment_votes_total( $comment_id ) {
		global $wpdb;
		if ( !$comment_id )
			return false;

		$result_query = $wpdb->get_results($wpdb->prepare("SELECT vote_count_up, vote_count_down 
				FROM ".$wpdb->base_prefix."comment_vote_totals 
				WHERE comment_id = %d", $comment_id));

		return ( count($result_query) == 1 ? array( "up" => $result_query[0]->vote_count_up,
		"down" => $result_query[0]->vote_count_down ) : array( "up" => 0, "down" => 0 ));
	}

	

	public function get_comment_user_vote( $user_id, $comment_id ) {
		global $wpdb;
		return $wpdb->get_var($wpdb->prepare("SELECT vote_value 
		FROM ".$wpdb->base_prefix."comment_votes
		WHERE voter_id = %s AND comment_id = %d", $user_id, $comment_id));
	}

	

	function add_vote_in_comment($content){

		$comment_id = get_comment_ID();
		if ( !$comment_id )
			return false;
			echo $content;
		$vote_counts = $this->get_comment_votes_total( $comment_id );
		$existing_vote = $this->get_comment_user_vote( $this->get_user_id(), $comment_id );

		echo '<div style="clear:both;padding-top:5px;"></div><div class="" id="comment-'.$comment_id.'" comment-id="'.$comment_id.'">';
		$this->load_comment_votes( $vote_counts["up"], $vote_counts["down"], $existing_vote, get_the_ID() );
		echo'</div><div style="clear:both"></div>';
	}

	public function load_comment_votes( $thumb_up_count = 0, $thumb_down_count = 0, $existing_vote = 0, $comment_post_id=0 ) { 
		
	
		$thumb_up_status = '';
		$thumb_down_status = '';
		
		$votable = $this->get_user_id();
		
		if ( $existing_vote > 0 ){
			$thumb_up_status = '-on';
			$button_style = 'class="up-like-active"';
			$button_down_style = 'class="down-like"';
		}elseif ( $existing_vote < 0 ){
			$thumb_down_status = '-on';
			$button_down_style = 'class="down-like-active"';
			$button_style = 'class="up-like" ';
		}else{
			$button_style = 'class="up-like" ';
			$button_down_style = 'class="downn-like"';
		}		
		
		$thumb_up_image = plugins_url( '/images/thumb-up'.$thumb_up_status.'.png', __FILE__);
		$thumb_down_image = plugins_url( '/images/thumb-down'.$thumb_down_status.'.png', __FILE__);

		$vote_total_count = $thumb_up_count + $thumb_down_count;
							
		if ($votable){
			echo '<div class="rating-button" title="Vote Up" >
			<img class="thumb-up-button" vote-direction="1" 
			comment-post-id="'.$comment_post_id.'"  src="'.$thumb_up_image.'" > 
			<div '.$button_style.' >Like </div> </div>
			<div class="thumb-up-count" style="float:left;padding-left:5px;">'.($thumb_up_count?$thumb_up_count:'0').
			'</div> <div class="rating-button" title="Vote Down">
			<img class="thumb-down-button" vote-direction="-1" 
			comment-post-id="'.$comment_post_id.'" src="'.$thumb_down_image.'">
			<div '.$button_down_style.' >Dislike </div>
			</div>
			<div class="thumb-down-count" style="float:left;">'.($thumb_down_count?$thumb_down_count:'0').'</div>';
		}else{
			echo '<div class="inactive-panel" title="">';
			echo '<div class="rating-button-inactive vote-up-click" title="Please Signup/Login to Vote UP">
			<img class="thumb-up-button" vote-direction="1" 
			comment-post-id="'.$comment_post_id.'"  src="'.$thumb_up_image.'"> 
			<div '.$button_style.' >Like </div> </div>
			<div class="thumb-up-count" style="float:left;padding-left:5px;">'.($thumb_up_count?$thumb_up_count:'0').
			'</div> <div class="rating-button-inactive vote-down-click" title="Please Signup/Login to Vote Down">
			<img class="thumb-down-button" vote-direction="-1"
			comment-post-id="'.$comment_post_id.'" src="'.$thumb_down_image.'">
			<div '.$button_down_style.' >Dislike </div>
			</div>
			<div class="thumb-down-count" style="float:left;">'.($thumb_down_count?$thumb_down_count:'0').'</div></div>';
		}

	}


			

	public function process_vote(){
		// Include the file for ajax calls
		require_once('rating_ajax.php');
	}
}
	

//******************************************************************************************************
// END OF YoutubeLikeRating CLASS
//******************************************************************************************************



	//Create instance of plugin
	$ylr = new YoutubeLikeRating();

	//Handle plugin activation and update
	register_activation_hook( __FILE__, array( &$ylr, 'setup_plugin' ));
	add_action('init', array( &$ylr, 'setup_plugin' ), 1);
	if(get_option ("ylr_show_vote_on")  == 'post_only'){
		add_filter('the_content', array(&$ylr, 'add_vote_in_post'), 99);
	}elseif(get_option ("ylr_show_vote_on")  == 'comment_only'){
		add_filter('comment_text', array(&$ylr, 'add_vote_in_comment'), 99);
	}else{
		add_filter('the_content', array(&$ylr, 'add_vote_in_post'), 99);
		add_filter('comment_text', array(&$ylr, 'add_vote_in_comment'), 99);
	}




	
	//********************************************************************
	// Admin page
	
	function youtubeLikeRating_admin_menu()
	{
		add_options_page('YoutubeLikeRating', 'YoutubeLikeRating', 'manage_options', 'YoutubeLikeRating', 'youtubeLikeRating_options');
	} 

	function youtubeLikeRating_options()
	{
		global $ylr;
				
		if (isset ($_POST['Submit']))
		{
			// guest allowed
			if(isset($_POST['guest_allowed'])) {
				if(get_option('ylr_guest_allowed') === false) { 
					//bool false means does not exists
					add_option('ylr_guest_allowed', ($_POST['guest_allowed'] == "on"?'allowed':'not allowed'));
				}else{
					update_option('ylr_guest_allowed', 
					($_POST['guest_allowed'] == "on"?'allowed':'not allowed'));			

				}
			}else{
				if(get_option('ylr_guest_allowed') === false) { 
					//bool false means does not exists
					add_option('ylr_guest_allowed','not allowed');
				}else{
					update_option('ylr_guest_allowed', 'not allowed');			

				}
			}

			if(isset($_POST['show_vote_on'])){
				if(get_option('ylr_show_vote_on') === false){

					add_option('ylr_show_vote_on', $_POST['show_vote_on']);
				}else{
					update_option('ylr_show_vote_on', $_POST['show_vote_on']);			

				}
			}
		}
		
		echo '<div><h2>Youtube Like Rating Plugin Settings</h2>
		<form name="form1" method="post" action=""><table width="100%" cellpadding="5" class="form-table"><tbody>';
		
		echo '<tr valign="top"><th>Allow guests to vote:</th><td><input type="checkbox" 
		name="guest_allowed" id="guest_allowed" '.(get_option('ylr_guest_allowed') == 'allowed' ? 'checked="checked"' : '').'/> 
		<span class="description">Allow only logged in usesr to vote</span></td></tr>';
		
		echo '<tr valign="top"><th>Allow Vote On:</th><td><select name="show_vote_on">';
		echo '<option value="post_only"'.(get_option('ylr_show_vote_on') == 'post_only' ? 'selected = "selected"' : '').'>
			Post Only </option>';
		echo '<option value="comment_only"'.(get_option('ylr_show_vote_on') == 'comment_only' ? 'selected = "selected"' : '').'>
			Comment Only</option>';
		echo '<option value="both"'.(get_option('ylr_show_vote_on') == 'both' ? 'selected = "selected"' : '').'>
			Both Post and Comment</option>';
		echo '</select> </td></tr>';
		echo '<tr valign="top"><td><p class="submit"><input type="submit" name="Submit"
		class="button-primary" value="Save Changes" /></p></td></tr></tbody></table>';
		echo '</form></div>';
	}
?>
