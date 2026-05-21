=== ClassicPack ===
Contributors: butterflymedia
Tags: classicpress, modules, toolkit, admin, utilities
Requires at least: 6.2
Requires PHP: 8.0
Requires CP: 2.5
Tested up to: 2.7
Stable tag: 0.3.5
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A modular toolkit for ClassicPress and WordPress. Enable modules from the ClassicPack screen in ClassicPress or WordPress.

== Description ==

ClassicPack groups optional features into **modules**. Each module can be switched on or off from the ClassicPack screen—nothing runs until you enable it.

**Examples of what you can enable:**

* **Core Redirects Manager** — Inspect and remove automatic redirects that ClassicPress and WordPress store when URLs change.
* **Email Commenters** — Send one email to everyone who commented on a post or page.
* **Auto Save Images** — Pull remote images into your Media Library when you publish.
* **Delete Post with Attachments** — On permanent delete, clean up uploads tied to that post when safe.
* **Users Online** — See who is on the site and a dashboard summary.
* **User Manager** — Optional Users screen columns and members-only content helpers.
* **User Content Overview** — On a user’s profile, lists counts and admin links for content they author (and related rows such as media or customer orders), for cleanup and reassignment.
* **Post type switcher** — On the Classic post editor, change an item’s post type (with a clear warning).
* **Duplicate post** — Row action to duplicate a public post type as a draft, including meta and terms.

* **User Avatar** — Pick a profile picture from the Media Library on your profile; shown instead of Gravatar where avatars appear.
* **Admin Login** — Custom login URL; redirect visitors who hit wp-login.php or /wp-admin/ without signing in (Settings → Permalinks).

Requirements: ClassicPress 2.5+ (or WordPress 6.2+), PHP 8.0+.

== Installation ==

1. Upload the `classicpack` folder to `/wp-content/plugins/`, or install the zip from the ClassicPress / WordPress plugin screen.
2. Activate **ClassicPack** through the Plugins menu.
3. Go to **ClassicPack** in the admin menu and enable the modules you want.

== Frequently Asked Questions ==

= Does ClassicPack work with the block editor? =

Some modules target the Classic editor or list tables only. Enable modules individually and test in your environment.

= Where are settings stored? =

Enabled modules are stored in the `classicpack_modules` option. Individual modules may add their own options where documented.

== Changelog ==

= 0.3.5 =
* Admin Login: use `classicpack_admin_login_*` options and resolve CPCS violations

= 0.3.4 =
* Add Admin Login module (custom login URL and redirects; configure under Settings → Permalinks)

= 0.3.3 =
* Load the `classicpack` textdomain on `init` (no translation files required); avoid WP 6.7+ `_load_textdomain_just_in_time()` notices from early gettext
* Bootstrap enabled modules on `init` instead of `plugins_loaded` so module registry strings run after translations are allowed

= 0.3.2 =
* Remove the non-functional Details action for Delete Post with Attachments and User Avatar on the modules screen
* Add the `classicpack-ui-toggle` class to plugin admin checkboxes (Auto Save Images, User Manager, and post editor or meta box hooks)

= 0.3.1 =
* Align short descriptions with ClassicPress and WordPress
* Add Plugins list link to the ClassicPack modules screen
* Clarify Core Redirects Manager description for ClassicPress and WordPress

= 0.3.0 =
* User Avatar module: rename profile form table class from `easy-author-avatar-image-form-table` to `author-avatar-image-form-table`

= 0.2.1 =
* Add User Avatar module (profile picture from Media Library, legacy meta key retained)
* Remove legacy standalone Easy Author Avatar Image option usage; uninstall clears old option key

= 0.2.0 =
* Add User Content Overview module (profile content counts and admin links)
* Fix post type switcher nested-form submit (use detached form and HTML5 form association and fall back when wp_set_post_type is unavailable)
* Lower PHP requirement to 8.0
* Update documentation and readme

= 0.1.0 =
* Initial release
