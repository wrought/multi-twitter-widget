<?php
/*
Plugin Name: Multi Twitter Stream
Plugin URI: https://github.com/msenateatplos/multi-twitter-widget
Description: A widget for multiple twitter accounts and keyword search
Author: Matt Senate, Clayton McIlrath
Version: 1.5.1
*/
 
/*
 * @TODO:
 * + Options for order arrangement (chrono, alpha, etc)
 * + Fix bugs with user and search term limits
 * + Change admin form to allow free text field (using intval() anyway to sanitize)
 */

function human_time($datefrom, $dateto = -1)
{
	// Defaults and assume if 0 is passed in that its an error rather than the epoch
	if ( $datefrom <= 0 )
		return "A long time ago";
		
	if ( $dateto == -1) { 
		$dateto = time(); 
	}
	
	// Calculate the difference in seconds betweeen the two timestamps
	$difference = $dateto - $datefrom;
	
	// If difference is less than 60 seconds use 'seconds'
	if ( $difference < 60 )
	{ 
		$interval = "s"; 
	}
	// If difference is between 60 seconds and 60 minutes use 'minutes'
	else if ( $difference >= 60 AND $difference < (60*60) )
	{
		$interval = "n"; 
	}
	// If difference is between 1 hour and 24 hours use 'hours'
	else if ( $difference >= (60*60) AND $difference < (60*60*24) )
	{
		$interval = "h"; 
	}
	// If difference is between 1 day and 7 days use 'days'
	else if ( $difference >= (60*60*24) AND $difference < (60*60*24*7) )
	{
		$interval = "d"; 
	}
	// If difference is between 1 week and 30 days use 'weeks'
	else if ( $difference >= (60*60*24*7) AND $difference < (60*60*24*30) )
	{
		$interval = "ww";
	}
	// If difference is between 30 days and 365 days use 'months'
	else if ( $difference >= (60*60*24*30) AND $difference < (60*60*24*365) )
	{
		$interval = "m"; 
	}
	// If difference is greater than or equal to 365 days use 'years'
	else if ( $difference >= (60*60*24*365) )
	{
		$interval = "y"; 
	}
	
	// Based on the interval, determine the number of units between the two dates
	// If the $datediff returned is 1, be sure to return the singular
	// of the unit, e.g. 'day' rather 'days'
	switch ($interval)
	{
		case "m" :
			$months_difference = floor($difference / 60 / 60 / 24 / 29);
			
			while(
				mktime(date("H", $datefrom), date("i", $datefrom),
				date("s", $datefrom), date("n", $datefrom)+($months_difference),
				date("j", $dateto), date("Y", $datefrom)) < $dateto)
			{
				$months_difference++;
			}
			$datediff = $months_difference;
	
			// We need this in here because it is possible to have an 'm' interval and a months
			// difference of 12 because we are using 29 days in a month
			if ( $datediff == 12 )
			{ 
				$datediff--; 
			}
	
			$res = ($datediff==1) ? "$datediff month ago" : "$datediff months ago";
			
			break;
	
		case "y" :
			$datediff = floor($difference / 60 / 60 / 24 / 365);
			$res = ($datediff==1) ? "$datediff year ago" : "$datediff years ago";

			break;
	
		case "d" :
			$datediff = floor($difference / 60 / 60 / 24);
			$res = ($datediff==1) ? "$datediff day ago" : "$datediff days ago";
			
			break;
	
		case "ww" :
			$datediff = floor($difference / 60 / 60 / 24 / 7);
			$res = ($datediff==1) ? "$datediff week ago" : "$datediff weeks ago";

			break;
	
		case "h" :
			$datediff = floor($difference / 60 / 60);
			$res = ($datediff==1) ? "$datediff hour ago" : "$datediff hours ago";

			break;
	
		case "n" :
			$datediff = floor($difference / 60);
			$res = ($datediff==1) ? "$datediff minute ago" : "$datediff minutes ago";
	
			break;
	
		case "s":
			$datediff = $difference;
			$res = ($datediff==1) ? "$datediff second ago" : "$datediff seconds ago";
			
			break;
	}

	return $res;
}



