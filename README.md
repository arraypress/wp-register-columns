# WordPress Register Custom Columns Library

The WordPress Register Custom Columns Library simplifies the registration of custom columns in WordPress. This library streamlines the process of setting up custom columns for different metadata types (e.g., 'user', 'post'), including their labels, capabilities, and supports. It provides a structured and extendable approach to declaring new columns, ensuring consistency and reducing boilerplate code across projects.

## Features

- **Easy Registration**: Register custom columns with minimal code.
- **Customizable Labels**: Customizable column labels for enhanced admin UI integration.
- **Sortable Columns**: Supports sortable columns with text or numeric sorting.
- **Inline Editing**: Inline editing support for specific columns.
- **Best Practices**: Enforces best practices by setting public visibility flags appropriately.
- **AJAX Support**: Handles AJAX requests for inline editing and metadata updates.
- **Flexible Configuration**: Allows setting column width, custom display callbacks, and more.

## Minimum Requirements

- **PHP**: 7.4 or higher
- **WordPress**: 5.0 or higher

## Installation

This library is a developer tool, not a WordPress plugin, so it needs to be included in your WordPress project or plugin.

You can install it using Composer:

```bash
composer require arraypress/register-custom-columns
```

## Basic Usage

```php
use ArrayPress\RegisterCustomColumns\Utils\ColumnHelper;
```

### Registering Post Columns

```php
use function ArrayPress\RegisterCustomColumns\register_post_columns;

/**
 * Example for Posts: Display Thumbnail Image
 *
 * This example demonstrates how to display a custom column in the posts table
 * that shows the thumbnail image.
 */
$custom_post_columns = [
    'thumbnail' => [
        'label'               => __( 'Thumbnail', 'text-domain' ),
        'display_callback'    => function ( $value, $post_id, $column ) {
            $thumbnail_id = get_post_thumbnail_id( $post_id );
            return ColumnHelper::image_thumbnail( $thumbnail_id, [64, 64] );
        },
        'position'            => 'before:title',
        'permission_callback' => function () {
            return current_user_can( 'edit_posts' );
        }
    ],
];

register_post_columns( [ 'post', 'page' ], $custom_post_columns );
```

### Registering Comment Columns

```php
use function ArrayPress\RegisterCustomColumns\register_comment_columns;

/**
 * Example for Comments: Display Comment Word Count
 *
 * This example demonstrates how to display a custom column in the comments table
 * that shows the word count of each comment. The word count is formatted using
 * the `number_format_i18n` function to ensure proper localization.
 */
$custom_comment_columns = [
    'comment_word_count' => [
        'label'               => __( 'Word Count', 'text-domain' ),
        'display_callback'    => function ( $value, $comment_id, $column ) {
            $comment = get_comment( $comment_id );
            $word_count = str_word_count( $comment->comment_content );
            return number_format_i18n( $word_count );
        },
        'position'            => 'after:author',
        'permission_callback' => function () {
            return current_user_can( 'moderate_comments' );
        }
    ],
];
register_comment_columns( $custom_comment_columns );
```

### Registering Taxonomy Columns

```php
use function ArrayPress\RegisterCustomColumns\register_taxonomy_columns;

/**
 * Example for Taxonomy: Display Color
 *
 * This example demonstrates how to display a custom column in the taxonomy terms table
 * that shows a color field. The color field supports inline editing.
 */
$custom_taxonomy_columns = [
    'color' => [
        'label'            => __( 'Color', 'text-domain' ),
        'meta_key'         => 'color_meta',
        'inline_edit'      => true,
        'inline_attributes' => [
            'type'  => 'color',
        ],
        'display_callback' => function ( $value, $term_id, $column ) {
            return ColumnHelper::color_circle( $value );
        },
        'permission_callback' => function () {
            return current_user_can( 'manage_categories' );
        }
    ],
];

register_taxonomy_columns( [ 'category', 'post_tag' ], $custom_taxonomy_columns );
```

### Registering Media Library Columns

