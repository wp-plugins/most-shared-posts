<?php
/*
Plugin Name: Most Shared Posts
Plugin URI: http://www.tomanthony.co.uk/wordpress-plugins/most-shared-posts/
Description: Showcases your posts with the most social shares to your visitors in the sidebar. Please consider a small <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=ZS48RS294BLGN" target="_blank">donation</a>.
Version: 1.0.1
Author: Tom Anthony
Author URI: http://www.tomanthony.co.uk/

Copyright (C) 2011-2011, Tom Anthony
All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
The name of Tom Anthony may not be used to endorse or promote products derived from this software without specific prior written permission.
THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/



// ===========================================
// ===========================================
// INIT SECTION: Place our required hooks etc.
// ===========================================
// ===========================================

// Add our hook on init
add_action( 'widgets_init', 'load_most_shared_posts_widget' );

// Add a header hook
add_action('wp_head', 'most_shared_posts_head');

// Add the admin menu for showing the options
add_action('admin_menu', 'show_most_shared_posts_options');

// This will show any messages we have for the user.
add_action( 'admin_notices', 'plugin_notices' );	

// Add a link to our settings page in the plugins list
$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'most_shared_posts_link' );

// Register our activate function
register_deactivation_hook(__FILE__, 'clear_welcome_message' );

// Register our uninstall function
register_uninstall_hook(__FILE__, 'most_shared_posts_deinstall');


// ===========================================
// ===========================================
// END INIT SECTION
// ===========================================
// ===========================================

// Standard register widget function
function load_most_shared_posts_widget() {
	register_widget( 'Most_Shared_Posts' );
}

// Our header function which'll hook in our CSS file
function most_shared_posts_head() {
	echo '<link rel="stylesheet" type="text/css" href="' . plugins_url('most-shared-posts.css', __FILE__). '">';
}
	
/**
 * Most_Shared_Posts class.
 *
 * @since 1.0.0
 */
class Most_Shared_Posts extends WP_Widget {

	/**
	 * Widget setup.
	 */
	function Most_Shared_Posts() {
		global $wp_version;
		
		/* Widget settings. */
		$widget_ops = array( 'classname' => 'most-shared-posts', 'description' => 'Showcases your most shared posts to your visitors in your blog\'s sidebar.' );

		/* Widget control settings. */
		$control_ops = array( 'width' => 250, 'height' => 350, 'id_base' => 'toma_msp' );

		/* Create the widget. */
		$this->WP_Widget( 'toma_msp', 'Most Shared Posts', $widget_ops, $control_ops );
		
		if (version_compare($wp_version,"2.8","<"))
		{
			exit ("Most Shared Posts requires Wordpress version 2.8 or later. Please update Wordpress. :)");
		}
	}


