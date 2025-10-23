# WordPress Register Columns

A lightweight library for registering custom columns in WordPress admin tables. This library provides a clean, simple API for adding display-only columns to posts, users, taxonomies, comments, and media without the bloat of inline editing or complex UI components.

## Features

- **Simple API**: Register custom columns with minimal code
- **Column Positioning**: Position columns before or after existing columns
- **Sortable Columns**: Support for text and numeric sorting
- **Display Callbacks**: Full control over column content rendering
- **Permission Callbacks**: Control column visibility based on user capabilities
- **Width Control**: Set custom column widths
- **Multiple Post Types**: Register same columns across multiple post types or taxonomies
- **Lightweight**: No AJAX, no inline editing, just clean display functionality

## Requirements

- PHP 7.4 or higher
- WordPress 5.0 or higher

## Installation

Install via Composer:

```bash
composer require arraypress/wp-register-columns
```

## Basic Usage

### Post Columns

Register custom columns for posts or custom post types:

```php
register_post_columns( 'post', [
    'views' => [
        'label'            => __( 'Views', 'textdomain' ),
        'meta_key'         => 'post_views',
        'sortable'         => true,
        'numeric'          => true,
        'position'         => 'after:title',
        'display_callback' => function( $value, $post_id ) {
            return number_format_i18n( (int) $value );
        },
        'width' => '80px'
    ]
] );
```

### Multiple Post Types

Register the same columns across multiple post types:

```php
register_post_columns( [ 'post', 'page', 'custom_post_type' ], [
    'status' => [
        'label'            => __( 'Status', 'textdomain' ),
        'meta_key'         => 'custom_status',
        'position'         => 'before:date',
        'display_callback' => function( $value, $post_id ) {
            return ucfirst( $value );
        }
    ]
] );
```

### User Columns

Register custom columns for users:

```php
register_user_columns( [
    'points' => [
        'label'            => __( 'Points', 'textdomain' ),
        'meta_key'         => 'user_points',
        'sortable'         => true,
        'numeric'          => true,
        'position'         => 'after:email',
        'display_callback' => function( $value, $user_id ) {
            return number_format_i18n( (int) $value );
        }
    ]
] );
```

### Taxonomy Columns

Register custom columns for taxonomies:

```php
register_taxonomy_columns( [ 'category', 'post_tag' ], [
    'color' => [
        'label'            => __( 'Color', 'textdomain' ),
        'meta_key'         => 'term_color',
        'position'         => 'after:name',
        'display_callback' => function( $value, $term_id ) {
            if ( empty( $value ) ) {
                return '—';
            }
            return sprintf(
                '<span style="display:inline-block;width:20px;height:20px;background:%s;border-radius:50%%;"></span>',
                esc_attr( $value )
            );
        }
    ]
] );
```

### Comment Columns

Register custom columns for comments:

```php
register_comment_columns( [
    'rating' => [
        'label'            => __( 'Rating', 'textdomain' ),
        'meta_key'         => 'comment_rating',
        'sortable'         => true,
        'numeric'          => true,
        'position'         => 'after:author',
        'display_callback' => function( $value, $comment_id ) {
            return str_repeat( '★', (int) $value );
        }
    ]
] );
```

### Media Columns

Register custom columns for media library:

```php
register_media_columns( [
    'dimensions' => [
        'label'            => __( 'Dimensions', 'textdomain' ),
        'position'         => 'after:title',
        'display_callback' => function( $attachment_id ) {
            $meta = wp_get_attachment_metadata( $attachment_id );
            if ( empty( $meta['width'] ) || empty( $meta['height'] ) ) {
                return '—';
            }
            return sprintf( '%d × %d', $meta['width'], $meta['height'] );
        },
        'width' => '100px'
    ]
] );
```

## Column Configuration Options

Each column accepts the following configuration options:

| Option                | Type     | Description                                           |
|-----------------------|----------|-------------------------------------------------------|
| `label`               | string   | Column header label                                   |
| `meta_key`            | string   | Meta key to retrieve value from                       |
| `position`            | string   | Column position (e.g., 'after:title', 'before:date') |
| `sortable`            | bool     | Whether the column is sortable                        |
| `numeric`             | bool     | Whether to sort numerically                           |
| `sortby`              | string   | Custom orderby parameter for sorting                  |
| `display_callback`    | callable | Function to render column content                     |
| `permission_callback` | callable | Function to check if user can see column             |
| `width`               | string   | CSS width value (e.g., '100px', '10%')               |