function format_tweet($tweet, $widget) 
{
	if ( $widget['reply'] )
	    $tweet = preg_replace('/(^|\s)@(\w+)/', '\1@<a href="http://www.twitter.com/\2">\2</a>', $tweet);
	
	if ( $widget['hash'] )
	    $tweet = preg_replace('/(^|\s)#(\w+)/', '\1#<a href="http://search.twitter.com/search?q=%23\2">\2</a>', $tweet);
	
	if( $widget['links'] )
		$tweet = preg_replace('#(^|[\n ])(([\w]+?://[\w\#$%&~.\-;:=,?@\[\]+]*)(/[\w\#$%&~/.\-;:=,?@\[\]+]*)?)#is', '\1<a href="\2">\2</a>', $tweet);
	
	return $tweet;
}

	
function feed_sort($a, $b)
{
	if ( $a->status->created_at )
	{
		$a_t = strtotime($a->status->created_at);
		$b_t = strtotime($b->status->created_at);
	}
	else if ( $a->updated ) 
	{
		$a_t = strtotime($a->updated);
		$b_t = strtotime($b->updated);
	}
	
	if ( $a_t == $b_t ) 
		return 0 ;

    return ($a_t > $b_t ) ? -1 : 1; 
}

function multi_twitter($widget) 
{
	// Create our HTML output var to return
	$output = ''; 
	
	// Get our root upload directory and create cache if necessary
	$upload = wp_upload_dir();
	$upload_dir = $upload['basedir']."/cache";
	
	if ( ! file_exists($upload_dir) )
	{
		if ( ! mkdir($upload_dir) )
		{
			$output .= '<span style="color: red;">could not create dir'.$upload_dir.' please create this directory</span>';

			return $output;
		}
	}
	
	$accounts = explode(" ", $widget['users']);
	$terms = explode(", ", $widget['terms']);
	
	if ( ! $widget['user_limit'] )
	{
		$widget['user_limit'] = 5; 
	} 
	
	if ( ! $widget['term_limit'] )
	{ 
		$widget['term_limit'] = 5; 
	}
	if ( ! $widget['result_limit'] )
	{
		$widget['result_limit'] = 2;
	}
	
	$output .= '<ul class="multi-twitter">';
		
	// Parse the accounts and CRUD cache
	foreach ( $accounts as $account )
	{
		$cache = false; // Assume the cache is empty
		$cFile = "$upload_dir/users_$account.xml";
	
		if ( file_exists($cFile) ) 
		{
			$modtime = filemtime($cFile);		
			$timeago = time() - 1800; // 30 minutes ago
			if ( $modtime < $timeago )
			{
				// Set to false just in case as the cache needs to be renewed
				$cache = false; 
			} 
			else 
			{
				// The cache is not too old so the cache can be used.
				$cache = true; 
			}
		}
		
		if ( $cache === false ) 
		{
			// curl the account via XML to get the last tweet and user data
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, "http://twitter.com/users/$account.xml");
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$content = curl_exec($ch);
			curl_close($ch);
			
			// Create an XML object from curl'ed content
			if ( ! $content === false )
			{
				$xml = new SimpleXMLElement($content);
				$feeds[] = $xml;
		
				// Let's save our data into uploads/cache/
				$fp = fopen($cFile, 'w');
				if ( ! $fp )
				{
					$output .= '<li style="color: red;">Permission to write cache dir to <em>'.$cFile.'</em> not granted</li>';
				}
				else 
				{
					fwrite($fp, $content);
				}
				fclose($fp);
			}
			else
			{
				// Content couldn't be retrieved... failing silently for now
			}
		} 
		else
		{
			//cache is true let's load the data from the cached file
			$xml = simplexml_load_file($cFile);
			$feeds[] = $xml;
		}
	}
	
	// Parse the terms and CRUD cache
	foreach ( $terms as $term )
	{
		$cache = false; // Assume the cache is empty
		$cFile = "$upload_dir/term_$term.xml";
	
		if ( file_exists($cFile) )
		{
			$modtime = filemtime($cFile);
			$timeago = time() - 1800; // 30 minutes ago
			if ( $modtime < $timeago )
			{
				// Set to false just in case as the cache needs to be renewed
				$cache = false; 
			}
			else
			{
				// The cache is not too old so the cache can be used.
				$cache = true; 
			}
		}
		
		if ( $cache === false ) 
		{				
			// curl the account via XML to get the last tweet and user data
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, "http://search.twitter.com/search.atom?q=$term");
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$content = curl_exec($ch);
			curl_close($ch);
			
			// Create an XML object from curl'ed content
			$xml = new SimpleXMLElement($content);
			$feeds[] = $xml;
			
			if ( ! $content === false )
			{
				// Let's save our data into uploads/cache/twitter/
				$fp = fopen($cFile, 'w');
				if ( ! $fp )
				{
					 $output .= '<li style="color: red;">Permission to write cache dir to <em>'.$cFile.'</em> not granted</li>';
				} 
				else 
				{ 
					fwrite($fp, $content); 
				}
				fclose($fp);
			}
			else
			{
				// Content couldn't be retrieved... failing silently for now
			}
		} 
		else 
		{
			//cache is true let's load the data from the cached file
			$xml = simplexml_load_file($cFile);
			$feeds[] = $xml;
		}
	}
	
	// Sort our $feeds array
	//usort($feeds, "feed_sort");
	
	// Split array and output results
	$sn_index = 0;
	$term_index = 0;
	
	foreach ( $feeds as $feed )
	{
		if ( $feed->screen_name != '' AND $sn_index <= $widget['user_limit'] )
		{
			$output .= 
				'<li class="clearfix">'.
					'<a href="http://twitter.com/'.$feed->screen_name.'">'.
						'<img class="twitter-avatar" src="'
						.$feed->profile_image_url
						.'" width="40" height="40" alt="'
						.$feed->screen_name.'" />'
						.$feed->screen_name
						.':</a> '
						.format_tweet($feed->status->text, $widget)
						.'<br />';
						
			if ( $widget['date'] )
			{ 
				$output .= '<em>'.human_time(strtotime($feed->status->created_at)).'</em>'; 
			}
			
			$output .= '</li>';
		}
		$sn_index++;
		if ( preg_match('/search.twitter.com/i', $feed->id) AND $term_index <= $widget['term_limit'] )
		{
			$count = count($feed->entry);
			
			for ( $i=0; $i<$count; $i++ )
			{
				if ( $i < $widget['result_limit'] )
				{
					$output .= 
						'<li class="clearfix">'.
							'<a href="'.$feed->entry[$i]->author->uri.'">'.
								'<img class="twitter-avatar" '.
									'src="'.$feed->entry[$i]->link[1]->attributes()->href.'" '.
									'width="40" height="40" '.
									'alt="'.$feed->entry[$i]->author->name.'" />'.
								$feed->entry[$i]->author->name.
							'</a>: '.
							format_tweet($feed->entry[$i]->content, $widget).
							'<br />';
							
					if ( $widget['date'] )
					{ 
						$output .= '<em>'.human_time(strtotime($feed->updated)).'</em>'; 
					}
					$output .= '</li>';
				}
			}
		}
		$term_index++;
	}
	
	$output .= '</ul>';
	
	if ( $widget['credits'] == true )
	{
		$output .= 
			'<hr />'.
			'<strong>powered by</strong> '.
			'<a href="http://incbrite.com/services/wordpress-plugins" target="_blank">Incbrite Wordpress Plugins</a>';
	}
	
	if ( $widget['styles'] == true )
	{
		$output .= 
			'<style type="text/css">'.
			'.twitter-avatar { clear: both; float: left; padding: 6px 12px 2px 0; }'.
			'</style>';
	}	
	
	echo $output;
}

