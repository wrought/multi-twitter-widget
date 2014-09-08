=== Plugin Name ===
Contributors: clay.mcilrath, roger_hamilton, msenate, cviebrock
Donate link: http://bitly.com/111ya6n
Tags: widgets, twitter, multi twitter, multiple twitter, twitter account
Requires at least: 2.8
Tested up to: 3.5.1
Stable tag: trunk

A simple widget that displays only the most recent tweet from multiple accounts.

== Description ==
Have a team or group of tweeters that you'd like to show on a site?
Want to follow a hashtag and mix in with tweets from one or more user accounts?
The problem with most Wordpress Twitter Plugins is that the few that support multiple twitter accounts
usually show the tweets of the users in chronological order. This means if USER_A tweets more than USER_B
your whole feed might be all about USER_A. I found in many cases that I'd rather pull in the most recent tweet
from each user. So that's what this plugin does. It will also pull in search results and hashtags

Another fork exists here: https://github.com/msenateatplos/multi-twitter-widget/blob/master/widget.php

## CHANGELOG
- 1.6.0 Colin added support for wpackagist installation, and translations (en by default, de_DE added)
- 1.5.1 Matt added all his patches, reworked to adopt Roger's OAuth, use OO, etc
- 1.5.0 Roger did an overhaul on the plugin supporting oath and other more modern conventions
- 1.4.3 Fixing the auto link conversion thanks to Roger
- 1.4.2 Code cleanup
- 1.4.1 Updating docs and links
- 1.4.0	Built in better cache handling, added options for credits and styles 
- 1.3.3	Added in Option to enable/disable dates, cleaned up logic
- 1.3.2	Using 2.8 deprecated tags due to lack of documentation
- 1.3.1	Updated to support 3.0
- 1.3.0	Added options for search terms in addition to accounts
- 1.2.5	Added options in the widget settings to link #hashtags and @replies.
- 1.2.0	Added option on widget to limit feed
- 1.1.0	Added chronological sorting for feeds
- 1.0.1	Re-arranged files get plugin working properly


== Installation ==
Estimated Time: 10 minutes

1. Upload the 'widget-twitter' folder to the /wp-content/plugins/ folder on your site
2. In WP-Admin, you should see 'Multi Twitter Widget' listed as an inactive plugin. Click the link to activate it. 
3. Once activated, go to Appearance > Widgets and Drop the Widget into your preferred sidebar
4. You will need oauth credentials for twitter, which means you'll have to create an app on https://dev.twitter.com/apps to get these credentials. Defaults have been provided, but there is no guarantee they will work long term.
5. Once you've dropped in the widget, enter twitter handles (space separated) and click save. 
