![](https://i.ibb.co/LXt5w5hG/bumps.png)

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/huseyinfiliz/bump.svg)](https://packagist.org/packages/huseyinfiliz/bump) [![Total Downloads](https://img.shields.io/packagist/dt/huseyinfiliz/bump.svg)](https://packagist.org/packages/huseyinfiliz/bump)

# Bump
A powerful discussion bumping system for [Flarum](https://flarum.org) forums. Allow users to bump discussions to keep them visible, with comprehensive quota management, cooldown controls, and group-based permissions.

### 🤖 Manual Bump Settings
![Demo](https://i.ibb.co/nsx19jNv/image.png)

### 🛠️ Absorber Settings
![Demo](https://i.ibb.co/sJwNcsbb/image.png)

### 🔧 Group Overrides Settings
![Demo](https://i.ibb.co/DDD4pWbj/image.png)

### 📈 Recent Activity
![Demo](https://i.ibb.co/7dW5Gdq0/image.png)

## Compatibility

| Branch | Flarum | PHP |
| --- | --- | --- |
| `1.x` | Flarum 1.x | As required by Flarum 1.x |
| `2.x` | Flarum 2.0.2 and newer 2.x releases | PHP 8.3+ |

The `2.x` branch uses Flarum 2's resource API. Manual bumps use
`POST /api/discussions/{id}/bump` and return the updated discussion.

## Features

- 🔄 **Manual Bumping**: Users can bump discussions to bring them back to the top
- 🛡️ **Bump Absorber**: Prevent new discussions from being bumped too frequently
- ⏰ **Cooldown System**: Configurable time restrictions between manual bumps
- 📊 **Quota Management**: Daily and weekly bump limits per user
- 👥 **Group Overrides**: Different bump and absorber settings for each user group
- 🚫 **Bypass Permissions**: Moderators can bypass all restrictions
- 🏷️ **Tag-based Control**: Configure which tags allow bumping and absorber
- 📈 **Stats Dashboard**: View bump statistics and recent activity
- 🚀 **Performance**: Smart caching with automatic cache invalidation

### Installation

```bash
composer require huseyinfiliz/bump
```

You can also install with Extension Manager: `huseyinfiliz/bump`

### Updating

```sh
composer update huseyinfiliz/bump
```
To remove simply run `composer remove huseyinfiliz/bump`

### Credits
This extension sponsored by [@andrewjs](https://discuss.flarum.org/u/andrewjs) ✨

![](https://flarum.org/extension/huseyinfiliz/bump/open-graph-image)

## Quick Start

### For Users
1. Open any discussion you own or have permission to bump
2. Click the **"Bump"** button at the bottom of the discussion
3. The discussion moves to the top of the discussion list

### For Admins
Navigate to **Admin → Extensions → Bump** to configure:

#### Manual Bump Tab
- **Enable Manual Bump**: Allow discussion owners to manually bump their discussions
- **Cooldown Hours**: Time users must wait between bumps
  - Set to `-1` to disable bumps
  - Set to `0` for no cooldown
  - Set to a positive number for hours (e.g., 24 = once per day)
- **Daily Quota**: Maximum bumps per day for discussion owners
  - Set to `-1` to disable bumps
  - Set to `0` for unlimited
  - Set to a positive number for daily limit
- **Weekly Quota**: Maximum bumps per week for discussion owners
  - Set to `-1` to disable bumps
  - Set to `0` for unlimited
  - Set to a positive number for weekly limit
- **Available in Tags**: Select which tags allow manual bumping (empty = all tags)
- **Moderator Groups**: Select groups that can bump ANY discussion and bypass all restrictions

#### Absorber Tab
- **Enable Absorber**: Prevent discussions from being bumped too frequently by new posts
- **Time Threshold**: Minimum hours required between discussion creation and allowing auto-bumps from new posts
  - Set to `0` to disable absorber
  - For example, setting to 2 means new posts won't bump the discussion for the first 2 hours after creation
- **Apply to Tags**: Select specific tags where absorber should be active (empty = all tags)
- **Bypass Groups**: Select groups whose posts will always bump discussions immediately

#### Group Overrides Tab
Customize settings for specific user groups (overrides global settings):
- **Manual Bump Settings**: Override cooldown, daily quota, and weekly quota per group
- **Absorber Settings**: Override absorber threshold per group
- Leave fields empty to use global defaults
- Set to `0` for unlimited/bypass
- Priority: Group settings > Global defaults

#### Recent Activity Tab
View recent bump activity with details:
- Discussion title and link
- Bump type (Manual/Automatic/Absorber)
- User who performed the bump
- Timestamp

## 🎯 Use Cases

### Marketplace Forums
- Sellers can bump their listings every 24 hours with manual bump
- Prevent spam with daily/weekly quotas
- VIP members get more bump allowances via group overrides
- Absorber prevents new listings from being bumped by rapid replies

### Support Forums
- Discussion owners can bump unanswered questions
- Staff bypass all restrictions with moderator groups
- Absorber keeps new questions organized for first few hours

### Community Forums
- Keep active discussions at the top with manual bumping
- Fair bumping with cooldown controls
- Group-based privileges for supporters
- Absorber prevents spam bumping on trending topics

## 🔧 Advanced Configuration

### Group Override Priority
Settings are resolved in this order:
1. **Group Override** (if user belongs to group with override)
2. **Global Default** (if no group override applies)

### Manual Bump Behavior
- **Cooldown**: Time restriction applies per discussion, not globally
- **Quotas**: Reset based on rolling time windows (not midnight)
  - Daily quota: Last 24 hours from current time
  - Weekly quota: Last 7 days from current time
  - Quotas are tracked per user across all discussions
- **Moderator Groups**: Can bump ANY discussion and bypass all cooldown/quota restrictions

### Absorber Behavior
- **Threshold Check**: Based on discussion age (time since creation), not last bump time
- **First Bump**: First post always allowed to bump the discussion
- **Manual Bump Protection**: When absorber blocks a post, it preserves any manual bumps
- **Bypass Options**:
  - Global bypass groups: Users in these groups always bump discussions
  - Group override threshold 0: Per-group absorber bypass
- **Tag Control**: Works only on selected tags (or all if empty)

### Cache Management
The extension automatically clears all caches when you save settings in admin panel.

If you modify database values manually, run:
```bash
php flarum cache:clear
```

After saving settings, refresh forum pages (F5) to load new settings.

## 🌍 Translations

This extension comes with English translations. Community translations are welcome!

## 💖 Support & Contributing
If you find this extension useful, consider:
- ⭐ Starring the repository on GitHub
- 💬 Leaving feedback on the Flarum discussion
- **Issues**: [GitHub Issues](https://github.com/huseyinfiliz/bump/issues)
- **Discussions**: [Flarum Community](https://discuss.flarum.org/d/38327-bump-smart-absorber-push-top)

---

**Developed by** ❤️ [Hüseyin Filiz](https://huseyinfiliz.com/en)
