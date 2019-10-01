=== Auto Refresh API AJAX ===
Contributors: berkux
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=APWXWK3DF2E22
Tags:liveupdate,liveticker,content,json,api,ajax
Requires at least: 3.0
Tested up to: 5.2.2
Requires PHP: 5.3.0
Stable tag: 1.2.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Plugin to load Content via a JSON-API, display it on a Wordpress-Page, -Post or -Sidebar and auto-refresh it without reoloading. Useful e. g. for Liveticker.

== Description ==

= Plugin to load Content via a JSON-API, display it on a Wordpress-Page, -Post or -Sidebar and auto-refresh it without reoloading =
If you want to show content from an JSON-feed which is updating often (e.g. Liveticker, Time...) this plugin helps.
* define the URL to the JSON-data (must be accessible without authorization via http-get - if this is not the case you can proxy that with my plugin JSON Content Importer
* define a secret key, if the URL is not on the same server to handle the "same origin policy" 
* define how often Ajax should update the data
* define where to put the data on a paghe by inserting a jQuery-DOM-selector 
* define the inital value 

[youtube https://www.youtube.com/watch?v=mzQLX8xkfOU]

 == Frequently Asked Questions ==

[Example and explanation](https://json-content-importer.com/auto-refresh-api-ajax/ "Example and explanation").

= API with authorization =
If the API has authorization you can handle this withe the plugin [JSON Content Importer](https://json-content-importer.com "JSON Content Importer"]: With that you can access to almost any API and build a JSON-feed out of the API-JSON as you like.

== Installation ==
For detailed installation instructions, please read the [standard installation procedure for WordPress plugins](http://codex.wordpress.org/Managing_Plugins#Installing_Plugins).

1. Login to your WordPress installation
2. Install plugin by uploading the plugins to `/wp-content/plugins/`.
3. Activate the plugin through the _Plugins_ menu.
4. Klick on "Auto Refresh AA" menuentry in the left bar and set the info for what, how often, where...


= Don't forget: =
[Donate whatever this plugin is worth for you](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=APWXWK3DF2E22)

= Where is this plugin from? =
This plugin is made in munich, bavaria, germany!
Famous for Oktoberfest, FC Bayern Munich, AllianzArena, DLD, TUM, BMW, Siemens, seas, mountains and much more...

== Screenshots ==

1. Settings of the plugin

== Changelog ==

= 1.2.1 =
Minor Bugfix: Missing function added 

= 1.2.0 =
Added setting of inital values: E. g. hide box with content until the content is loaded or load content with the server first before the client 

= 1.1.0 =
Added a 2nd way to proxy URLs for AJax-Calls

= 1.0.0 =
* Initial release

== Upgrade Notice ==
Version 1.2.0: 
* Added setting of inital values: E. g. hide box with content until the content is loaded or load content with the server first before the client 