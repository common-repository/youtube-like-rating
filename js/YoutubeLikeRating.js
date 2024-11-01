jQuery(document).ready(function(){


  function votePost( post_id, direction ) {
      var data = {
        action: 'process_vote',
        post_id: post_id,
        direction: direction
      };
      jQuery.post(ratingAjax.ajaxurl, data, function(response){ handleVotes(response); });
  }

  function voteComment( comment_id, direction, comment_post_id ) {
      var data = {
        action: 'process_vote',
        comment_id: comment_id,
        direction: direction,comment_post_id : comment_post_id 
      };
      jQuery.post(ratingAjax.ajaxurl, data, function(response){ handleVotes(response); });
  }

  function handleVotes( response ) {


	var vote_response = eval('('+response+')');
	if ( vote_response.status == 1 ) {
	
	if ( vote_response.post_id ) {
		var element_name = 'post';
		var element_id = vote_response.post_id;
	} else if ( vote_response.comment_id ) {
		var element_name = 'comment';
		var element_id = vote_response.comment_id;
	}

	var thumb_up_count = jQuery( '#' + element_name + '-' + element_id + ' .thumb-up-count' );
	var thumb_up_button = jQuery( '#' + element_name + '-' + element_id + ' .thumb-up-button' );

	var vote_total_count = jQuery( '#' + element_name + '-' + element_id + ' .thumb-total-count' );
		
	var thumb_down_count = jQuery( '#' + element_name + '-' + element_id + ' .thumb-down-count' );
	var thumb_down_button = jQuery( '#' + element_name + '-' + element_id + ' .thumb-down-button' );


	deactivate_vote( thumb_down_button );
	deactivate_vote( thumb_up_button );
	if ( vote_response.direction == 1 )
		activate_vote( thumb_up_button );
	else if ( vote_response.direction == -1 )
		activate_vote( thumb_down_button );
	vote_total_count_num = parseInt(vote_response.vote_totals.up) + parseInt(vote_response.vote_totals.down);
	vote_total_count.text(vote_total_count_num);

			
			if ( vote_response.vote_totals.up == 0 && vote_response.vote_totals.down == 0)
			{
				if (thumb_up_count.length)
        				thumb_up_count.hide();
				if (thumb_down_count.length)
       					 thumb_down_count.hide();
				if (vote_total_count.length)
					vote_total_count.hide ();
			}
			else
			{

				if ( vote_response.vote_totals.up > 0 )
					vote_response.vote_totals.up = "" + vote_response.vote_totals.up;
				if ( vote_response.vote_totals.down > 0 )
					vote_response.vote_totals.down = "" + vote_response.vote_totals.down;
        
				if (thumb_up_count.length)
       					thumb_up_count.text(vote_response.vote_totals.up).show();

				if (thumb_down_count.length)
       					thumb_down_count.text(vote_response.vote_totals.down).show();
				if (vote_total_count.length)
					vote_total_count.show ();
      }
    } 
  }

	function activate_vote( buttonObj ) {
		buttonObj.attr( 'src', buttonObj.attr( 'src' ).replace( '.png', '-on.png' ) );
		if(buttonObj.parent().children().length>1){
			if(buttonObj.parent().children()[1].className=='up-like'){
				buttonObj.parent().children()[1].className = 'up-like-active';
			}else{
				buttonObj.parent().children()[1].className = 'down-like-active';
			}
		}
	}

	function deactivate_vote( buttonObj ) {
		buttonObj.attr( 'src', buttonObj.attr( 'src' ).replace( '-on.png', '.png' ) );
		if(buttonObj.parent().children().length>1){
			if(buttonObj.parent().children()[1].className=='up-like-active'){
				buttonObj.parent().children()[1].className = 'up-like';
			}else{
				buttonObj.parent().children()[1].className = 'down-like';
			}
		}
	}

	
	jQuery('.rating-button').live( 'click', function( event )
	{

		var id = jQuery(this).parent().attr('id').split("-");
		var vote_value = -1;
		var button_obj = jQuery(this).children()[0];

    		//Remove vote if clicking same vote again
		if ( button_obj.src.indexOf('-on.png') >= 0 )
			vote_value = 0;
		else
			vote_value = button_obj.getAttribute("vote-direction");

		if (id[0] == "post" && id[1])
			votePost(id[1], vote_value );
		if (id[0] == "comment" && id[1]){
			comment_post_id =  button_obj.getAttribute("comment-post-id");
			voteComment(id[1], vote_value, comment_post_id);
		}
 	 });
	jQuery('.vote-up-click').tipTip({
		activation:"click",
		height:100
		
	});
	jQuery('.vote-down-click').tipTip({
		activation:"click",
		height:100
		
	});

});

