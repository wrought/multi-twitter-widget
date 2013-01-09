=== Plugin Name ===
Contributors: msenate, clay.mcilrath
Donate link: http://www.plos.org/support-us/individual-membership/
Tags: widgets, twitter, multi twitter, multiple twitter, twitter account
Requires at least: 2.8
Tested up to: 3.5
Stable tag: trunk

Forks the Multi Twitter Stream plugin originally written by clay.mcilrath. A simple, updated widget that displays a most recent tweet from each account listed, as well as a number of tweets that result from a search (such as a hashtag). 

== Description ==
Have a team or group of tweeters that you'd like to show on a site? 

The problem with most Wordpress Twitter Plugins is that the few that support multiple twitter accounts usually show the tweets of the users in chronological order. This means if USER_A tweets more than USER_B your whole feed might be all about USER_A. I found in many cases that I'd rather pull in the most recent tweet from each user. So that's what this plugin does. It will also pull in search results and hashtags

## CHANGELOG
- 1.5 Adopts patches from msenate including using OO plugin format and readme cleanup
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
1. Upload the 'widget-twitter' folder to the /wp-content/plugins/ folder on your site
2. In WP-Admin, you should see 'Multi Twitter Widget' listed as an inactive plugin. Click the link to activate it. 
3. Once activated, go to Appearance > Widgets and Drop the Widget into your preferred sidebar
4. Once you've dropped in the widget, enter twitter handles (space separated), enter search terms (comma separated), or both. Click save.
