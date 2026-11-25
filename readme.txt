=== SmokyPost – Social Auto Share for Posts and Pages ===
Contributors: smokysystems
Tags: auto post, social sharing, facebook, instagram, linkedin
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.3.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically share new and old posts and pages to your social media accounts. No developer accounts or API keys needed.

== Description ==

SmokyPost shares your WordPress posts and pages to your social media accounts when you publish them. Connect your accounts in a few clicks, choose a page for each platform, and keep publishing as usual. You do not need developer accounts or API keys: you sign in with your normal account and SmokyPost does the rest.

**Available now:** Bluesky and Mastodon.

**Coming soon:** Facebook Pages, Instagram business accounts, LinkedIn company pages, Threads and Tumblr. The plugin is ready for them, but the platforms have to approve SmokyPost before everyone can connect an account. Until then, connecting these may not work yet. More platforms are on the way.

= What you get =

* **Share on publish.** New posts and pages go out right after you publish them. Switch automatic sharing on or off for each platform, on its platform card or under Settings.
* **Share now.** Send any published post or page from the editor or the post list, one at a time or several at once with a bulk action. Updating a published item does not share it again.
* **Control for each item.** Tick the platforms you want and write a custom message in the editor. A message template with placeholders covers everything else, and post tags can become hashtags.
* **The right image.** SmokyPost uses the featured image, then the first image in the content, then your default image. The editor shows a preview and where the image comes from, and you can pick another image from the content or the Media Library.
* **Made for each platform.** Facebook gets a link preview, LinkedIn an article card, and Instagram an image post that is cropped to a size Instagram accepts.
* **Instagram safety net.** Instagram needs an image. You get a warning in the editor and before publishing, and skipped items are marked in the post list.
* **Clear status.** See the result for each platform in the editor, FB, IG and LI badges in the post list, and a History tab. Failure reasons are written in plain language.
* **No lost shares.** If a platform is busy or says no, SmokyPost tries again later, and sends you an e-mail when a connection needs to be renewed.
* **E-mail notifications.** Get an e-mail when a share fails and, if you like, when an item was shared. Send them to one or more addresses, and use your own SMTP account if the e-mails of your site do not arrive.
* **Works with automated content.** Posts created by other plugins, importers, autoblogs, the REST API and WP-CLI are shared too. You can turn this off, and old imports are skipped.
* **Any public post type.** Posts, pages, products and more.
* **Editors and page builders.** Works with the block editor and the classic editor. SmokyPost reads your content the way visitors see it, so shortcodes, page builder content and lazy-loaded images are found.
* **Your language.** English by default, it follows your site language. 63 languages are included and every text can be translated.

= How it works =

1. Install and activate the plugin, then create a free SmokyPost account from the SmokyPost screen in your WordPress admin.
2. Connect Bluesky or Mastodon and choose an account. Facebook, Instagram, LinkedIn, Threads and Tumblr follow as soon as each platform approves SmokyPost.
3. Publish a post. SmokyPost shares it with its title, excerpt, link and image.

= Free and paid plans =

SmokyPost is free to use. The free plan of the SmokyPost service covers one site and 30 items a day, each shared to all the platforms above, with automatic and manual sharing and one-time scheduling. A Pro plan is for people who need more, such as a sharing queue with a calendar, automatic re-sharing of older items, a different text for each platform and links for clients. Plans and prices are listed at https://www.smokypost.com/.

Everything in this plugin works without paying. The extra Pro features come as a separate add-on plugin, SmokyPost Pro, which you download after paying.

= Good to know =

Each platform sets its own limits, for example on how many posts an account can publish through its API in a day. SmokyPost retries when a platform is busy, but it cannot go beyond what the platforms allow.

SmokyPost is not affiliated with, endorsed by or sponsored by Meta Platforms, Inc. (Facebook, Instagram), LinkedIn Corporation or Google LLC.

== External service ==

This plugin relies on the SmokyPost service at https://www.smokypost.com, operated by Dumanlı Web Tasarım (Istanbul, Turkey), to connect your social accounts and publish your posts. The service is required for the plugin to work. Nothing is sent until you create an account or sign in from the plugin.

