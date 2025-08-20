=== GameServerlistMonR – Game Server List & Monitoring for WordPress ===
Contributors: yamiru
Tags: game servers, server status, discord, monitoring, games
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Beautiful, minimalistic game server monitoring plugin for WordPress with real-time status updates. Monitor your game servers, Discord communities, and voice servers directly from your WordPress site.

== Description ==

GameServerlistMonR is a lightweight and responsive plugin that lets you display the live status of your game servers, Discord communities, and voice servers. With support for more than 200 games and built-in Discord integration, you can easily show your community activity directly on your WordPress site.

= Features =

* Real-time monitoring with auto-refresh
* 200+ supported games (Minecraft, CS2, Rust, etc.)
* Discord server integration
* 5 modern themes (Modern, Dark, Light, Glass, Neon)
* Fully responsive on all devices
* Lightweight and fast with caching
* Works out of the box – no config required

== Installation ==

1. Download the latest release from [GitHub](https://github.com/Yamiru/WP-GameServerlistMonR/releases).
2. Upload the folder to `/wp-content/plugins/`.
3. Activate the plugin through the "Plugins" menu in WordPress.
4. Navigate to **Game Servers** in the WordPress admin dashboard.

== Usage ==

* Show a single server:
  `[game_server id="1"]`

* Show all servers:
  `[monr_list]`

* Show specific type (e.g., Minecraft with dark theme):
  `[monr_list type="minecraft" theme="dark"]`

= Shortcode Parameters =

**[monr_list]**
* `type` - Filter by game type  
* `theme` - Theme style (modern/dark/light/glass/neon)  
* `show_offline` - Show offline servers (yes/no)  
* `show_powered` - Show powered by link (yes/no)  

**[game_server]**
* `id` - Server ID (required)  
* `theme` - Override theme for single server  

== Frequently Asked Questions ==

= Why does my server show as offline? =
1. Check if the ports are open.  
2. Verify the server type is correct.  
3. Check query port settings.  
4. Test with a manual query tool.  

= Discord server not showing? =
1. Ensure invite link is valid.  
2. Use permanent invite links.  
3. Check Discord API status.  

== Screenshots ==

1. Example of game server list in dark theme.  
2. Single Minecraft server widget.  
3. Discord integration example.  

== Changelog ==

= 1.0.0 =
* Added support for 200+ games
* Enhanced Discord integration
* Improved error handling
* Performance optimizations
* GameQ v3 integration
* Built-in fallback queries
* Discord server support

== Credits ==

* Author: [Yamiru](https://yamiru.com)  
* GameQ Library: [Austinb/GameQ](https://github.com/Austinb/GameQ)  

== License ==

This plugin is licensed under the GPL v2 or later.  
See: https://www.gnu.org/licenses/gpl-2.0.html