	/**
	 * How to display the widget on the screen.
	 */
	function widget( $args, $instance ) {
		extract( $args );

		/* Our variables from the widget settings. */
		$title = apply_filters('widget_title', $instance['title'] );

		/* Before widget (defined by themes). */
		echo $before_widget;

		/* Display the widget title if one was input (before and after defined by themes). */
		if ( $title )
			echo $before_title . $title . $after_title;
		
		// ===========================================
		// ===========================================
		// STAGE 1: Sorting out if we need to update
		// out caches.
		// ===========================================
		// ===========================================
		
		// Calculate the recency limit now.
		// There is no point in hitting the APIs for posts
		// we are not interested in.
		
		$recency_limit_unit = isset( $instance['recency_limit_unit'] ) ? $instance['recency_limit_unit'] : 2;
		
		global $recency_limit;
		$recency_limit = 730;
		
		switch($recency_limit_unit)
		{
			case "days":
				$recency_limit = intval($instance['recency_limit_number']);
				break;
			case "months":
				$recency_limit = intval($instance['recency_limit_number']) * 31;
				break;
			case "years":
				$recency_limit = intval($instance['recency_limit_number']) * 365;
				break;
			default:
				$recency_limit = 730;
				break;
		}
		
		
		// If something went wrong then set 2 years as a fallback.
		if ($recency_limit <= 0)
			$recency_limit = 730;
			
		// List of filter functions for filtering posts of different ages into our query

		function add_day_filter( $where = '', $from_x_days_back, $to_x_days_back ) {
			global $recency_limit;
			
			$days_into_past = $from_x_days_back;
			
			if ($recency_limit < $from_x_days_back)
			{
				$days_into_past = $recency_limit;
			}
			
			$where .= " AND post_date >= '" . date('Y-m-d', strtotime('-' . $to_x_days_back .' days')) . "'" . " AND post_date <= '" . date('Y-m-d', strtotime('-' . $days_into_past .' days')) . "'";
			
			return $where;
		}
		
		function last_2_days( $where = '' ) {
			$where .= " AND post_date > '" . date('Y-m-d', strtotime('-2 days')) . "'";
			return $where;
		}
		
		function from_2_to_7_days( $where = '' ) {
			$where .= " AND post_date >= '" . date('Y-m-d', strtotime('-7 days')) . "'" . " AND post_date <= '" . date('Y-m-d', strtotime('-2 days')) . "'";
			return $where;
		}
		
		function from_7_to_30_days( $where = '' ) {
			return add_day_filter($where, 8, 30);
		}
		
		function from_30_to_180_days( $where = '' ) {
			return add_day_filter($where, 31, 180);
		}
		
		function older_than_180( $where = '' ) {
			return add_day_filter($where, 181, 3600); // 10 year default
		}
		
		function within_recency_limit( $where = '' ) {
			global $recency_limit;
			
			$where .= " AND post_date >= '" . date('Y-m-d', strtotime('-' . $recency_limit .' days')) . "'";
			
			return $where;
		}
		
		// End of filter functions
		
		// This is our standard set of arguments for our query.
		// Todo - better way to do the 500 / limit?
		$args = array(
			'posts_per_page' => 500,
			'orderby' => 'date',
			'order' => 'DESC',
			'ignore_sticky_posts' => 1
		);
		
		// last 2 days - every 1 hour
		// last week - every 6 hours
		// last month - every 12 hours
		// last 6 months - every 48 hours
		// older - every week
		
		// It may be better to change this to ordering by comment count
		// as their is likely some sort of rough correlation? Todo.
		
		// This specifies how long to cache posts of different
		// ages, by associating a specific filter function for
		// an age range with a cache length (in secs).
		//
		// We cache older posts for much longer, on the assumption
		// they won't be getting shared so often.
		$filter_functions_and_cache_times = array(
			'last_2_days' => (60*60),
			'from_2_to_7_days' => (60*60*6),
			'from_7_to_30_days' => (60*60*12),
			'from_30_to_180_days' => (60*60*48),
			'older_than_180' => (60*60*24*7)
		);
		
		// if we recently checked our caches, then do not bother
		if (is_numeric(get_transient("msp_recently_checked_counts")))
		{
			// All is well with the world. Tum te tum.
			
			//echo "<!-- No Check Performed due to recency. -->";

		}else{ // Otherwise, check the caches...
			
			//echo "<!-- Ok beginning checks. -->";
			
			// First, set this so we don't check again too soon.
			set_transient("msp_recently_checked_counts", 99, 60*10); // don't check again for 10 minutes.
			
			// This variable will count how many API calls
			// we have made this time around. We'll use it
			// to prevent too many in one go, so a single
			// user isn't slowed up too much.
			$api_hits_counter = 0;
			
			// Loop over te various date ranges fetching posts and checking if we need
			// to update our caches for each.
			
			foreach ($filter_functions_and_cache_times as $date_range_filter_function=>$cache_time)
			{
				//echo "<!-- Next Filter. -->";
				
				// Set the date range_filter
				add_filter( 'posts_where', $date_range_filter_function );
		
				$posts_in_range = new WP_Query( $args );
				
				while ( $posts_in_range->have_posts() ) : $posts_in_range->the_post();
					
			
					//echo "<!-- Next Post. -->";
				
					$transient_base = "msp_trans_" . get_the_ID() . "_";
					
					// Now we check if we have cached results for each of the 3 social counts
					// and update if necessary. Currently, they are all cached for the same time
					// but I check them each separately in case in future I cached for different
					// time periods.
					
					// We gather the data for each, even if in the options they are set not
					// to be included. This is such that users can change settings in an
					// instant and it'll not have to queue to fetch that data.
					
					// We keep a cached recent copy using transients, which also serves
					// as how we detect if we checked recently. We also keep a longer term
					// cached copy using meta data. These are prefixed with an underscore
					// which makes them hidden in the Wordpress admin interface when people
					// are editing posts.
				
					// ============
					// FACEBOOK
					// ============
			
					// If have a cached Facebook Likes count for this post...
					if (is_numeric($fb_likes = get_transient($transient_base."_fb_likes")))
					{
						// ... then great. We can sit back and relax. :)
						$post_likes = $fb_likes;
						
						//echo "<!-- FB Likes fetched with Transient = " . $post_likes . " -->";
						
					}else{
						
						//echo "<!-- Fetching FB Likes from API -->";
						
						// ... if not, then lets check the Facebook API.
						$api_hits_counter++;
						$facebook_api_results = file_get_contents("http://api.facebook.com/restserver.php?method=links.getStats&format=json&urls=" . urlencode(get_permalink()));
						$parsed_facebook_api_results = json_decode($facebook_api_results, true);
						
						// The FB like button now shows the total shares count for FB,
						// so we do too! :)
						//$post_likes = $parsed_facebook_api_results[0]['like_count'];
						$post_likes = $parsed_facebook_api_results[0]['total_count'];
						
						if (is_numeric($post_likes)) // We got a valid response from the API.
						{
							// Now cache the result...
							
							// The short version:
							// Includes a randomiser to offset the odds of one
							// user having to request multiple posts.
							$this_cache_time = $cache_time + rand(60*1, 60*25);
							set_transient($transient_base."_fb_likes", $post_likes, $this_cache_time);
							
							// The long version
							update_post_meta(get_the_ID(), "_msp_fb_likes", $post_likes);
							
						
							//echo "<!-- FB Likes fetched with API = " . $post_likes . " -->";
							
						}else{ // Looks like we failed to get a valid response
						
							// Fetch the cached version
							// if we don't have a cached version either, then
							// we'll just end up with 0 which is fine. :)
							
							$post_likes = intval(get_post_meta(get_the_ID(), "_msp_fb_likes", true));
						
							//echo "<!-- FB Likes API failed. Cached = " . $post_likes . " -->";
							
							// Now we cache it to transient, but always for 6 hours, until
							// hopefully the API comes back.
							
							set_transient($transient_base."_fb_likes", $post_likes, 60*60*6);
						}
						
					}
				
					// ============
					// TWITTER
					// ============
			
					// If have a cached Twitter Tweets count for this post...
					if (is_numeric($tweets = get_transient($transient_base."_tweets")))
					{
						// ... then great. We can sit back and relax. :)
						$post_tweets = $tweets;
						
						//echo "<!-- Tweets fetched with Transient = " . $post_tweets . " -->";
					}else{
						//echo "<!-- Fetching Tweets from API -->";
						
						// ... if not, then lets check the Twitter API.
						$api_hits_counter++;
						$twitter_api_results = file_get_contents("http://urls.api.twitter.com/1/urls/count.json?url=" . urlencode(get_permalink()));
						
						$parsed_twitter_api_results = json_decode($twitter_api_results, true);
						$post_tweets = $parsed_twitter_api_results['count'];
						
						if (is_numeric($post_tweets)) // We got a valid response from the API.
						{
							// Now cache the result...
							
							// The short version:
							// Includes a randomiser to offset the odds of one
							// user having to request multiple posts.
							$this_cache_time = $cache_time + rand(60*1, 60*25);
							set_transient($transient_base."_tweets", $post_tweets, $this_cache_time);
							
							// The long version
							update_post_meta(get_the_ID(), "_msp_tweets", $post_tweets);
							
						
							//echo "<!-- Tweets fetched with API = " . $post_tweets . " -->";
							
						}else{ // Looks like we failed to get a valid response
						
							// Fetch the cached version
							// if we don't have a cached version either, then
							// we'll just end up with 0 which is fine. :)
							$post_tweets = intval(get_post_meta(get_the_ID(), "_msp_tweets", true));
						
							//echo "<!-- Tweets API failed. Cached = " . $post_tweets . " -->";
							
							// Now we cache it to transient, but always for 6 hours, until
							// hopefully the API comes back.
							
							set_transient($transient_base."_tweets", $post_tweets, 60*60*6);
						}
					}
				
					// ============
					// GOOGLE
					// ============
					
					// If have a cached Google +1 count for this post...
					if (is_numeric($plusones = get_transient($transient_base."_google_plus_ones")))
					{
						// ... then great. We can sit back and relax. :)
						$post_plus_ones = $plusones;
						
						//echo "<!-- Google +s fetched with Transient = " . $post_plus_ones . " -->";
					}else{
						
						//echo "<!-- Fetching Google +s from API -->";
						
						// ... if not, then lets check the Google API.
						
						$api_hits_counter++;
						
						// This API is a bit more complicated as we have to send a JSON request
						// using POST. Details: http://www.tomanthony.co.uk/blog/google_plus_one_button_seo_count_api/
						$ch = curl_init(); 
						curl_setopt($ch, CURLOPT_URL, "https://clients6.google.com/rpc?key=AIzaSyCKSbrvQasunBoV16zDH9R33D88CeLr9gQ");
						curl_setopt($ch, CURLOPT_POST, 1);
						curl_setopt($ch, CURLOPT_POSTFIELDS, '[{"method":"pos.plusones.get","id":"p","params":{"nolog":true,"id":"' . get_permalink() . '","source":"widget","userId":"@viewer","groupId":"@self"},"jsonrpc":"2.0","key":"p","apiVersion":"v1"}]');
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
						curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
						$curl_results = curl_exec ($ch);
						curl_close ($ch);

						$parsed_results = json_decode($curl_results, true);

						$post_plus_ones = $parsed_results[0]['result']['metadata']['globalCounts']['count'];
	
						if (is_numeric($post_plus_ones)) // We got a valid response from the API.
						{
							// Now cache the result...
							
							// The short version:
							// Includes a randomiser to offset the odds of one
							// user having to request multiple posts.
							$this_cache_time = $cache_time + rand(60*1, 60*25);
							set_transient($transient_base."_google_plus_ones", $post_plus_ones, $this_cache_time);
							
							// The long version
							update_post_meta(get_the_ID(), "_msp_google_plus_ones", $post_plus_ones);
							
						
							//echo "<!-- Google +s fetched with API = " . $post_plus_ones . " -->";
							
						}else{ // Looks like we failed to get a valid response
						
							// Fetch the cached version
							// if we don't have a cached version either, then
							// we'll just end up with 0 which is fine. :)
							$post_plus_ones = intval(get_post_meta(get_the_ID(), "_msp_google_plus_ones", true));
						
							//echo "<!-- Google +s API failed. Cached = " . $post_plus_ones . " -->";

							// Now we cache it to transient, but always for 6 hours, until
							// hopefully the API comes back.

							set_transient($transient_base."_google_plus_ones", $post_plus_ones, 60*60*6);
						}
					}
					
					// ============
					// TOTALS
					// ============
					
					
					// Add the 3 networks values to get a total
					// Check the options of which to include
					
					$post_totals = 0;
					
					if (get_option('toma_msp_include_fb') == 'on')
						$post_totals += intval($post_likes);
					
					if (get_option('toma_msp_include_twitter') == 'on')
						$post_totals += intval($post_tweets);
					
					if (get_option('toma_msp_include_google') == 'on')
						$post_totals += intval($post_plus_ones);
					
					
						
					//echo "<!-- Totals calculated as = " . $post_totals . " -->";
							
					// This will overwrite the previous value
					update_post_meta(get_the_ID(), "_msp_total_shares", $post_totals);
					
					// If we have done more than 15 API calls then
					// stop checking for this user and move on to
					// displaying.
					// Don't be tempted to 'break 2' because
					// we need to make sure we do the post
					// reset and remove_filter at the bottom of
					// the next loop out
					if ($api_hits_counter > 14)
						break;
			
				endwhile;
		
				wp_reset_postdata();
				
				// Remove the date range filter.
				remove_filter('posts_where', $date_range_filter_function);
				
				// If we have done more than 15 API calls then
				// stop checking for this user and move on to
				// displaying.
				if ($api_hits_counter > 14)
					break;
			}
		}
		
		
		// ===========================================
		// ===========================================
		// STAGE 2: Checking what we need to display,
		// and then displaying it. :)
		// ===========================================
		// ===========================================
		
		// Read options on which networks to include
					
		$include_fb_count = (get_option('toma_msp_include_fb') == 'on') ? true : false;
		$include_twitter_count = (get_option('toma_msp_include_twitter') == 'on') ? true : false;
		$include_google_count = (get_option('toma_msp_include_google') == 'on') ? true : false;
		$suppress_icons = (get_option('toma_msp_suppress_icons') == 'on') ? true : false;
		$h3_wrap = (get_option('toma_msp_h3_wrap') == 'on') ? true : false;
		$attribution_link = (get_option('toma_msp_attribution_link') == 'on') ? true : false;
		
		
		
		// Read the option on font-size
		
		$css_class_font = '';
		
		switch(get_option('toma_msp_font_size'))
		{
			case "smaller":
				$css_class_font = "share-counts-smaller";
				break;
			case "standard":
				$css_class_font = "";
				break;
			case "bigger":
				$css_class_font = "share-counts-bigger";
				break;
			case "even-bigger":
				$css_class_font = "share-counts-even-bigger";
				break;
			case "huge":
				$css_class_font = "share-counts-huge";
				break;
			default:
				$css_class_font = "share-counts-bigger";
				break;
		}
		
		// Read the option on icon-size
		
		$icon_pixel_size = 16;
		
		switch(get_option('toma_msp_icon_size'))
		{
			case "smaller":
				$icon_pixel_size = 12;
				break;
			case "standard":
				$icon_pixel_size = 16;
				break;
			case "bigger":
				$icon_pixel_size = 20;
				break;
			case "huge":
				$icon_pixel_size = 25;
				break;
			default:
				$icon_pixel_size = 16;
				break;
		}
		
		
		// Read the option on how many posts to display.
		$number_of_posts = intval($instance['number_of_posts_to_list']);
		
		if ($number_of_posts <= 0)
			$number_of_posts = 5;
		
		// Setup and run the query for getting the list of posts to show
		$args = array(
			'posts_per_page' => $number_of_posts,
			'orderby' => 'meta_value_num',
		    'meta_key' => '_msp_total_shares',
			'order' => 'DESC'
		);
		
		// Add the filter here to get only those
		// within the setting of how far back
		// to check.
		add_filter( 'posts_where', "within_recency_limit" );
		
		$posts_in_range = new WP_Query( $args );
		
		
		// Start the loop to loop over each post we are going to
		// list in the widget.
		
		
		//echo "<!-- Begin loop for showing. -->";
					
		echo '<ul class="entries">';
			
		while ( $posts_in_range->have_posts() ) : $posts_in_range->the_post();
			
			//echo "<!-- Next post -->";
		
			$fb_likes = get_post_meta(get_the_ID(), "_msp_fb_likes", true);
			$tweets = get_post_meta(get_the_ID(), "_msp_tweets", true);
			$plusones = get_post_meta(get_the_ID(), "_msp_google_plus_ones", true);
			$totals = get_post_meta(get_the_ID(), "_msp_total_shares", true);
			
			
			//echo "<!-- Likes fetched as = " . $fb_likes . " -->";
			//echo "<!-- Tweets fetched as = " . $tweets . " -->";
			//echo "<!-- PlusOnes fetched as = " . $plusones . " -->";
			//echo "<!-- Totals fetched as = " . $totals . " -->";
			
			echo '<li>';
	
			if ($h3_wrap)
				echo '<h3 class="post-title" >';
			
			echo '<a href="' . get_permalink() . '" rel="bookmark">' . get_the_title() . '</a>';
			
			if ($h3_wrap)
				echo '</h3>';
			
			//echo '<span class="date">' . get_the_date() . '</span>';
			
			if (!$suppress_icons)
			{
				echo '<div class="share-counts ' . $css_class_font . '">';
				
				if ($include_google_count)
				{
				echo '<img src="' . plugins_url('google_icon.png', __FILE__) . '" width="'.$icon_pixel_size.'px" height="'.$icon_pixel_size.'px" title="Google +1s" alt="Google +1 logo" />' . $plusones;
				
				echo " &nbsp; ";
				}
				
				if ($include_twitter_count)
				{
				echo '<img src="' . plugins_url('twitter_icon.png', __FILE__) . '" width="'.$icon_pixel_size.'px" height="'.$icon_pixel_size.'px" title="Tweets" alt="Twitter logo" />' . $tweets;
				
				echo " &nbsp; ";
				}
				
				
				if ($include_fb_count)
				{
				echo '<img src="' . plugins_url('facebook_icon.png', __FILE__) . '" width="'.$icon_pixel_size.'px" height="'.$icon_pixel_size.'px" title="Facebook shares" alt="Facebook logo" />' . $fb_likes;
				}
				
				//echo '<img src="' . plugins_url('shares_icon.png', __FILE__) . '" width="12px" height="12px" />' . $totals;
				
				
				echo '</div>';
			}
				
			echo '</li>';
		
		endwhile;
		// End loop over ther posts to show.
				
		echo '</ul>';
		
		if ($attribution_link)
		{
			echo "<small>Plugin by <a href='http://www.tomanthony.co.uk/wordpress-plugins/most-shared-posts/'>Tom Anthony</a></small>";
		}
		
		wp_reset_postdata();
		
		// Remove the date range filter.
		remove_filter('posts_where', "within_recency_limit");

		echo $after_widget;
	}