After you connect, the plugin sends:

* when connecting: your e-mail address and password (or license key), the site address, WordPress, PHP and plugin versions, site language and time zone;
* when an item is shared: its ID, title, link, excerpt, the message text and the address of its image;
* periodically: a request for the status of recent shares.
* with each request: the address of the site's wp-cron.php. The service calls this address every few minutes, so status updates and e-mail notifications also go out on time on sites with few visitors.
* when the site is disconnected: whether it was the Disconnect button or deleting the plugin.

Your social account tokens are stored encrypted on the SmokyPost servers in Germany and are never stored on your site. The service then publishes to Facebook and Instagram (Meta Platforms), LinkedIn, Bluesky, Mastodon, Threads (Meta Platforms) and Tumblr on your behalf. For Bluesky it keeps the app password you enter, encrypted, and never your main password. When you remove a platform or disconnect the site, the connection is deleted from the service, and uninstalling the plugin disconnects the site. To have your account data deleted, write to info@smokypost.com or use https://www.smokypost.com/data-deletion/.

Payments for paid plans are handled by Paddle (Paddle.com Market Limited), which sells the plan to you as the merchant of record. When you choose a paid plan, you are sent to Paddle's secure checkout and Paddle collects your payment details and billing address. SmokyPost never sees or stores your card number, and receives only the information needed to activate your plan (your plan, its renewal status and Paddle's customer and subscription IDs).

* Paddle Terms of Use: https://www.paddle.com/legal/terms
* Paddle Privacy Policy: https://www.paddle.com/legal/privacy
* Terms of Service: https://www.smokypost.com/terms/
* Privacy Policy: https://www.smokypost.com/privacy-policy/

== Installation ==

1. Install and activate the plugin.
2. Go to SmokyPost in the admin menu and create a free account or sign in.
3. Connect your social accounts and choose a page for each.
4. Publish a post.

== Frequently Asked Questions ==

= Do I need a Facebook or LinkedIn developer app? =

No. You sign in with your normal account; SmokyPost uses its own apps.

= Which platforms are supported? =

Bluesky accounts and Mastodon accounts (any Mastodon server) work today. Facebook Pages, Instagram business and creator accounts, LinkedIn company pages, Threads profiles and Tumblr blogs are coming soon: SmokyPost is built for them, but each platform has to approve it before everyone can connect. More platforms are planned.

= Is it free? =

The plugin is free software under the GPL. The sharing service has a free plan for one site and 30 items a day.

= What counts towards the daily limit? =

Each item counts once a day, however many platforms it is shared to. The Account tab shows how many items you have shared today.

= Will automatically created posts be shared? =

Yes, by default. Turn off "Automatically created items" under SmokyPost > Settings if you only want to share what you publish in the editor. Imported items older than 24 hours are never shared automatically.

= Does it share again when I update a post? =

No. Updating a published item never shares it again. Use "Share now" in the editor or the post list when you want to send it once more.

= Can I share pages and older posts? =

Yes. Choose Page under SmokyPost > Settings > Content types, then use "Share now" on any published post or page, or select several in the list and use the bulk action.

= Which image is shared? =

The custom image you choose in the editor, otherwise the featured image, otherwise the first image in the content, otherwise your default image from the settings.

= Why was Instagram skipped? =

Instagram only accepts posts with an image. Add a featured image or set a default image in the settings.

= What happens if a platform is busy or a connection expires? =

SmokyPost tries again a few times over the next hours. If a connection has expired, you get an e-mail and the Platforms tab shows it, so you can reconnect in a few clicks.

= Does it slow down my site? =

No. Sharing is done by the SmokyPost service, not while your visitors load a page.

= Can I use it on client sites? =

Yes, each site connects with its own account. The free plan covers one site.

= What happens to my shared posts if I stop using SmokyPost? =

Nothing. Posts already shared stay on your social pages. Deactivating or uninstalling only stops new shares, and uninstalling disconnects the site and removes the plugin's settings.

= What data does the plugin send? =

See the External service section above for exactly what is sent and when.

