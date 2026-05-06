[![ClassicPress Directory Coding Standard checks.](https://github.com/wolffe/classicpack/actions/workflows/cpcs.yml/badge.svg?branch=master)](https://github.com/wolffe/classicpack/actions/workflows/cpcs.yml)

# ClassicPack

A modular admin toolkit: one plugin, many optional features. You turn only what you need on from a single place so unused code is never loaded.

---

**Official plugin page:** [getbutterfly.com — ClassicPack](https://getbutterfly.com/classicpress-plugins/classicpack/)  
**More plugins from the same author:** [ClassicPress plugins at getbutterfly.com](https://getbutterfly.com/classicpress-plugins/)

---

## What this plugin is for

ClassicPack does not run any optional feature until you enable it. After installation, you open the **ClassicPack** item in the admin menu, choose the modules you want (grouped by category), and save. Disabled modules are not loaded, which keeps the site lean.

Use ClassicPack if you want several small admin utilities in one place instead of many separate small plugins, with a clear on/off switch for each one.

---

## Quick start (first-time setup)

1. Install the `classicpack` folder under `wp-content/plugins/` (or install the release zip from your Plugins screen) and **activate** ClassicPack.
2. In the admin sidebar, open **ClassicPack** (top-level menu). You will see checkboxes for each module, grouped (for example: Performance and SEO, Media, Content, Users).
3. Enable the modules you need and click **Save modules**. Only enabled modules are loaded on the next request.
4. Some modules add their own subpage under **ClassicPack** (for example *User Manager*) or a link from the modules list. Others appear only in context (for example a row action on a post list, or a block on a user profile).

If something does not show up, confirm the module is enabled and that you meet the module’s context (for example the post type switcher is for the **classic** post editor, not the block editor).

---

## Where things appear in the admin

| You are looking for… | Where to go |
| --- | --- |
| Turn features on or off | **ClassicPack** (main screen, module toggles) |
| Module-only settings (when available) | **ClassicPack →** submenu for that module, or the **Settings** / **Details** action on the module row, if shown |
| Core redirects, email commenters, auto-save images, users online, user manager, etc. | As documented per module in the table below |
| This README as project documentation | On GitHub or in the plugin folder as `README.md` |

**Help in the dashboard:** ClassicPress and WordPress do not add a “Help” dropdown for third-party plugin screens by default. For ClassicPack, use this README, the [readme.txt](readme.txt) *Description* and *Frequently Asked Questions* sections, and the short text under each module on the **ClassicPack** screen.

---

## Modules (what each one does, at a glance)

Enable these on the **ClassicPack** screen. Nothing here runs when the module is off.

| Module | What it does (short) | Where you use it |
| --- | --- | --- |
| **Core Redirects Manager** | Lists automatic redirects the CMS stores when a post’s URL changes; you can remove unneeded ones. | Under **ClassicPress**-related redirects (see module link) |
| **Email Commenters** | Sends a single email to every address that commented on a chosen post or page. | **ClassicPack** submenu: Email Commenters |
| **Auto Save Images** | When you publish, can pull hotlinked images into the Media Library. | Settings on the **Auto Save Images** screen |
| **Delete Post with Attachments** | On permanent post delete, can remove media uploaded only to that post when safe. | Runs when you trash/delete content (see description on the modules screen) |
| **Users Online** | Shows who is browsing the site and a dashboard summary. | **ClassicPack** / dashboard widget as implemented |
| **User Manager** | Optional Users list columns (e.g. registration, last login) and options to keep posts or pages for logged-in users only. | **ClassicPack → User Manager** and **Users** list |
| **User Content Overview** | On a user’s profile, shows counts and links for content that user “owns” (by post type, media, optional Woo customer orders) for cleanup or reassignment. | **Users** → edit a user |
| **Post type switcher** | In the **classic** editor, switch an existing item to another public post type (with a warning). | **Posts** or **Pages** (or CPT) → edit, Publish box |
| **Duplicate post** | Row action to duplicate a public post type as a draft (content, meta, terms, featured image as applicable). | Post type list tables |
| **User Avatar** | Profile picture from the Media Library on the user profile screen; replaces Gravatar when set (legacy meta key unchanged). | **Users → Profile** / **Users → Edit user** |

Some modules are Classic-editor specific or list-table specific. If you use only the block editor, test before relying on a module.

---

## Requirements

- ClassicPress 2.5+ or WordPress 6.2+
- PHP 8.0+

## Changelog

Release notes match [readme.txt](readme.txt) (plugin directory listing).

### 0.3.0

- User Avatar module: rename profile form table class from `easy-author-avatar-image-form-table` to `author-avatar-image-form-table`

### 0.2.1

- Add User Avatar module (profile picture from Media Library, legacy user meta key retained)
- Remove standalone Easy Author Avatar-style enable option; uninstall clears the legacy `easy_author_avatar_image_option` row if present

### 0.2.0

- Add User Content Overview module (profile content counts and admin links)
- Fix post type switcher nested-form submit (use detached form and HTML5 form association and fall back when wp_set_post_type is unavailable)
- Lower PHP requirement to 8.0
- Update documentation and readme

### 0.1.0

- Initial release