/*
 * Attempts to implement extension of WP_Widget class
 */


class multiTwitterWidget extends WP_Widget {
	function multiTwitterWidget() {
		// Instantiate parent object
		parent::__construct(
			'multi_twitter',
			'Multi Twitter',
			array( 'description' => 'Widget to display tweets from multiple twitter accounts')
		);
	}

	function widget( $args, $instance ) {
		// Widget output
		extract($args);

		echo $before_widget;
		echo $before_title;
		echo $instance['title'];
		echo $after_title;

		multi_twitter($instance);
		
		echo $after_widget;
		
	}

	function update( $new_instance, $old_instance ) {
		// Save widget options
		$new_instance = (array) $new_instance;
		$instance = array( 'hash' => 0, 'reply' => 0, 'links' => 0, 'date' => 0, 'credits' => 0, 'styles' => 0 );
		// Set values
		foreach ( $instance as $field => $val ) {
			if (isset($new_instance[$field]) )
				$instance[$field] = 1; // loop through booleans
		}
		$instance['title'] = htmlspecialchars($new_instance['title']);
		$instance['users'] = htmlspecialchars($new_instance['users']);
		$instance['terms'] = htmlspecialchars($new_instance['terms']);
		$instance['user_limit'] = intval($new_instance['user_limit']);
		$instance['term_limit'] = intval($new_instance['term_limit']);
		$instance['result_limit'] = intval($new_instance['result_limit']);

		// Return
		return $instance;

	}