= Which languages does it support? =

The plugin is translated into 63 languages and shows itself in your WordPress site language. Support is in English only: please write to us in English.

= Who translated the plugin? Can I help? =

English is the original. Turkish is written and checked by the author, a native Turkish speaker. The other languages were translated with an AI model and have not been reviewed by native speakers yet. We are looking for translation volunteers: if you speak one of these languages, please help on [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/smokypost/) and fix anything that sounds wrong. Every correction is welcome, and we will follow what your language team prefers.

== Screenshots ==

1. The Platforms tab: connect Facebook, Instagram, LinkedIn, Bluesky, Mastodon, Threads and Tumblr and switch automatic sharing on or off for each platform.
2. The SmokyPost panel in the block editor: see the image that will be shared, pick the platforms and write a custom message.
3. The post list shows where each item was shared, with a bulk action to share several items at once.
4. The History tab lists the latest shares and their result for each platform.
5. Settings: content types, message template, hashtags, default image and rules for automatically created items.

== Upgrade Notice ==

= 0.3.3 =
Deleting the plugin now reliably disconnects the site from SmokyPost.

= 0.3.2 =
Notifications go out on time on quiet sites, and the Turkish translation follows the review of the Turkish team.

= 0.3.0 =
New: e-mail notifications when a share fails or succeeds, with optional SMTP settings for them.

= 0.2.9 =
The plugin name and description are now translated in all 63 languages, and the Turkish wording is better.

= 0.2.8 =
Now in 63 languages, including right-to-left layouts.

= 0.2.7 =
The Account tab explains how Pro works before you pay and shows the download after.

= 0.2.6 =
The plugin author is now SmokySystems.

= 0.2.5 =
New: schedule a share for a date and time, free. The free plan now allows 30 items a day.

= 0.2.4 =
Fixes the SmokyPost column in the post list, which ran out of its table when many platforms are connected.

= 0.2.3 =
No changes to how the plugin works. The description now says which platforms work today.

= 0.2.2 =
No changes for users. The plugin is now published under the SmokySystems account.

= 0.2.1 =
New "Delete shared posts" button in the post list and the editor.

= 0.2.0 =
The plugin is now fully free. Features that needed a paid plan are no longer in it, and the service address is now www.smokypost.com.

= 0.1.35 =
A share that waits for its time can now be cancelled from the editor.

= 0.1.34 =
New on paid plans: ask a client to connect their own page with a link, no password needed.

= 0.1.33 =
New on paid plans: schedule a share, write a different text for each platform, and share old items again automatically.

= 0.1.32 =
Shared posts are taken down right when the item is deleted, without waiting for WP-Cron.

= 0.1.31 =
Small text fix in Settings.

= 0.1.30 =
Shared excerpts keep the paragraphs of your post.

= 0.1.29 =
Adds Threads and Tumblr.

= 0.1.28 =
You can now clear the History list by dates or all at once.

= 0.1.27 =
Live progress when you click "Share now".

= 0.1.26 =
Adds Bluesky and Mastodon.

= 0.1.25 =
Clearer message when a platform cannot list your accounts; short descriptions on the platform cards.

= 0.1.24 =
New optional setting: delete the social posts when an item is deleted.

= 0.1.23 =
New: share to Google Business Profile (paid plans).

= 0.1.22 =
New plan prices: Starter $39, Pro $79, Agency $149 per year.

= 0.1.21 =
The Account tab now shows the daily item limit of each plan.

= 0.1.20 =
Wording fix in the refund confirmation.

= 0.1.19 =
Plan changes now show the exact amount and ask you to confirm. You can cancel and get a refund from the Account tab.

= 0.1.18 =
After paying you are taken back to your own site.

= 0.1.17 =
New: upgrade to a paid plan from the Account tab.

== Changelog ==

= 0.3.3 =
* When the site is disconnected, the plugin tells SmokyPost whether it was the Disconnect button or deleting the plugin.
* Deleting the plugin now reliably disconnects the site from SmokyPost.

