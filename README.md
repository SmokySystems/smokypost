# SmokyPost – Social Auto Share

SmokyPost shares your WordPress posts and pages to your social media accounts when you publish them. Connect your accounts in a few clicks. No developer accounts or API keys needed.

- **WordPress.org:** https://wordpress.org/plugins/smokypost/
- **Website:** https://www.smokypost.com

## Features

- Share on publish, per platform on or off
- Share now from the editor or the post list, including a bulk action
- Platform choice and custom message for each item, message template with placeholders, tags as hashtags
- Image picked automatically (featured image, first content image, default image), or chosen by hand
- Status per platform in the editor, badges in the post list, History tab
- E-mail notifications for failed and successful shares, with built-in SMTP settings
- Works with the block editor, the classic editor, page builders, autoblogs, importers and the REST API
- English by default, follows the site language, 63 languages included

Platforms: Bluesky, Mastodon, Facebook Pages, Instagram business accounts, LinkedIn company pages, Threads and Tumblr. See the WordPress.org page for which platforms are available today.

The plugin connects to the SmokyPost service at www.smokypost.com to link your accounts and publish your posts. See "External service" in `readme.txt`.

## Repository

- `smokypost.php`, `includes/`, `assets/`, `languages/` – the plugin
- `tools/` – development tools (string extraction, translation build), not part of the release

Build the language files: `python3 tools/build-languages.py`

## Translations

Translations are welcome on [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/smokypost/).

## License

GPLv2 or later.
