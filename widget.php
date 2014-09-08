<?php
/*
Plugin Name: Multi Twitter Stream
Plugin URI: https://github.com/msenateatplos/multi-twitter-widget
Description: A widget for multiple twitter accounts and keyword search
Author: Clayton McIlrath, Roger Hamilton, Matt Senate
Version: 1.5.1
*/

/*
 * @TODO:
 * + Options for order arrangement (chrono, alpha, etc)
 * + Change admin form to allow free text field (using intval() anyway to sanitize)
 * + Remove in-line styles, create custom style sheet and enqueue w/ WP
 */

function human_time($datefrom, $dateto = -1)
{
	// Defaults and assume if 0 is passed in that its an error rather than the epoch
	if ( $datefrom <= 0 )
		return  __('A long time ago', 'multi-twitter-widget');

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

			$res = sprintf(
				($datediff==1) ?
					__('%d month ago', 'multi-twitter-widget') :
					__('%d months ago', 'multi-twitter-widget') ,
				$datediff
			);

			break;

		case "y" :
			$datediff = floor($difference / 60 / 60 / 24 / 365);

			$res = sprintf(
				($datediff==1) ?
					__('%d year ago', 'multi-twitter-widget') :
					__('%d years ago', 'multi-twitter-widget') ,
				$datediff
			);
			break;

		case "d" :
			$datediff = floor($difference / 60 / 60 / 24);
			$res = sprintf(
				($datediff==1) ?
					__('%d day ago', 'multi-twitter-widget') :
					__('%d days ago', 'multi-twitter-widget') ,
				$datediff
			);
			break;

		case "ww" :
			$datediff = floor($difference / 60 / 60 / 24 / 7);
			$res = sprintf(
				($datediff==1) ?
					__('%d week ago', 'multi-twitter-widget') :
					__('%d weeks ago', 'multi-twitter-widget') ,
				$datediff
			);
			break;

		case "h" :
			$datediff = floor($difference / 60 / 60);
			$res = sprintf(
				($datediff==1) ?
					__('%d hour ago', 'multi-twitter-widget') :
					__('%d hours ago', 'multi-twitter-widget') ,
				$datediff
			);
			break;

		case "n" :
			$datediff = floor($difference / 60);
			$res = sprintf(
				($datediff==1) ?
					__('%d minute ago', 'multi-twitter-widget') :
					__('%d minutes ago', 'multi-twitter-widget') ,
				$datediff
			);
			break;

		case "s":
			$datediff = $difference;
			$res = sprintf(
				($datediff==1) ?
					__('%d second ago', 'multi-twitter-widget') :
					__('%d seconds ago', 'multi-twitter-widget') ,
				$datediff
			);
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
	if ( $a['created_at'] )
	{
		$a_t = strtotime($a['created_at']);
		$b_t = strtotime($b['created_at']);
	}
	else if ( $a['updated'] )
	{
		$a_t = strtotime($a['updated']);
		$b_t = strtotime($b['updated']);
	}

	if ($a_t == $b_t)
		return 0;

	return ($a_t > $b_t) ? -1 : 1;
}