= 0.3.2 =
* Status updates and e-mail notifications go out on time on sites with few visitors: the plugin tells SmokyPost the address of the site's wp-cron.php, and the service calls it every few minutes.
* The Turkish translation follows the review of the WordPress Turkish team.

= 0.3.1 =
* New extension point so SmokyPost Pro can switch e-mail notifications on or off for each platform.

= 0.3.0 =
* New Notifications tab: e-mails when a share fails and when an item was shared, one e-mail per item. Both are on by default and can be switched off.
* Notifications go to the administrator address of the site, or to any addresses you enter, separated by commas.
* A failed share is reported after SmokyPost has tried several times, and the same problem on the same platform is reported once a day.
* New SMTP settings for SmokyPost's e-mails, with a button to send a test e-mail. Other e-mails of your site are not changed.
* The Turkish wording of the payment note is better, and the readme now asks for translation volunteers.

= 0.2.9 =
* The plugin name and description are now translated in every language: the Plugins screen shows "SmokyPost – …" in your site language.
* Turkish wording now follows the WordPress Turkish glossary and style guide.
* The plugin page text is up to date (63 languages, the Pro add-on).

= 0.2.8 =
* The plugin now ships in 63 languages and shows in your WordPress site language. Right-to-left languages (Arabic, Hebrew) lay out correctly.

= 0.2.7 =
* Account tab: a short explanation of how Pro works before you pay, a download button for the Pro add-on afterwards, and the page checks by itself while the plan is being activated.

= 0.2.6 =
* The plugin author is now SmokySystems (smokysystems.com).

= 0.2.5 =
* New: "Share at" in the editor schedules a share for a date and time in your site time zone, once. It works for an item that is already published and for one you are still writing (it is then shared at that time after you publish). "Cancel the scheduled share" takes it back.
* The free plan of the SmokyPost service now allows 30 items a day (it was 3).

= 0.2.4 =
* Fix: the SmokyPost column in the post list no longer runs out of the table when many platforms are connected. The platform badges now wrap onto a second line.

= 0.2.3 =
* The description now says which platforms work today (Bluesky, Mastodon) and which are coming soon once each platform approves SmokyPost.

= 0.2.2 =
* The plugin is now published under the SmokySystems WordPress.org account. No changes to how it works.

= 0.2.1 =
* New: "Delete shared posts" in the post list and in the editor takes an item's posts down again without deleting the item. A confirmation is shown first. Instagram does not allow other apps to delete posts, so you are reminded to delete that one in the app.

= 0.2.0 =
* The plugin no longer contains features that need a paid plan: scheduling, a separate text for each platform, automatic re-sharing, links for clients and Google Business Profile are removed, so everything left in the plugin works for everyone.
* Add-on plugins can extend SmokyPost through new filters and actions.
* The SmokyPost service now runs at https://www.smokypost.com.
* The bundled translation is now loaded on the init hook.

= 0.1.35 =
* New (paid plans): "Cancel the scheduled share" in the editor takes back a share that still waits for its time.

= 0.1.34 =
* New (paid plans): "Ask your client to connect" on a platform card makes a link your client opens to sign in to Facebook, Instagram, LinkedIn, Threads, Tumblr or Google. You then choose their page or account.

= 0.1.33 =
* New (paid plans): "Share at" in the editor schedules a share for a date and time in your site time zone.
* New (paid plans): different text for each platform for a single item, next to the per-platform templates in Settings.
* New (paid plans): "Share old items again" shares one older item every few hours, never the same one within the gap you set. Items can be excluded in the editor.

= 0.1.32 =
* Fix: when an item is deleted, SmokyPost is asked to take down the shared posts at once instead of waiting for WP-Cron, which some sites run rarely.

= 0.1.31 =
* Fix: the note under "Delete the shared posts when the item is deleted" now lists Threads and Tumblr.

= 0.1.30 =
* Fix: the automatic excerpt keeps the paragraphs of the post instead of running them together on one line.

= 0.1.29 =
* New: Threads and Tumblr. Connect your Threads profile or a Tumblr blog and every new item is posted with a link card. Both are included in the Free plan and are removed with the item when "Delete the shared posts when the item is deleted" is on.