	// Update the settings for a particular instance
	// of the widget. This is separate to the global
	// settings that apply to all our widgets.
	function update( $new_instance, $old_instance ) {

		$instance = $old_instance;
		
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['recency_limit_unit'] = strip_tags( $new_instance['recency_limit_unit'] );
		$instance['recency_limit_number'] = intval( $new_instance['recency_limit_number'] );
		$instance['number_of_posts_to_list'] = intval( $new_instance['number_of_posts_to_list'] );

		return $instance;
	}

	// Display the form with the options for an
	// instance of the widget.
	function form( $instance ) {

		/* Set up some default widget settings. */
		$defaults = array( 'title' => 'Most Shared Posts', 'recency_limit_unit' => 'years', 'recency_limit_number' => 2, 'number_of_posts_to_list' => 5);
		$instance = wp_parse_args( (array) $instance, $defaults ); ?>

		<!-- Widget Title: Text Input -->
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e('Title:', 'hybrid'); ?></label>
			<input id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo $instance['title']; ?>" style="width:100%;" />
		</p>
	
        <p>
        	<label for="<?php echo $this->get_field_id( 'recency_limit_number' ); ?>">Lists posts from the last:</label><br />
        	 
        	<input type="text" name="<?php echo $this->get_field_name( 'recency_limit_number' ); ?>" value="<?php echo $instance['recency_limit_number']; ?>" id="<?php echo $this->get_field_id( 'recency_limit_number' ); ?>" size="4" /> 

		    <select name="<?php echo $this->get_field_name( 'recency_limit_unit' ); ?>">
		                  <option value="days" <?php if ($instance['recency_limit_unit'] == 'days') { echo "selected=\"selected\""; } ?>>
		                    days
		                  </option>
		                  <option value="months" <?php if ($instance['recency_limit_unit'] == 'months') { echo "selected=\"selected\""; } ?>>
		                    months
		                  </option>
		                  <option value="years" <?php if ($instance['recency_limit_unit'] == 'years') { echo "selected=\"selected\""; } ?>>
		                    years
		                  </option>
	                </select>
	    </p>
	    
        <p>
        	<label for="<?php echo $this->get_field_id( 'number_of_posts_to_list' ); ?>">How many posts to list:</label><br />
        	 
        	<input type="text" name="<?php echo $this->get_field_name( 'number_of_posts_to_list' ); ?>" value="<?php echo $instance['number_of_posts_to_list']; ?>" size="4" />
	    </p>

	<?php
	}
}