	function form( $instance ) {
		// Default values (if key is not set, use default values below)
		$instance = wp_parse_args( (array) $instance, array( 'title' => 'Multi Twitter', 'users' => 'wrought thinkclay', 'terms' => 'wordpress, plos', 'user_limit' => 5, 'term_limit' => 5, 'result_limit' => 2, 'links' => 1, 'reply' => 1, 'hash' => 1, 'date' => 1, 'credits' => 1, 'styles' => 1 ) );
		// Output admin widget options form
		?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>">Widget Title: </label><br />
			<input type="text" class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo $instance['title']; ?>" />
		</p>
		<p>	
			<label for="<?php echo $this->get_field_id('users'); ?>">Users: </label><br />
			<input type="text" class="widefat" id="<?php echo $this->get_field_id('users'); ?>" name="<?php echo $this->get_field_name('users'); ?>" value="<?php echo $instance['users']; ?>" /><br />
			<small><em>enter accounts separated with a space</em></small>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('terms'); ?>">Search Terms: </label><br />
			<input type="text" class="widefat" id="<?php echo $this->get_field_id('terms'); ?>" name="<?php echo $this->get_field_name('terms'); ?>" value="<?php echo $instance['terms']; ?>" /><br />
			<small><em>enter search terms separated with a comma</em></small>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('user_limit'); ?>">Limit user feed to: </label>
			<select id="<?php echo $this->get_field_id('user_limit'); ?>" name="<?php echo $this->get_field_name('user_limit'); ?>">				<option value="<?php echo $instance['user_limit']; ?>"><?php echo $instance['user_limit']; ?></option>
				<option value="1">1</option>
				<option value="2">2</option>
				<option value="3">3</option>
				<option value="4">4</option>
				<option value="5">5</option>
				<option value="6">6</option>
				<option value="7">7</option>
				<option value="8">8</option>
				<option value="9">9</option>
				<option value="10">10</option>
			</select>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('term_limit'); ?>">Limit search terms to: </label>
			<select id="<?php echo $this->get_field_id('term_limit'); ?>" name="<?php echo $this->get_field_name('term_limit'); ?>">
				<option value="<?php echo $instance['term_limit']; ?>"><?php echo $instance['term_limit']; ?></option>
				<option value="1">1</option>
				<option value="2">2</option>
				<option value="3">3</option>
				<option value="4">4</option>
				<option value="5">5</option>
				<option value="6">6</option>
				<option value="7">7</option>
				<option value="8">8</option>
				<option value="9">9</option>
				<option value="10">10</option>
			</select>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('result_limit'); ?>">Limit search results to: </label>
			<select id="<?php echo $this->get_field_id('result_limit'); ?>" name="<?php echo $this->get_field_name('result_limit'); ?>">
				<option value="<?php echo $instance['result_limit']; ?>"><?php echo $instance['result_limit']; ?></option>
				<option value="1">1</option>
				<option value="2">2</option>
				<option value="3">3</option>
				<option value="4">4</option>
				<option value="5">5</option>
				<option value="6">6</option>
				<option value="7">7</option>
				<option value="8">8</option>
				<option value="9">9</option>
				<option value="10">10</option>
			</select>
		<br />
		<small><em>Limits number of results for each term.</em></small>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('links'); ?>">Automatically convert links?</label>
			<input type="checkbox" name="<?php echo $this->get_field_name('links'); ?>" id="<?php echo $this->get_field_id('links'); ?>" <?php if ($instance['links']) echo 'checked="checked"'; ?> />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('reply'); ?>">Automatically convert @replies?</label>
			<input type="checkbox" name="<?php echo $this->get_field_name('reply'); ?>" id="<?php echo $this->get_field_id('reply'); ?>" <?php if ($instance['reply']) echo 'checked="checked"'; ?> />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('hash'); ?>">Automatically convert #hashtags?</label>
			<input type="checkbox" name="<?php echo $this->get_field_name('hash'); ?>" id="<?php echo $this->get_field_id('hash'); ?>" <?php if ($instance['hash']) echo 'checked="checked"'; ?> />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('date'); ?>">Show Date?</label>
			<input type="checkbox" name="<?php echo $this->get_field_name('date'); ?>" id="<?php echo $this->get_field_id('date'); ?>" <?php if ($instance['date']) echo 'checked="checked"'; ?> />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('credits'); ?>">Show Credits?</label>
			<input type="checkbox" name="<?php echo $this->get_field_name('credits'); ?>" id="<?php echo $this->get_field_id('credits'); ?>" <?php if ($instance['credits']) echo 'checked="checked"'; ?> />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('styles'); ?>">Use Default Styles?</label>
			<input type="checkbox" name="<?php echo $this->get_field_name('styles'); ?>" id="<?php echo $this->get_field_id('styles'); ?>" <?php if ($instance['styles']) echo 'checked="checked"'; ?> />
		</p>
		<?php
	}
}

function multi_twitter_init() 
{
	register_widget( "multiTwitterWidget" );
}

add_action("widgets_init", "multi_twitter_init");
?>
