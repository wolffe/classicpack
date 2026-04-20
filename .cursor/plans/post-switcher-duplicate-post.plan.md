---
name: Post switcher and duplicate post
overview: Add two optional ClassicPack modules—Classic Editor post type switcher (public types + warning, no extra settings) and duplicate post (draft copy with meta/taxonomies)—registered like existing modules with enable/disable only.
todos:
  - id: registry
    content: Register `post-type-switcher` and `duplicate-post` in classicpack-modules.php (registry, groups, loader); no admin_action_config (no Details button)
  - id: module-switcher
    content: Implement modules/post-type-switcher/post-type-switcher.php (Classic Editor UI + admin_post handler + caps + nonce)
  - id: module-duplicate
    content: Implement modules/duplicate-post/duplicate-post.php (row actions + duplication + redirect)
  - id: qa
    content: Smoke-test on post.php and list tables; run CPCS/PHPCS on new files
---

# Post type switcher + duplicate post modules

## Scope (confirmed)

- **Classic Editor only** (ClassicPress target): no Block Editor UI. Gate with `post.php` / `post-new.php` and avoid loading block-editor assets; optionally bail if `use_block_editor_for_post` is true when that API exists (WordPress parity).
- **Enable/disable only** via existing [`classicpack_modules`](includes/classicpack-modules.php) option—no new options tables or settings screens.
- **Public post types**: `get_post_types( array( 'public' => true ), 'objects' )` for both features; exclude the current type from the switcher dropdown.
- **Post type switcher**: small inline **warning** (taxonomies, meta, URLs, and plugins may not match the new type).
- **Duplicate post**: follow the earlier “small win” pattern—**new draft**, copy content/excerpt/title pattern, **post meta** (whitelist or copy all except internal keys like `_edit_lock`), **terms** per taxonomy, **featured image** via `_thumbnail_id`; redirect to edit screen of the duplicate.

## Registry and UI placement

- Add entries to [`classicpack_get_module_registry()`](includes/classicpack-modules.php) with labels/descriptions and files under `modules/post-type-switcher/post-type-switcher.php` and `modules/duplicate-post/duplicate-post.php`.
- **Do not** add [`classicpack_get_module_admin_action_config()`](includes/classicpack-modules.php) entries—modules with no config omit the Details/Settings column (see `if ( $action_cfg )` in the modules screen template).
- Restore a **Content** group in `$groups` (e.g. `id: content`) containing both slugs, or append them to an existing group that fits product language (“Content” is clearest).

## Module 1: Post type switcher

- **Where**: [`post_submitbox_misc_actions`](https://developer.wordpress.org/reference/hooks/post_submitbox_misc_actions/) or a small meta box in the submit area—keeps it visible on the Classic edit screen without cluttering the title area.
- **When**: Existing posts only (`get_current_screen()->action === 'add'` or `post-new.php` → hide). Require `edit_post` on the post and capability to create/edit the **target** type (`get_post_type_object( $type )->cap`).
- **UI**: `<select>` of other public types + short warning text + submit control (or “Change type” button posting to `admin-post.php`).
- **Handler**: `admin_post_classicpack_switch_post_type` (or namespaced action): verify nonce, `wp_update_post( array( 'ID' => $id, 'post_type' => $new_type ) )`, [`wp_safe_redirect`](https://developer.wordpress.org/reference/functions/wp_safe_redirect/) back to `post.php` with success query arg. Handle failure with `wp_die` or admin notice.
- **Edge cases**: Do not list types the user cannot create; skip `attachment` if it appears as public=false in practice (rely on `public => true`).

## Module 2: Duplicate post

- **Where**: [`post_row_actions`](https://developer.wordpress.org/reference/hooks/post_row_actions/)—single callback that checks `$post->post_type` against public types and adds “Duplicate” link with nonce URL.
- **Handler**: `admin_post_classicpack_duplicate_post`: capability `edit_post` + create posts for target; clone via `wp_insert_post`, then copy meta (skip `_edit_lock`, `_edit_last`, etc.), `wp_set_object_terms` for each taxonomy, copy `_thumbnail_id` if valid attachment.
- **Default status**: `draft` (as discussed).
- **Redirect**: `wp_redirect` to `post.php?post={new_id}&action=edit` with optional `duplicated=1` notice.

## Files to add

| Path | Role |
|------|------|
| `modules/post-type-switcher/post-type-switcher.php` | Hooks + UI + switch handler |
| `modules/duplicate-post/duplicate-post.php` | Row action + duplicate handler |

Optional minimal CSS only if core classes are insufficient (prefer WordPress admin patterns).

## Non-goals (v1)

- Block Editor support, bulk duplicate, scheduled duplicate, or per-role settings beyond capabilities.

## Verification

- Enable each module on ClassicPress with Classic Editor; switch type between two public types; duplicate a post and confirm draft + meta + terms + featured image.
- Run project PHPCS/CPCS on new PHP files.
