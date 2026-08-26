# Register Columns

Add columns, filters, bulk actions and row actions to WordPress's own list
tables — posts, users, terms, comments and media.

Four things that sound separate and are not: they all hang off the same admin
screen, they all need to know which screen that is, and a plugin that wants one
usually wants the others. They were four packages until they weren't.

## Install

```bash
composer require arraypress/wp-register-columns
```

Requires PHP 8.3 and WordPress 5.0.

## Columns

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

`register_user_columns()`, `register_taxonomy_columns()`,
`register_comment_columns()` and `register_media_columns()` take the same
shape. The post and taxonomy ones take a slug or a list of them; the others
have only one table each.

Every function takes a third argument of columns to remove:

```php
register_post_columns( 'page', $columns, [ 'comments' ] );
```

### Options

| Option                | Type     | What it does                                                        |
| --------------------- | -------- | ------------------------------------------------------------------- |
| `label`               | string   | The column header. Escaped for you.                                  |
| `meta_key`            | string   | Meta to read the value from.                                         |
| `position`            | string   | `after:title`, `before:date`. Appended if absent or unmatched.       |
| `sortable`            | bool     | Offer the column as sortable.                                        |
| `numeric`             | bool     | Sort by `meta_value_num`, so 9 sorts before 80.                      |
| `desc_first`          | bool     | Sort descending on the first click.                                  |
| `sortby`              | string   | Sort by a property of the object instead of by meta.                 |
| `display_callback`    | callable | What the cell contains. See below.                                   |
| `permission_callback` | callable | Whether this user sees the column at all.                            |
| `width`               | string   | A CSS length, a percentage, or `auto`. Anything else is ignored.     |

`numeric` and `desc_first` are separate on purpose. `numeric` is about *how*
to compare — a meta value sorted as text puts 9 after 80 — and `desc_first` is
about which end to start from. They used to be one key, so every column
declared numeric also started descending, which is not what the word means.

### Display callbacks

A callback with a `meta_key` is given the value first, then the object id:

```php
'display_callback' => function ( $value, int $post_id ): string {
	return $value ? esc_html( $value ) : '—';
},
```

Without one it is given only the id:

```php
'display_callback' => fn( int $post_id ): string => get_edit_post_link( $post_id ),
```

What a callback returns is used as it is, so a column can render a badge or a
link. That also means it is yours to escape. Without a callback the stored
value is escaped and printed, and an empty one shows a placeholder with a
label for screen readers rather than a blank cell — a blank cell in a list
table reads as a column that failed to load.

A stored `0` is a value, not an absence, and is printed.

### Permissions

Without a `permission_callback` a column requires `manage_options`, which is
stronger than most columns want. Say what you mean:

```php
'permission_callback' => fn(): bool => current_user_can( 'edit_others_posts' ),
```

## Filters

Dropdowns above the list, and the query change that goes with them:

```php
register_post_list_filters( 'post', [
	'state' => [
		'label'   => __( 'All states', 'my-plugin' ),
		'options' => [ 'live' => 'Live', 'draft' => 'Draft' ],
	],
] );
```

The label is the empty first option — "All states" — not a heading above the
control, which is what core's own filters do and half the height.

| Option           | Type     | What it does                                                    |
| ---------------- | -------- | --------------------------------------------------------------- |
| `label`          | string   | The unfiltered option.                                           |
| `options`        | array    | Value => label. Or a value => `[ 'label' => …, 'count' => … ]`.  |
| `taxonomy`       | string   | Fill the options from a taxonomy's terms instead, at render time. |
| `show_count`     | bool     | Show how many rows each option matches.                          |
| `hide_empty`     | bool     | For a taxonomy, leave out terms nothing uses. Default true.      |
| `capability`     | string   | Who sees the filter.                                             |
| `query_callback` | callable | Do the filtering yourself, for anything a meta compare cannot express. |

Without a `query_callback` a filter compares against meta of the same name.
With one, you get the query and the value:

```php
'query_callback' => function ( $query, string $value ): void {
	$query->set( 'meta_query', [ [ 'key' => '_views', 'value' => 1000, 'compare' => '>=' ] ] );
},
```

`register_user_list_filters()` does the same for the users table.

Registering filters twice for the same table adds to them rather than
replacing them, so two plugins can each add one.

## Bulk actions

```php
register_post_bulk_actions( 'download', [
	'archive' => [
		'label'      => __( 'Archive', 'my-plugin' ),
		'capability' => 'edit_others_posts',
		'callback'   => function ( array $post_ids ): int {
			foreach ( $post_ids as $post_id ) {
				update_post_meta( $post_id, '_archived', true );
			}

			return count( $post_ids );
		},
	],
] );
```

The callback is handed every selected id and returns how many it dealt with.
That count is what the notice afterwards reports, so an action that skips
half its rows says so.

`register_user_bulk_actions()`, `register_taxonomy_bulk_actions()`,
`register_comment_bulk_actions()` and `register_media_bulk_actions()` take the
same shape.

A `capability` keeps the action out of the dropdown for anybody who may not
use it. An action that appears and then refuses reads as a broken feature
rather than a locked one.

## Row actions

The links under a row's title — Edit, Trash, View — and yours beside them.

```php
register_post_row_actions( 'download', [
	'duplicate' => [
		'label'      => __( 'Duplicate', 'my-plugin' ),
		'capability' => 'edit_posts',
		'ajax'       => true,
		'confirm'    => __( 'Duplicate this download?', 'my-plugin' ),
		'callback'   => function ( int $post_id ): string {
			// ... do the work ...
			return __( 'Duplicated.', 'my-plugin' );
		},
	],
] );
```

`ajax => true` runs the callback over admin-ajax and replaces the row action's
text with whatever it returns, so nothing reloads. Without it the action is an
ordinary link and `url` says where it goes.

`register_user_row_actions()`, `register_taxonomy_row_actions()`,
`register_comment_row_actions()` and `register_media_row_actions()` take the
same shape.

## Screens

The one thing all three halves need, and the reason they are one library:

```php
use ArrayPress\RegisterColumns\Support\Screen;

Screen::id( 'post', 'download' );   // edit-download
Screen::id( 'media' );              // upload
Screen::is( 'term', 'category' );   // on the category list, not the term editor
```

None of these is guessable — a post list is `edit-{post_type}` but the media
list is `upload`, users is `users` but comments is `edit-comments` — and every
one is checked against a real `WP_Screen` in the tests.

## EDD

[edd-register-columns](https://github.com/arraypress/edd-register-columns)
builds on this for Easy Digital Downloads' own tables: orders, customers,
discounts, downloads, licences, subscriptions and commissions.

## Testing

```bash
composer test          # phpunit
composer lint          # phpcs, defect sniffs
composer format:check  # phpcs, formatting
```