function multi_twitter($widget)
{
	if ( ! class_exists('Codebird') )
		require 'lib/codebird.php';

	// Initialize Codebird with our keys.  We'll wait and
	// pass the token when we make an actual request
	Codebird::setConsumerKey($widget['consumer_key'], $widget['consumer_secret']);

	$cb = Codebird::getInstance();
	$output = '';

	// Get our root upload directory and create cache if necessary
	$upload     = wp_upload_dir();
	$upload_dir = $upload['basedir'] . "/cache";

	if ( ! file_exists($upload_dir) )
	{
		if ( ! mkdir($upload_dir))
		{
			$output .= '<span style="color: red;">' . sprintf(
				__('Could not create dir "%s"; please create this directory.', 'multi-twitter-widget') ,
				$upload_dir
			) . '</span>';

			return $output;
		}
	}

	// split the accounts and search terms specified in the widget
	$accounts = explode(" ", $widget['users']);
	$terms    = explode(", ", $widget['terms']);

	$output .= '<ul class="multi-twitter">';

	// Parse the accounts and CRUD cache

	$feeds = null;

	foreach ( $accounts as $account )
	{
	  if ( $account != "" )
	  {
		$cache = false;
		// Assume the cache is empty
		$cFile = "$upload_dir/users_$account.txt";

		if ( file_exists($cFile) )
		{
			$modtime = filemtime($cFile);
			$timeago = time() - 1800; // 30 minutes ago

			// Check if length is less than new limit
			$str     = file_get_contents($cFile);
			$content = unserialize($str);
			$length = count($content);

			if ( $modtime < $timeago or $length != $widget['user_limit'] )
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

		// begin
		if ( $cache === false )
		{
			$cb->setToken($widget['access_token'], $widget['access_token_secret']);
			$params  = array('screen_name' => $account, 'count' => $widget['user_limit']);
			// let Codebird make an authenticated request  Result is json
			$reply   = $cb->statuses_userTimeline($params);
			// turn the json into an array
			$json    = json_decode($reply, true);
			$length = count($json);

			for ($i = 0; $i < $length; $i++)
			{
				// add it to the feeds array
				$feeds[] = $json[$i];
				// prepare it for caching
				$content[] = $json[$i];
			}

			// Let's save our data into uploads/cache/
			$fp = fopen($cFile, 'w');

			if (!$fp)
			{
				$output .= '<li style="color: red;">' . sprintf(
					__('Permission to write cache dir to <em>%s</em> not granted.', 'multi-twitter-widget') ,
					$cFile
				) . '</li>';

			}
			else
			{
				$str = serialize($content);
				fwrite($fp, $str);
			}
			fclose($fp);
		$content = null;
		}
		else
		{
			//cache is true let's load the data from the cached file
			$str     = file_get_contents($cFile);
			$content = unserialize($str);
			$length = count($content);
			// echo $length;

			for ($i = 0; $i < $length; $i++)
			{
				// add it to the feeds array
				$feeds[] = $content[$i];
			}
		}
	  } // end account empty check
	} // end accounts foreach

	// Parse the terms and CRUD cache
	foreach ( $terms as $term )
	{
	  if ( $term != "" )
	  {
		$cache = false;
		// Assume the cache is empty
		$cFile = "$upload_dir/term_$term.txt";

		if (file_exists($cFile))
		{
			$modtime = filemtime($cFile);
			$timeago = time() - 1800; // 30 minutes ago

			// Check if length is less than new limit
			$str     = file_get_contents($cFile);
			$content = unserialize($str);
			$length = count($content);

			if ( $modtime < $timeago or $length != $widget['term_limit'] )
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
			$cb->setToken($widget['access_token'], $widget['access_token_secret']);
			$search_params = array(
				'q' => $term,
		'count' => $widget['term_limit']
			);
			$reply         = $cb->search_tweets($search_params);
			$json          = json_decode($reply, true);
			$length = count($json['statuses']);

			for ( $i = 0; $i < $length; $i++ )
			{
				// add it to the feeds array
				$feeds[] = $json['statuses'][$i];
				// prepare it for caching
				$content[] = $json['statuses'][$i];
			}

			if ($content === false)
			{
				// Content couldn't be retrieved... Do something..
				$output .= '<li>' . __('Content could not be retrieved; Twitter API failed.', 'multi-twitter-widget') . '</li>';

			}
			else
			{
				// Let's save our data into uploads/cache/
				$fp = fopen($cFile, 'w');

				if (!$fp)
				{
					$output .= '<li style="color: red;">' . sprintf(
						__('Permission to write cache dir to <em>%s</em> not granted.', 'multi-twitter-widget') ,
						$cFile
					) . '</li>';
				}
				else
				{
					fwrite($fp, serialize($content));
				}
				fclose($fp);
				$content = null;
			}
		}
		else
		{
			//cache is true let's load the data from the cached file
			$str     = file_get_contents($cFile);
			$content = unserialize($str);
			$length  = count($content);

			for ($i = 0; $i < $length; $i++)
			{
				// add it to the feeds array
				$feeds[] = $content[$i];
			}
		}
	  } // end terms empty check
	} // end terms foreach

	// Sort our $feeds array
	if ( $widget['sort_by_date'] )
	{
	usort($feeds, "feed_sort");
	}

	// Split array and output results
	$i = 1;

	// format the tweet for display
	foreach ( $feeds as $feed )
	{
		if ( ! empty($feed) )
		{
			$output .= '<li class="tweet clearfix" style="margin-bottom:8px;">' . '<a href="http://twitter.com/' . $feed['user']['screen_name'] . '">' . '<img class="twitter-avatar" src="' . $feed['user']['profile_image_url'] . '" width="40" height="40" alt="' . $feed['user']['screen_name'] . '" />' . '</a>';
			$output .= '<span class="tweet-userName">' . $feed['user']['name'] . '</span>';
			if ($widget['date'])
			{
				$output .= '<span class="tweet-time">' . human_time(strtotime($feed['created_at'])) . '</span>';
			}
			$output .= '<span class="tweet-message">' . format_tweet($feed['text'], $widget) . '</span>';

			$output .= '</li>';
		}
	}
	$output .= '</ul>';

	if ($widget['credits'] === true)
	{
		$output .= '<hr /><strong>' . __('Development by', 'multi-twitter-widget') . '</strong> ' .
			'<a href="http://twitter.com/thinkclay" target="_blank">Clay McIlrath</a>, ' .
			'<a href="http://twitter.com/roger_hamilton" target="_blank">Roger Hamilton</a>, ' .
			'<a href="http://twitter.com/wrought/" target="_blank">Matt Senate</a>, and ' .
			'<a href="http://twitter.com/cviebrock/" target="_blank">Colin Viebrock</a>.';
	}

	if ($widget['styles'] === true)
	{
		$output .= '<style type="text/css">' . '.twitter-avatar { clear: both; float: left; padding: 6px 12px 2px 0; }' . '.twitter{background:none;}' . '.tweet{min-height:48px;margin:0!important;}' . '.tweet a{text-decoration:underline;}' . '.tweet-userName{padding-top:7px;font-size:12px;line-height:0;color:#454545;font-family:Arial,sans-serif;font-weight:700;margin-bottom:10px;margin-left:8px;float:left;min-width:50px;}' . '.twitter-avatar{width:48px;height:48px;-webkit-border-radius:5px;-moz-border-radius:5px;border-radius:5px;padding:0!important;}' . '.tweet-time{color:#8A8A8A;float:left;margin-top:-3px;font-size:11px!important;}' . '.tweet-message{font-size:11px;line-height:14px;color:#333;font-family:Arial,sans-serif;word-wrap:break-word;margin-top:-30px!important;width:200px;margin-left:58px;}' . '</style>';
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
			array( 'description' => __('Widget to display tweets from multiple twitter accounts', 'multi-twitter-widget') )
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
		$instance = array( 'hash' => 0, 'reply' => 0, 'links' => 0, 'date' => 0, 'sort_by_date' => 0, 'credits' => 0, 'styles' => 0 );
		// Set values
		foreach ( $instance as $field => $val ) {
			if (isset($new_instance[$field]) )
				$instance[$field] = 1; // loop through booleans
		}
		$instance['consumer_key'] = htmlspecialchars($new_instance['consumer_key']);
		$instance['consumer_secret'] = htmlspecialchars($new_instance['consumer_secret']);
		$instance['access_token'] = htmlspecialchars($new_instance['access_token']);
		$instance['access_token_secret'] = htmlspecialchars($new_instance['access_token_secret']);
		$instance['title'] = htmlspecialchars($new_instance['title']);
		$instance['users'] = htmlspecialchars($new_instance['users']);
		$instance['terms'] = htmlspecialchars($new_instance['terms']);
		$instance['user_limit'] = intval($new_instance['user_limit']);
		$instance['term_limit'] = intval($new_instance['term_limit']);

		// Return
		return $instance;

	}

	function form( $instance ) {
		// Default values (if key is not set, use default values below)
		$instance = wp_parse_args( (array) $instance, array( 'consumer_key' => 'lNbFfDyYdUdZPhqqlsAGVA', 'consumer_secret' => 'izPPTeFQYlC2UFsxxu6ODZtoOC6FFyPZyXX959p4Z4', 'access_token' => '16468552-NUSSANz4tgU7gUCZPHMWxSdgatO10YtG1SrBuYUAA', 'access_token_secret' => 'SZJZIJ0jdRKa9EQ9T6JpamxOz28GDWuvDl7tydwzHIQ', 'title' => 'Multi Twitter', 'users' => 'thinkclay roger_hamilton wrought', 'terms' => 'wordpress, plos', 'user_limit' => 1, 'term_limit' => 1, 'links' => 1, 'reply' => 1, 'hash' => 1, 'date' => 1, 'sort_by_date' => 1, 'credits' => 0, 'styles' => 1 ) );
		// Output admin widget options form
		?>
	<fieldset style="border:1px solid gray;padding:10px;">
	<legend><?php echo __('Oauth Settings', 'multi-twitter-widget'); ?></legend>
	<p>
	<label for="<?php echo $this->get_field_id('consumer_key'); ?>"><?php echo __('Consumer Key:', 'multi-twitter-widget'); ?> </label><br />
	<input type="text" class="widefat" id="<?php echo $this->get_field_id('consumer_key'); ?>" name="<?php echo $this->get_field_name('consumer_key'); ?>" value="<?php echo $instance['consumer_key']; ?>" />
	</p>
	<p>
	<label for="<?php echo $this->get_field_id('consumer_secret'); ?>"><?php echo __('Consumer Secret:', 'multi-twitter-widget'); ?> </label><br />
	<input type="text" class="widefat" id="<?php echo $this->get_field_id('consumer_secret'); ?>" name="<?php echo $this->get_field_name('consumer_secret'); ?>" value="<?php echo $instance['consumer_secret']; ?>" />
	</p>
	<p>
	<label for="<?php echo $this->get_field_id('access_token'); ?>"><?php echo __('Access Token:', 'multi-twitter-widget'); ?> </label><br />
	<input type="text" class="widefat" id="<?php echo $this->get_field_id('access_token'); ?>" name="<?php echo $this->get_field_name('access_token'); ?>" value="<?php echo $instance['access_token']; ?>" />
	</p>
	<p>
	<label for="<?php echo $this->get_field_id('access_token_secret'); ?>"><?php echo __('Access Token Secret:', 'multi-twitter-widget'); ?> </label><br />
	<input type="text" class="widefat" id="<?php echo $this->get_field_id('access_token_secret'); ?>" name="<?php echo $this->get_field_name('access_token_secret'); ?>" value="<?php echo $instance['access_token_secret']; ?>" />
	</p>
	</fieldset>
	<p>
	<label for="<?php echo $this->get_field_id('title'); ?>"><?php echo __('Widget Title:', 'multi-twitter-widget'); ?> </label><br />
	<input type="text" class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo $instance['title']; ?>" />
	</p>
	<p>
	<label for="<?php echo $this->get_field_id('users'); ?>"><?php echo __('Users:', 'multi-twitter-widget'); ?> </label><br />
	<input type="text" class="widefat" id="<?php echo $this->get_field_id('users'); ?>" name="<?php echo $this->get_field_name('users'); ?>" value="<?php echo $instance['users']; ?>" /><br />
	<small><em>enter accounts separated with a space</em></small>
	</p>
	<p>
	<label for="<?php echo $this->get_field_id('terms'); ?>"><?php echo __('Search Terms:', 'multi-twitter-widget'); ?> </label><br />
	<input type="text" class="widefat" id="<?php echo $this->get_field_id('terms'); ?>" name="<?php echo $this->get_field_name('terms'); ?>" value="<?php echo $instance['terms']; ?>" /><br />
	<small><em>enter search terms separated with a comma</em></small>
	</p>
	<p>
	<label for="<?php echo $this->get_field_id('user_limit'); ?>"><?php echo __('Limit user feed to:', 'multi-twitter-widget'); ?> </label>
	<select id="<?php echo $this->get_field_id('user_limit'); ?>" name="<?php echo $this->get_field_name('user_limit'); ?>">
		<option value="<?php echo $instance['user_limit']; ?>"><?php echo $instance['user_limit']; ?></option>
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
		<small><em><?php echo __('Limits number of results for each user.', 'multi-twitter-widget'); ?></em></small>
	</p>
	<p>
	<label for="<?php echo $this->get_field_id('term_limit'); ?>"><?php echo __('Limit search terms to:', 'multi-twitter-widget'); ?> </label>
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
		<br />
		<small><em><?php echo __('Limits number of results for each term.', 'multi-twitter-widget'); ?></em></small>
	</p>
	<p>
	<label for="<?php echo $this->get_field_id('links'); ?>"><?php echo __('Automatically convert links?', 'multi-twitter-widget'); ?></label>
	<input type="checkbox" name="<?php echo $this->get_field_name('links'); ?>" id="<?php echo $this->get_field_id('links'); ?>" <?php if ($instance['links']) echo 'checked="checked"'; ?> />
	</p>
	<p>
	<label for="<?php echo $this->get_field_id('reply'); ?>"><?php echo __('Automatically convert @replies?', 'multi-twitter-widget'); ?></label>
	<input type="checkbox" name="<?php echo $this->get_field_name('reply'); ?>" id="<?php echo $this->get_field_id('reply'); ?>" <?php if ($instance['reply']) echo 'checked="checked"'; ?> />
	</p>
	<p>
	<label for="<?php echo $this->get_field_id('hash'); ?>"><?php echo __('Automatically convert #hashtags?', 'multi-twitter-widget'); ?></label>
	<input type="checkbox" name="<?php echo $this->get_field_name('hash'); ?>" id="<?php echo $this->get_field_id('hash'); ?>" <?php if ($instance['hash']) echo 'checked="checked"'; ?> />
	</p>
	<p>
	<label for="<?php echo $this->get_field_id('date'); ?>"><?php echo __('Show Date?', 'multi-twitter-widget'); ?></label>
	<input type="checkbox" name="<?php echo $this->get_field_name('date'); ?>" id="<?php echo $this->get_field_id('date'); ?>" <?php if ($instance['date']) echo 'checked="checked"'; ?> />
	</p>
	<p>
	<label for="<?php echo $this->get_field_id('sort_by_date'); ?>"><?php echo __('Sort by Date?', 'multi-twitter-widget'); ?></label>
	<input type="checkbox" name="<?php echo $this->get_field_name('sort_by_date'); ?>" id="<?php echo $this->get_field_id('sort_by_date'); ?>" <?php if ($instance['sort_by_date']) echo 'checked="checked"'; ?> />
	</p>
	<p>
	<label for="<?php echo $this->get_field_id('credits'); ?>"><?php echo __('Show Credits?', 'multi-twitter-widget'); ?></label>
	<input type="checkbox" name="<?php echo $this->get_field_name('credits'); ?>" id="<?php echo $this->get_field_id('credits'); ?>" <?php if ($instance['credits']) echo 'checked="checked"'; ?> />
	</p>
	<p>
	<label for="<?php echo $this->get_field_id('styles'); ?>"><?php echo __('Use Default Styles?', 'multi-twitter-widget'); ?></label>
	<input type="checkbox" name="<?php echo $this->get_field_name('styles'); ?>" id="<?php echo $this->get_field_id('styles'); ?>" <?php if ($instance['styles']) echo 'checked="checked"'; ?> />
	</p>
<?php
	}
}

function multi_twitter_init()
{
	load_plugin_textdomain('multi-twitter-widget', false, basename( dirname( __FILE__ ) ) . '/lang' );
	register_widget( "multiTwitterWidget" );
}

add_action("widgets_init", "multi_twitter_init");
?>