// Setup our options.
function show_most_shared_posts_options() {
	add_options_page('Most Shared Posts Options', 'Most Shared Posts', 'manage_options', 'most_shared_posts', 'most_shared_posts_options');
	
	add_option('toma_msp_include_fb', 'on');
	add_option('toma_msp_include_twitter', 'on');
	add_option('toma_msp_include_google', 'on');
	add_option('toma_msp_font_size', '');
	add_option('toma_msp_icon_size', 16);
	add_option('toma_msp_suppress_icons', 'off');
	add_option('toma_msp_h3_wrap', 'off');
	add_option('toma_msp_attribution_link', 'off');
	
}



// Display our options page
function most_shared_posts_options() { ?>
<style type="text/css">
div.headerWrap { background-color:#e4f2fds; width:200px}
#options h3 { padding:7px; padding-top:10px; margin:0px; cursor:auto }
#options label { width: 300px; float: left; margin-left: 10px; }
#options input { float: left; margin-left:10px}
#options p { clear: both; padding-bottom:10px; }
#options .postbox { margin:0px 0px 10px 0px; padding:0px; }
</style>
<div class="wrap">
<form method="post" action="options.php" id="options">
<?php wp_nonce_field('update-options') ?>
<h2>Most Shared Posts Options</h2>

<div class="postbox-container" style="width:100%;">
	<div class="metabox-holder">
	<div class="postbox">
		<h3 class="hndle"><span>Information</span></h3>
		<div style="margin:20px;">
			<p>NOTE: The plugin throttles how quickly it gathers the social data, to ensure it does not cause any slowness for your visitors. For small sites with less than 100 posts, this should typically be done in the first hour. For sites with other 2000 posts it could take a day or more.</p>
			<p>Furthermore, the social counts are cached to ensure good performance. Posts in the last 30 days are cached for between 1 to 12 hours depending on age. Posts up to 6 months old are cached for 48 hours, and posts over 6 months are cached for a week.</p>
	<a href="http://www.tomanthony.co.uk/wordpress-plugins/most-shared-posts/" style="text-decoration:none" target="_blank">Plugin Homepage</a> - More information on this plugin including FAQs. If you have feature requests you can contact me there. :)<br /><br />
			
			<div>
				
				Please consider a donation: <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=ZS48RS294BLGN" target="_blank"><img src="https://www.paypalobjects.com/en_GB/i/btn/btn_donate_SM.gif" border="0" /></a><br /><br />
				Alternatively, activate the attribution link (adds 'Plugin by Tom Anthony' as small text below the widget):<br /><br />
			<input class="checkbox" type="checkbox" <?php checked( get_option('toma_msp_attribution_link'), 'on' ); ?> id="toma_msp_attribution_link" name="toma_msp_attribution_link" /> 
			<label for="toma_msp_attribution_link">Include attribution link</label>
				
			</div>
		</div>
	</div>
	</div>


	<div class="metabox-holder">
	<div class="postbox">
		<h3 class="hndle"><span>Settings</span></h3>
		<div style="margin:20px;">
		<p>
			You'll need to add the "Most Shared Posts" widget to some of your pages first (you can choose which pages to include it on) in the 'Widgets' section of your Wordpress control panel. You can then separately configure each widget (how many posts to list, and how far back in time to include posts) when you add them. The settings below will affect all the "Most Shared Posts" widgets.
		</p>
		
		<p>
			Select which networks you wish to include; this will affect whether they are counted towards the total and whether they are displayed or not:
		</p>
		
		<!-- Include Facebook Checkbox -->
		<p>
			<input class="checkbox" type="checkbox" <?php checked( get_option('toma_msp_include_fb'), 'on' ); ?> id="toma_msp_include_fb" name="toma_msp_include_fb" /> 
			<label for="toma_msp_include_fb">Include Facebook Likes</label>
		</p>

		<!-- Include Twitter Checkbox -->
		<p>
			<input class="checkbox" type="checkbox" <?php checked( get_option('toma_msp_include_twitter'), 'on' ); ?> id="toma_msp_include_twitter" name="toma_msp_include_twitter" /> 
			<label for="toma_msp_include_twitter">Include Tweets</label>
		</p>

		<!-- Include Google Checkbox -->
		<p>
			<input class="checkbox" type="checkbox" <?php checked( get_option('toma_msp_include_google'), 'on' ); ?> id="toma_msp_include_google" name="toma_msp_include_google" /> 
			
			<label for="toma_msp_include_google">Include Google +1's</label>
		</p>
		
		<br />
		
		<p>This option will mean that no social network icons or share counts are displayed. The posts will just be shown by link, and will still be ordered by the networks selected above.</p>
		
		<!-- Suppress Icons Checbkox -->
		<p>
			<input class="checkbox" type="checkbox" <?php checked( get_option('toma_msp_suppress_icons'), 'on' ); ?> id="toma_msp_suppress_icons" name="toma_msp_suppress_icons" /> 
			
			<label for="toma_msp_suppress_icons">Don't display icons and counts.</label>
		</p>
		
		<br />
		
		
		
		<p>
			Configure how the share icons and counts appear:
		</p>
		
		
		<!-- Font Size Drop Down -->
		<p>
			<label for="toma_msp_font_size">Font size for counts:</label>
			
			<select name="toma_msp_font_size">
                  <option value="smaller" <?php if (get_option('toma_msp_font_size') == 'smaller') { echo "selected=\"selected\""; } ?>>
                    smaller
                  </option>
                  <option value="standard" <?php if (get_option('toma_msp_font_size') == 'standard') { echo "selected=\"selected\""; } ?>>
                    standard
                  </option>
                  <option value="bigger" <?php if (get_option('toma_msp_font_size') == 'bigger') { echo "selected=\"selected\""; } ?>>
                    bigger
                  </option>
                  <option value="even-bigger" <?php if (get_option('toma_msp_font_size') == 'even-bigger') { echo "selected=\"selected\""; } ?>>
                    even-bigger
                  </option>
                  <option value="huge" <?php if (get_option('toma_msp_font_size') == 'huge') { echo "selected=\"selected\""; } ?>>
                    huge
                  </option>
            </select>
		</p>
		
		<!-- Icon Size Drop Down -->
		<p>
			<label for="toma_msp_icon_size">Icon size for network icons:</label>
			
			<select name="toma_msp_icon_size">
                  <option value="smaller" <?php if (get_option('toma_msp_icon_size') == 'smaller') { echo "selected=\"selected\""; } ?>>
                    smaller
                  </option>
                  <option value="standard" <?php if (get_option('toma_msp_icon_size') == 'standard') { echo "selected=\"selected\""; } ?>>
                    standard
                  </option>
                  <option value="bigger" <?php if (get_option('toma_msp_icon_size') == 'bigger') { echo "selected=\"selected\""; } ?>>
                    bigger
                  </option>
                  <option value="huge" <?php if (get_option('toma_msp_icon_size') == 'huge') { echo "selected=\"selected\""; } ?>>
                    huge
                  </option>
            </select>
		</p>
	
		</div>
	</div>
	</div>

	<div class="metabox-holder">
	<div class="postbox">
		<h3 class="hndle"><span>Advanced Settings</span></h3>
		<div style="margin:20px;">
		<p>
			Using these advanced settings is not necessary, but if you are an advanced user and want to tweak how the widget is displayed, then this is where you can do that.
		</p>
		
		<br />
		
		
		<p>Depending on your theme, you may find that the list looks better if wrapped in an H3 tag.</p>
		
		<!-- H3 Wrap Checbkox -->
		<p>
			<input class="checkbox" type="checkbox" <?php checked( get_option('toma_msp_h3_wrap'), 'on' ); ?> id="toma_msp_h3_wrap" name="toma_msp_h3_wrap" /> 
			
			<label for="toma_msp_h3_wrap">Wrap post list in H3.</label>
		</p>
		
	
		</div>
	</div>
	</div>
	
<input type="hidden" name="action" value="update" />
<input type="hidden" name="page_options" value="toma_msp_include_fb, toma_msp_include_twitter, toma_msp_include_google, toma_msp_font_size, toma_msp_icon_size, toma_msp_suppress_icons, toma_msp_h3_wrap, toma_msp_attribution_link" />
<div class="submit"><input type="submit" class="button-primary" name="submit" value="Save Most Shared Posts Settings"></div>

</form>
</div>

<?php
}