## Display Callbacks

Display callbacks receive different parameters based on the table type:

### With Meta Key
```php
'display_callback' => function( $value, $object_id ) {
    // $value is the meta value
    // $object_id is the post/user/term/comment ID
    return esc_html( $value );
}
```

### Without Meta Key
```php
'display_callback' => function( $object_id ) {
    // $object_id is the post/user/term/comment ID
    // Retrieve any data you need
    return 'Custom output';
}
```

## Advanced Sorting

### Sort by Meta Key
```php
'meta_key'  => 'views_count',
'sortable'  => true,
'numeric'   => true
```

### Sort by Custom Field
```php
'sortby'    => 'meta_value_datetime',
'sortable'  => true
```

### Sort by Post Property
```php
'sortby'    => 'comment_count',
'sortable'  => true,
'numeric'   => true
```

## Permission Control

Control column visibility based on user capabilities:

```php
register_post_columns( 'post', [
    'internal_notes' => [
        'label'               => __( 'Internal Notes', 'textdomain' ),
        'meta_key'            => 'internal_notes',
        'position'            => 'after:title',
        'permission_callback' => function() {
            return current_user_can( 'edit_others_posts' );
        }
    ]
] );
```

## Removing Existing Columns

Remove unwanted default columns:

```php
register_post_columns( 'post', [
    'custom_column' => [
        'label'    => __( 'Custom', 'textdomain' ),
        'position' => 'after:title'
    ]
], [ 'tags', 'comments' ] ); // Remove tags and comments columns
```

## Examples

### Featured Image Column

```php
register_post_columns( 'post', [
    'thumbnail' => [
        'label'            => '',
        'position'         => 'before:title',
        'display_callback' => function( $post_id ) {
            $thumbnail_id = get_post_thumbnail_id( $post_id );
            if ( ! $thumbnail_id ) {
                return '—';
            }
            return wp_get_attachment_image( $thumbnail_id, [ 50, 50 ] );
        },
        'width' => '60px'
    ]
] );
```

### Word Count Column

```php
register_post_columns( 'post', [
    'word_count' => [
        'label'            => __( 'Words', 'textdomain' ),
        'sortable'         => true,
        'numeric'          => true,
        'position'         => 'after:title',
        'display_callback' => function( $post_id ) {
            $content = get_post_field( 'post_content', $post_id );
            $count   = str_word_count( strip_tags( $content ) );
            return number_format_i18n( $count );
        }
    ]
] );
```

### Last Login Column (Users)

```php
register_user_columns( [
    'last_login' => [
        'label'            => __( 'Last Login', 'textdomain' ),
        'meta_key'         => 'last_login',
        'sortable'         => true,
        'position'         => 'after:role',
        'display_callback' => function( $value, $user_id ) {
            if ( empty( $value ) ) {
                return '—';
            }
            return human_time_diff( (int) $value ) . ' ago';
        }
    ]
] );
```

## Best Practices

1. **Use Permission Callbacks**: Always check capabilities when displaying sensitive data
2. **Escape Output**: Use appropriate escaping functions in display callbacks
3. **Handle Empty Values**: Always check for empty values in display callbacks
4. **Use Width Sparingly**: Only set width when necessary
5. **Performance**: For expensive operations in display callbacks, consider caching

## Comparison with Original Library

This library is a complete rewrite focused on simplicity:

**Removed:**
- ❌ AJAX inline editing
- ❌ Metadata update/delete callbacks
- ❌ Sanitize callbacks
- ❌ Factory pattern complexity
- ❌ Built-in display helpers

**Kept:**
- ✅ Clean column registration
- ✅ Positioning system
- ✅ Sortable columns
- ✅ Display callbacks
- ✅ Permission callbacks
- ✅ Width control

**Result:** ~80% less code with clearer, more maintainable architecture.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

GPL-2.0-or-later

## Author

David Sherlock - [ArrayPress](https://arraypress.com/)

## Support

- [Documentation](https://github.com/arraypress/wp-register-columns)
- [Issue Tracker](https://github.com/arraypress/wp-register-columns/issues)