= 0.1.28 =
* New: clear the History list. Choose a range of dates or clear everything. Items already shared stay on your social accounts, and items still being sent stay in the list.

= 0.1.27 =
* "Share now" now shows the progress live (Sending, then Shared or Failed for each platform) instead of "Queued". You can leave the page; sharing continues in the background.

= 0.1.26 =
* New: Bluesky. Connect with your handle and an app password and every new item is posted with its hashtags and a link card with the picture.
* New: Mastodon. Enter your server (for example mastodon.social), approve SmokyPost there, and new items are posted with their link.
* Both are included in the Free plan and are removed with the item when "Delete the shared posts when the item is deleted" is on.

= 0.1.25 =
* Fix: when a platform signs in fine but refuses to list your pages or locations (for example Google before it approves Business Profile access), SmokyPost now says why instead of asking you to sign in again.
* New: every platform card has a short description.

= 0.1.24 =
* New: "Delete the shared posts when the item is deleted" (Settings, off by default). Moving an item to the trash or deleting it also removes its posts from Facebook, LinkedIn and Google Business Profile. Instagram does not allow this, so those posts stay and are marked in the history.

= 0.1.23 =
* New: Google Business Profile. Connect a location and new items are posted there with a "Learn more" button (paid plans).

= 0.1.22 =
* New plan prices: Starter $39, Pro $79 and Agency $149 per year.

= 0.1.21 =
* The plans table shows how many items each plan can share per day, and the Account tab shows today's use against that limit.

= 0.1.20 =
* Wording fix in the cancel and refund confirmation.

= 0.1.19 =
* Changing plans now shows the exact amount and the card that will be charged, and asks for confirmation first.
* New: cancel and get a full refund from the Account tab within 14 days of the first payment.

= 0.1.18 =
* After paying, the checkout page now returns you to the SmokyPost Account tab on your own site.

= 0.1.17 =
* Upgrade, switch plans and manage your subscription from the Account tab (payments by Paddle).

= 0.1.16 =
* Shorter plugin name: SmokyPost – Social Auto Share for Posts and Pages.
* Form input handling reviewed and documented.

= 0.1.15 =
* Plugin page: full description, FAQ and screenshots. The author link now points to the author's own site.

= 0.1.14 =
* New plugin name: SmokyPost – Social Auto Share Plugin for WordPress Sites.

= 0.1.13 =
* New plugin description and welcome text that no longer list individual platforms, as more platforms are coming.

= 0.1.12 =
* Automatic sharing no longer waits for the next WP-Cron run: right after an item is published the site asks itself to share it once the short delay is over. Sites with a slow or missing server cron share within about 15 seconds. The scheduled cron event stays as a fallback.

= 0.1.11 =
* Page builders: text and images are read from the content as visitors see it (Elementor, WPBakery, Divi and other shortcode builders); lazy-loaded images are found too.
* The editor notes that updating a published item does not share it again.

= 0.1.10 =
* Choose the image to share for each post or page: another image from the content or any image from the Media Library.

= 0.1.9 =
* Browsers load the new SmokyPost files after an update, also on sites that remove "?ver=" from file addresses.

= 0.1.8 =
* The editor shows which image will be shared and where it comes from.
* History lists the newest shares first.

= 0.1.7 =
* Automatic sharing can be turned on or off per platform, on each platform card or under Settings.
* Instagram warning in the editor and before publishing when an item has no image; skipped platforms are shown in the post list.
* "Share with SmokyPost" in the post list shares to all connected platforms.

= 0.1.6 =
* Everything is in English by default and follows the site language; no browser-language messages.
* Failure reasons and plan names are translated.
* Fixed the Facebook page picture.

= 0.1.5 =
* Clear, larger messages for every sign-in form error.

= 0.1.4 =
* Clearer notice about what is sent to SmokyPost.

= 0.1.3 =
* Form errors are shown by SmokyPost in the site language, not by the browser.

= 0.1.2 =
* Passwords need at least 8 characters.

= 0.1.1 =
* Sign-in form fields fit their boxes.

= 0.1.0 =
* First version.