```php
use function ArrayPress\RegisterCustomColumns\register_media_columns;

/**
 * Example for Media: Display File Size
 *
 * This example demonstrates how to display a custom column in the media table
 * that shows the file size of each attachment. The file size is formatted using
 * the appropriate helper method to ensure proper display.
 */
$custom_media_columns = [
    'file_size' => [
        'label'               => __( 'File Size', 'text-domain' ),
        'display_callback'    => function ( $value, $attachment_id, $column ) {
            return ColumnHelper::attachment_file_size( $attachment_id );
        },
        'position'            => 'after:author',
        'permission_callback' => function () {
            return current_user_can( 'upload_files' );
        }
    ],
];

register_media_columns( $custom_media_columns );
```

### Registering Media Library Columns

```php
use function ArrayPress\RegisterCustomColumns\register_user_columns;

/**
 * Example for Users: Display and Edit Points
 *
 * This example demonstrates how to display a custom column in the users table
 * that shows the points for each user. The credits are editable inline with
 * numeric input.
 */
$custom_user_columns = [
    'points' => [
        'label'               => __( 'Points', 'text-domain' ),
        'meta_key'            => 'points',
        'inline_edit'         => true,
        'inline_attributes'   => [
            'type' => 'number',
        ],
        'display_callback'    => function ( $value, $user_id, $column ) {
            return ColumnHelper::badge( number_format_i18n( $value ), '#4caf50', '#ffffff' );
        },
        'permission_callback' => function () {
            return current_user_can( 'edit_users' );
        },
    ],
];

register_user_columns( $custom_user_columns );
```

## Features Breakdown

### Custom Column Positioning

The `position` parameter allows you to specify where the custom column should appear relative to existing columns. It supports the following formats:

- `before:{column}`: Place the custom column before the specified column.
- `after:{column}`: Place the custom column after the specified column.

#### Examples:

```php
// Place custom column before the title column
'position' => 'before:title'

// Place custom column after the author column
'position' => 'after:author'
```

### Inline Editing (AJAX Mode)

The library supports inline editing for specific columns, enabling a smoother user experience. You can define attributes like type, color, etc., for the inline edit fields.

#### Example:

```php
$custom_columns = [
 'color' => [
     'label'            => __( 'Color', 'text-domain' ),
     'meta_key'         => 'color_meta',
     'inline_edit'      => true,
     'inline_attributes' => [
         'type'  => 'color',
         'style' => 'width: 60px;'
     ],
     'display_callback' => function ( $value, $post_id, $column ) {
         return '<div style="background-color: ' . esc_attr( $value ) . '; width: 20px; height: 20px;"></div>';
     },
     'permission_callback' => function () {
         return current_user_can( 'edit_posts' );
     }
 ],
];
register_post_columns( 'post', $custom_columns );
```

When a user clicks on the column value, an inline edit field will appear, allowing them to change the value directly.

### Customizable Column Labels

Easily customize the labels of your columns to better fit the admin UI. This ensures a consistent and user-friendly experience.

```php
'label' => __( 'Custom Label', 'text-domain' )
```

### Sortable Columns

The library supports sortable columns, which can be sorted by text or numeric values. This feature enhances the usability of the columns by allowing users to organize data as needed.

#### Example:

```php
'sortable' => true,
'numeric'  => true,  For numeric sorting
```

### AJAX Support for Metadata Updates

Handle AJAX requests for inline editing and metadata updates seamlessly. This improves the efficiency of data management in the WordPress admin panel.

## Helper Functions

### Image Thumbnail

Generates an image thumbnail based on an attachment ID.

```php
ColumnHelper::image_thumbnail( int $attachment_id, $size = 'thumbnail', array $atts = [] ): string
```

### Formatted Word Count

Generates a formatted word count.

```php
ColumnHelper::formatted_word_count( int $word_count ): string
```

## Contributions

Contributions to this library are highly appreciated. Raise issues on GitHub or submit pull requests for bug fixes or new features. Share feedback and suggestions for improvements.

## License

GPLv2 or later

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
