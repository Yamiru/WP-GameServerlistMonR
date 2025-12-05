# 🎮 GameServerlistMonR – Game Server List & Monitoring for WordPress

[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.0%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-1.0.0-orange.svg)](https://github.com/Yamiru/WP-GameServerlistMonR)

Beautiful, minimalistic game server monitoring plugin for WordPress with real-time status updates. Monitor your game servers, Discord communities, and voice servers directly from your WordPress site.

## ✨ Features

- **🎯 Real-time Monitoring** - Live server status with auto-refresh
- **🎮 200+ Supported Games** - From Minecraft to Quake, CS2 to Rust
- **💬 Discord Integration** - Show Discord server member counts
- **🎨 5 Beautiful Themes** - Modern, Dark, Light, Glass, and Neon
- **📱 Fully Responsive** - Perfect on all devices
- **⚡ Lightweight & Fast** - Optimized queries with caching
- **🔧 No Configuration Required** - Works out of the box

## 🚀 Quick Start

### Installation

1. Download the latest release from [Releases](https://github.com/Yamiru/WP-GameServerlistMonR/releases)
2. Upload to `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to 'Game Servers' in your WordPress admin

### Basic Usage

Add a single server:
```
[game_server id="1"]
```

Display all servers:
```
[monr_list]
```

Display specific game type:
```
[monr_list type="minecraft" theme="dark"]
```

## 🌐 Live Demo
[https://yamiru.com/wp-plugin-gameserverlistmonr](https://www.yamiru.com/wp-plugin-gameserverlistmonr/)

## 🖼️ Preview Screenshot
![FAQ Screenshot](https://i.imgur.com/4BWCYcc.png)

## 🎮 Supported Games

<details>
<summary><strong>Click to see all 200+ supported games</strong></summary>

### Popular Games
- **Minecraft** (Java & Bedrock)
- **Counter-Strike** (1.6, Source, GO, 2)
- **Rust**
- **ARK: Survival Evolved**
- **Valheim**
- **7 Days to Die**
- **DayZ**
- **Terraria**
- **Team Fortress 2**
- **Garry's Mod**

### Classic Games
- **Quake Series** (Quake 1-4, Quake Live)
- **Call of Duty Series** (CoD 1-4, MW, MW2, MW3, BO, BO2, BO3)
- **Battlefield Series** (1942, 2, 2142, BC2, 3, 4, 1, V)
- **Unreal Tournament** (UT99, 2003, 2004, 3)
- **Half-Life Series**

### Survival & Sandbox
- Conan Exiles
- SCUM
- V Rising
- Project Zomboid
- Unturned
- Atlas
- Space Engineers
- Starbound

### Military & Tactical
- ArmA (1, 2, 3)
- Squad
- Post Scriptum
- Hell Let Loose
- Insurgency
- Insurgency: Sandstorm

### Voice & Chat
- Discord
- TeamSpeak 3
- Mumble
- Ventrilo

### GTA Mods
- FiveM
- RedM
- SA-MP
- MTA:SA
- RAGE MP

And many more...
</details>

## 📦 Requirements

- WordPress 5.8 or higher
- PHP 7.0 or higher
- PHP sockets extension (optional, for enhanced queries)

## 🔧 Configuration

### Adding a Server

1. Go to **Game Servers → Add New**
2. Enter server details:
   - **Server Type**: Game identifier (e.g., `minecraft`, `csgo`, `rust`)
   - **IP/Domain**: Server address
   - **Port**: Game port
   - **Query Port**: Optional, for games using different query ports

### Discord Setup

1. Create a Discord server invite link
2. Add server with type `discord`
3. Enter invite code or full URL

### Shortcode Parameters

#### `[monr_list]` Parameters:
- `type` - Filter by game type
- `theme` - Theme style (modern/dark/light/glass/neon)
- `show_offline` - Show offline servers (yes/no)
- `show_powered` - Show powered by link (yes/no)

#### `[game_server]` Parameters:
- `id` - Server ID (required)
- `theme` - Override theme for single server

## 🎨 Available Themes

### Modern (Default)
Clean, minimalistic design with subtle gradients

### Dark
Futuristic dark theme with neon accents

### Light
Bright, clean theme with pastel colors

### Glass
Glassmorphism effect with transparency

### Neon
Cyberpunk style with glowing effects

## 🚀 Advanced Features

### GameQ Integration

For enhanced server querying with 200+ protocol support:

1. **Automatic Installation** (Recommended)
   - Go to Tools → GameQ Install
   - Click "Install GameQ"

2. **Manual Installation**
   - Download [GameQ v3](https://github.com/Austinb/GameQ/archive/refs/heads/v3.zip)
   - Extract to `/wp-content/plugins/game-server-list-monitor/GameQ/`

### Custom Icons

- **Emoji Icons**: Add any emoji in the icon field
- **Custom Images**: Provide URL to custom icon image
- **Auto Icons**: Leave empty for automatic status icons (🟢/🔴)

### Query Ports

Some games use different ports for queries:
- **Rust**: Game port + 400
- **ARK**: Game port + 1
- **TeamSpeak**: 10011 (default query port)


## 🐛 Troubleshooting

### Server Shows Offline

1. Check if ports are open
2. Verify server type is correct
3. Check query port settings
4. Test with manual query tool

### No Response from Server

1. Firewall may block queries
2. Server may require whitelisting
3. Try different query port

### Discord Not Working

1. Ensure invite link is valid
2. Use permanent invite links
3. Check Discord API status


## 📝 Changelog

### Version 1.0.0
- Added support for 200+ games
- Enhanced Discord integration
- Improved error handling
- Performance optimizations
- GameQ v3 integration
- Built-in fallback queries
- Discord server support

## ❓ FAQ

- **How do I display the server list on my website?**  
  Add the shortcode: `[monr_list]`.

- **How can I make the server list display full-width (wide screen)?**  
  Use a responsive theme or place the shortcode inside text that is not restricted by a container.

- **What should I do if the server does not appear or shows the wrong status?**  
  Clear the cache in the WordPress admin panel or wait a moment for it to refresh.
  
## 📄 License

This project is licensed under the GPL v2 License - see the [LICENSE](LICENSE) file for details.

## 🙏 Credits

- **Author**: [Yamiru](https://yamiru.com)
- **GameQ Library**: [Austinb/GameQ](https://github.com/Austinb/GameQ)
- **Contributors**: Thanks to all contributors!

## 💖 Support

If you find this plugin useful, please consider:
- ⭐ Starring the repository
- 🐛 Reporting bugs
- 💡 Suggesting new features
- 📝 Improving documentation

## 📧 Contact

- **Website**: [yamiru.com](https://yamiru.com)
- **GitHub**: [@Yamiru](https://github.com/Yamiru)
- **Issues**: [GitHub Issues](https://github.com/Yamiru/WP-GameServerlistMonR/issues)

---

**Powered by MonR** - A project by [Yamiru](https://yamiru.com)

Made with ❤️ for the gaming community
