<?php

//******************************************************************************************************
// Ajax Process Vote Function
//******************************************************************************************************
	
	global $wpdb;
	
	$result = array( 'status' => '-1', 
			'message' => '', 
			'vote_totals' => null, 
			'post_id' => null, 
			'comment_id' => null, 
			'direction' => null );


	if (!is_user_logged_in() && !$this->guest_allowed())
	{
		$result['message'] = 'You must be logged in to vote.';
		die(json_encode($result));
	}

	$user_id = $this->get_user_id();
	//Validate expected params
	if ( ($_POST['post_id'] == null && $_POST['comment_id'] == null)
	|| $_POST['direction'] == null	|| !$user_id )
		die(json_encode($result));

	$post_id = $_POST['post_id'];
	$comment_id = $_POST['comment_id'];
	if(isset($_POST['comment_post_id'])){
		$comment_post_id = $_POST['comment_post_id'];
	}else{
		$comment_post_id =0;
	}

	if ( $post_id != null ) {
		$element_name = 'post';
		$element_id = $post_id;
	} elseif ( $comment_id != null ) {
		$element_name = 'comment';
		$element_id = $comment_id;
	}
	else
		die(json_encode($result));

	$vote_value = $_POST['direction'];
	$down_vote = 0;
	$up_vote = 0;

	if ( $vote_value > 0 ) {
		$vote_value = 1;
		$up_vote_delta = 1;
	} elseif ( $vote_value < 0 ) { 
		$vote_value = -1;
		$down_vote_delta = 1;
	}

	if ( $element_name == 'post' )
		$existing_vote = $this->get_post_user_vote( $user_id, $post_id );
	elseif	( $element_name == 'comment' )
		$existing_vote = $this->get_comment_user_vote( $user_id, $comment_id );

	
	if ( $existing_vote < 0 )
		$down_vote_delta -= 1;
	elseif ( $existing_vote > 0 )
		$up_vote_delta -= 1;

	//Update user vote
	if ( $element_name == 'post' ){
		if ($existing_vote != null) {
			 $wpdb->query($wpdb->prepare("
				UPDATE ".$wpdb->base_prefix."post_votes
				SET vote_value = %d,
				date_time = '" . date('Y-m-d H:i:s') . "'
				WHERE voter_id = %s
				AND post_id = %d", $vote_value, $user_id, $element_id));
		} else {
			$wpdb->query($wpdb->prepare("
				INSERT INTO ".$wpdb->base_prefix."post_votes
				( vote_value, post_id, voter_id, date_time )
				VALUES
				( %d, %d, %s, %d )", $vote_value, $element_id, $user_id, date('Y-m-d H:i:s') ));
			$existing_vote = 0;
		}
		$total_result= $wpdb->get_row($wpdb->prepare("SELECT count(id) as total_up 
		FROM ".$wpdb->base_prefix."post_votes
		WHERE post_id = %d and vote_value = 1", $element_id)); 
		$up_vote_delta = $total_result->total_up;

		$total_result= $wpdb->get_row($wpdb->prepare("SELECT count(id) as total_down 
		FROM ".$wpdb->base_prefix."post_votes
		WHERE ".$element_name."_id =%d and vote_value = -1 ", $element_id)); 
		$down_vote_delta = $total_result->total_down;

		//Update total
	
		if ($wpdb->query($wpdb->prepare("
			UPDATE ".$wpdb->base_prefix."post_vote_totals
			SET vote_count_up = (%d),
			vote_count_down = (%d),
			date_time = '" . date('Y-m-d H:i:s') . "'
			WHERE post_id = %d", $up_vote_delta, $down_vote_delta, $element_id)) == 0)
			$wpdb->query($wpdb->prepare("
		        INSERT INTO ".$wpdb->base_prefix."post_vote_totals
			( vote_count_up, vote_count_down, post_id, date_time)
			VALUES
			( %d, %d, %d, %d )", $up_vote_delta, $down_vote_delta, $element_id, date('Y-m-d H:i:s')));

	}elseif ( $element_name == 'comment' ){
		if ($existing_vote != null) {
			$wpdb->query($wpdb->prepare("
				UPDATE ".$wpdb->base_prefix."comment_votes
				SET vote_value = %d,
				date_time = '" . date('Y-m-d H:i:s') . "'
				WHERE voter_id = %s
				AND ".$element_name."_id = %d 
				AND post_id = %d", $vote_value, $user_id, $element_id, $comment_post_id ));
		} else {
			$wpdb->query($wpdb->prepare("
				INSERT INTO ".$wpdb->base_prefix."comment_votes
				( vote_value, comment_id, voter_id, post_id, date_time )
				VALUES
				( %d, %d, %s, %d, %d )", $vote_value, $element_id, $user_id, $comment_post_id, date('Y-m-d H:i:s')  ));
				$existing_vote = 0;
		}
		
		$total_result= $wpdb->get_row($wpdb->prepare("SELECT count(id) as total_up 
		FROM ".$wpdb->base_prefix."comment_votes
		WHERE comment_id = %d AND vote_value = 1 
		AND post_id = %d",$element_id , $comment_post_id )); 
		$up_vote_delta = $total_result->total_up;

		$total_result= $wpdb->get_row($wpdb->prepare("SELECT count(id) as total_down 
		FROM ".$wpdb->base_prefix."comment_votes
		WHERE comment_id =%d AND vote_value = -1 
		AND post_id = %d", $element_id, $comment_post_id  )); 
		$down_vote_delta = $total_result->total_down;

		//Update total
		if ($wpdb->query($wpdb->prepare("
			UPDATE ".$wpdb->base_prefix."comment_vote_totals
			SET vote_count_up = (%d), vote_count_down = (%d), date_time = '" . date('Y-m-d H:i:s') . "'
			WHERE comment_id = %d AND post_id = %d", $up_vote_delta, $down_vote_delta, 
			$element_id, $comment_post_id )) == 0)
		
			$wpdb->query($wpdb->prepare("INSERT INTO ".$wpdb->base_prefix."comment_vote_totals
			( vote_count_up, vote_count_down, comment_id , post_id, date_time)
			VALUES
			( %d, %d, %d, %d, %d )", $up_vote_delta, $down_vote_delta, $element_id, $comment_post_id, date('Y-m-d H:i:s') ));
		}
	
	
	

	//Return success
	$result["status"] = 1;
	$result["message"] = "Your vote has been registered for this ".$element_name.".";
	$result["post_id"] = $post_id;
	$result["comment_id"] = $comment_id;
	$result["direction"] = $vote_value;

	$result["vote_totals"] = $wpdb->get_row($wpdb->prepare("SELECT vote_count_up as up, 
				vote_count_down as down
				FROM ".$wpdb->base_prefix.$element_name."_vote_totals
				WHERE ".$element_name."_id = %d", $element_id));

	// Check for method of processing the data
	if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') 				{		
		echo json_encode($result);
	}else{
		header("location:" . $_SERVER["HTTP_REFERER"]);
	}
	exit;

//******************************************************************************************************
// End Of Ajax Process Vote Function
//******************************************************************************************************
?>