// Function to add settings link on plugin page
function most_shared_posts_link($links) {
	$settings_link = '<a href="options-general.php?page=most_shared_posts">Settings</a>';
	array_unshift($links, $settings_link);
	return $links;
}


// Activate function will display a message informing
// the user how it takes a little time to collect data
// and how they need to install the widget.
function most_shared_posts_activate() {
	update_option('welcome_message', false);
}

function plugin_notices() {
	
	if (!get_option('welcome_message'))
	{
	$settings_link = "options-general.php?page=most_shared_posts";
echo '<div class="error fade"><p>Most Shared Posts will take a few mins to a few hours to gather the data. To start the data fetch you must add the widget to at least one sidebar.<br /><br />For more details visit the <a href="' . $settings_link . '">settings page</a>, or read the <a href="http://www.tomanthony.co.uk/wordpress-plugins/most-shared-posts/" target="_blank">online FAQ</a>.</p></div>';
	}
	update_option('welcome_message', true);
}

function clear_welcome_message() {
	update_option('welcome_message', false);
}

// The uninstall function
function most_shared_posts_deinstall() {

	// Delete the options we had setup.
	delete_option('toma_msp_include_fb');
	delete_option('toma_msp_include_twitter');
	delete_option('toma_msp_include_google');
	delete_option('toma_msp_font_size');
	delete_option('toma_msp_icon_size');
	delete_option('toma_msp_attribution_link');
	delete_option('toma_msp_suppress_icons');
	delete_option('welcome_message');
	
	
	// Delete the recent check transient
	delete_transient("msp_recently_checked_counts");
	
	// Delete all meta data and transients
	// We are going to have to loop all posts
	
	$args = array(
		'posts_per_page' => -1,
		'ignore_sticky_posts' => 1
	);
	
	$posts_in_range = new WP_Query( $args );
				
	// Run the loop
	while ( $posts_in_range->have_posts() ) : $posts_in_range->the_post();
		
		$transient_base = "msp_trans_" . get_the_ID() . "_";
		
		delete_transient($transient_base."_fb_likes");
		delete_transient($transient_base."_tweets");
		delete_transient($transient_base."_google_plus_ones");
		
		delete_post_meta(get_the_ID(), "_msp_fb_likes");
		delete_post_meta(get_the_ID(), "_msp_tweets");
		delete_post_meta(get_the_ID(), "_msp_google_plus_ones");
		delete_post_meta(get_the_ID(), "_msp_total_shares");
	
	endwhile;
	
	// Reset the post info after our loop
	// not sure I need this here
	wp_reset_postdata();
	
	// The transients would delete themselves, but
	// we delete them right away as it could be helpful
	// if a user is trying to uninstall and reinstall.
}

?>