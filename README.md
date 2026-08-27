# Register Columns

Add columns, filters, bulk actions and row actions to WordPress's own list
tables — posts, users, terms, comments and media.

## What it does

Four things that sound separate and are not. Each hangs off the same admin
screen, each needs to know which screen that is, and a plugin that wants one
usually wants the others — so adding a "Sales" column means one hook to
register it, another to render it, a third to make it sortable, and a fourth
to fix the ordering, before any of the same again for the filter beside it.

This is the declaration instead: describe the column, and the four hooks are
wired for you.

## Features

* Add a column to any core list table, from a meta key or a callback
* Show a thumbnail — a featured image, an avatar, or one stored in meta
* Make it sortable, including numerically, without writing the query
* Set its width and where it sits relative to the existing columns
* Add a dropdown filter above the table, and have it applied to the query
* Add a bulk action, with a capability and a notice reporting what it did
* Add a row action, running over ajax so the page does not reload
* Remove a core column you do not want

## Installation

```bash
composer require arraypress/wp-register-columns
```

## Quick start

A sales column on downloads, sortable, next to the title:

```php
register_post_columns( 'download', [
	'sales' => [
		'label'    => __( 'Sales', 'my-plugin' ),
		'meta_key' => '_sales',
		'position' => 'after:title',
		'sortable' => true,
		'numeric'  => true,
		'width'    => '90px',
	],
] );
```

`numeric` is the one that catches people out: without it, sorting is
alphabetical and 9 comes after 10.

## Image columns

`image` shows a thumbnail. With no `meta_key` the table uses its own natural
image — the featured image on a post, the avatar on a user or comment, the
file itself on a media item — which is the same relationship core has between
the users table and the avatar it puts in the username column:

```php
register_post_columns( 'download', [
	'thumb' => [
		'label'    => __( 'Image', 'my-plugin' ),
		'image'    => true,
		'position' => 'before:title',
	],
] );
```

With a `meta_key`, whatever is stored there is the image. An attachment id and
a URL both work, since both are things people store:

```php
register_taxonomy_columns( 'product_brand', [
	'logo' => [
		'label'    => __( 'Logo', 'my-plugin' ),
		'image'    => true,
		'meta_key' => 'brand_logo',
	],
] );
```

`image_size` takes square pixels, a registered size name, or a `[ width,
height ]` pair. It defaults to 32 — the size core uses for the avatar, so an
image column sitting next to a core one lines up with it.

A term has no natural image, so a term column needs a `meta_key`. An empty
value, a post with no featured image, and an id whose attachment has since
been deleted all show the same dash every other empty cell shows, rather than
a broken image.

The other three take the same shape:

```php
register_post_list_filters( 'download', [ /* ... */ ] );
register_post_bulk_actions( 'download', [ /* ... */ ] );
register_post_row_actions( 'download', [ /* ... */ ] );
```

`register_user_*`, `register_taxonomy_*`, `register_comment_*` and
`register_media_*` exist for each.

## Requirements

* PHP 8.3 or later
* WordPress 7.1 or later

## License

GPL-2.0-or-later
