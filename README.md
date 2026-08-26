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
