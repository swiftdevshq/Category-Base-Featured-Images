<?php
// 1. Add the Admin Page for Category Default Images
add_action('admin_menu', 'category_default_image_settings_page');
function category_default_image_settings_page() {
    add_menu_page(
        'Category Featured Images',
        'Category Images',
        'manage_options',
        'category-featured-images',
        'category_featured_images_callback',
        'dashicons-images-alt2',
        26
    );
}

// 2. Display Settings Page with Media Library Selector
function category_featured_images_callback() {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['submit'])) {
        $categories = get_categories(['hide_empty' => false]);
        foreach ($categories as $category) {
            if (isset($_POST['category_image_' . $category->term_id])) {
                update_option('category_default_image_id_' . $category->term_id, intval($_POST['category_image_' . $category->term_id]));
            }
        }
        echo "<div class='updated'><p>Default images updated.</p></div>";
    }

    echo '<form method="POST"><h1>Category Default Featured Images</h1>';
    $categories = get_categories(['hide_empty' => false]);
    foreach ($categories as $category) {
        $image_id = get_option('category_default_image_id_' . $category->term_id, '');
        $image_url = $image_id ? wp_get_attachment_url($image_id) : '';
        ?>
        <div style="margin-bottom: 20px;">
            <h2><?php echo esc_html($category->name); ?></h2>
            <img src="<?php echo esc_url($image_url); ?>" style="max-width:150px; display:block; margin-bottom:10px;" id="category-image-<?php echo $category->term_id; ?>-preview">
            <input type="hidden" name="category_image_<?php echo $category->term_id; ?>" id="category-image-<?php echo $category->term_id; ?>" value="<?php echo esc_attr($image_id); ?>">
            <button type="button" class="button" onclick="openMediaUploader(<?php echo $category->term_id; ?>)">Select Image</button>
            <button type="button" class="button" onclick="removeImage(<?php echo $category->term_id; ?>)">Remove Image</button>
        </div>
        <?php
    }
    submit_button();
    echo '</form>';
}

// 3. Add JavaScript for Media Uploader
add_action('admin_enqueue_scripts', 'category_featured_image_uploader_script');
function category_featured_image_uploader_script($hook) {
    if ($hook !== 'toplevel_page_category-featured-images') {
        return;
    }

    wp_enqueue_media();
    ?>
    <script>
    function openMediaUploader(catId) {
        var frame = wp.media({
            title: 'Select or Upload Image',
            button: { text: 'Use this image' },
            multiple: false
        });

        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            document.getElementById('category-image-' + catId).value = attachment.id;
            document.getElementById('category-image-' + catId + '-preview').src = attachment.url;
        });

        frame.open();
    }

    function removeImage(catId) {
        document.getElementById('category-image-' + catId).value = '';
        document.getElementById('category-image-' + catId + '-preview').src = '';
    }
    </script>
    <?php
}

// 4. Set Default Featured Image if None Exists
add_filter('get_post_metadata', 'apply_default_category_featured_image', 10, 4);
function apply_default_category_featured_image($metadata, $object_id, $meta_key, $single) {
    if ($meta_key !== '_thumbnail_id' || !empty($metadata)) return $metadata;

    $categories = get_the_category($object_id);
    if ($categories) {
        foreach ($categories as $category) {
            $default_image_id = get_option('category_default_image_id_' . $category->term_id);
            if ($default_image_id) {
                return $default_image_id;
            }
        }
    }
    return $metadata;
}

// 5. Enhanced Alt Text Handler for Default Featured Images
add_filter('post_thumbnail_html', 'set_dynamic_default_image_alt_text', 10, 5);
function set_dynamic_default_image_alt_text($html, $post_id, $post_thumbnail_id, $size, $attr) {
    // Get post title for alt text
    $post_title = get_the_title($post_id);
    
    // Check if this is a default category image
    $is_default_image = false;
    $categories = get_the_category($post_id);
    if ($categories) {
        foreach ($categories as $category) {
            $default_image_id = get_option('category_default_image_id_' . $category->term_id);
            if ($default_image_id && $default_image_id == $post_thumbnail_id) {
                $is_default_image = true;
                break;
            }
        }
    }
    
    // If it's a default image or we need to set alt text
    if ($is_default_image || !$post_thumbnail_id) {
        // Handle case where no featured image is set but default should be used
        if (!$post_thumbnail_id && $categories) {
            foreach ($categories as $category) {
                $default_image_id = get_option('category_default_image_id_' . $category->term_id);
                if ($default_image_id) {
                    $image_url = wp_get_attachment_image_src($default_image_id, $size);
                    if ($image_url) {
                        $alt_text = esc_attr($post_title);
                        $html = sprintf(
                            '<img src="%s" alt="%s" class="attachment-%s size-%s wp-post-image" />',
                            esc_url($image_url[0]),
                            $alt_text,
                            esc_attr($size),
                            esc_attr($size)
                        );
                        return $html;
                    }
                }
            }
        }
        // Handle case where featured image exists but is a default category image
        else if ($post_thumbnail_id) {
            // Update the alt attribute in existing HTML
            $alt_text = esc_attr($post_title);
            $html = preg_replace('/alt="[^"]*"/', 'alt="' . $alt_text . '"', $html);
            // If no alt attribute exists, add it
            if (strpos($html, 'alt=') === false) {
                $html = preg_replace('/<img/', '<img alt="' . $alt_text . '"', $html);
            }
        }
    }
    
    return $html;
}

// 6. Handle Alt Text for wp_get_attachment_image function calls
add_filter('wp_get_attachment_image_attributes', 'set_dynamic_attachment_alt_text', 10, 3);
function set_dynamic_attachment_alt_text($attr, $attachment, $size) {
    // Get current post ID
    $post_id = get_the_ID();
    if (!$post_id) return $attr;
    
    // Check if this attachment is a default category image for current post
    $categories = get_the_category($post_id);
    if ($categories) {
        foreach ($categories as $category) {
            $default_image_id = get_option('category_default_image_id_' . $category->term_id);
            if ($default_image_id && $default_image_id == $attachment->ID) {
                $attr['alt'] = esc_attr(get_the_title($post_id));
                break;
            }
        }
    }
    
    return $attr;
}

// 7. Handle Alt Text in Archive/Loop Context
add_filter('wp_get_attachment_image_attributes', 'set_archive_dynamic_alt_text', 15, 3);
function set_archive_dynamic_alt_text($attr, $attachment, $size) {
    // Only apply in archive/loop context where we might not have set alt text yet
    if (is_admin() || !in_the_loop()) return $attr;
    
    global $post;
    if (!$post || !isset($post->ID)) return $attr;
    
    // Check if this is a default category image and alt is not already set appropriately
    $categories = get_the_category($post->ID);
    if ($categories && (!isset($attr['alt']) || empty($attr['alt']) || $attr['alt'] === get_post_meta($attachment->ID, '_wp_attachment_image_alt', true))) {
        foreach ($categories as $category) {
            $default_image_id = get_option('category_default_image_id_' . $category->term_id);
            if ($default_image_id && $default_image_id == $attachment->ID) {
                $attr['alt'] = esc_attr(get_the_title($post->ID));
                break;
            }
        }
    }
    
    return $attr;
}

// 8. Handle the_post_thumbnail and get_the_post_thumbnail functions specifically
add_filter('post_thumbnail_html', 'ensure_dynamic_alt_in_thumbnails', 20, 5);
function ensure_dynamic_alt_in_thumbnails($html, $post_id, $post_thumbnail_id, $size, $attr) {
    if (empty($html)) return $html;
    
    // Get post title for alt text
    $post_title = get_the_title($post_id);
    
    // Check if current thumbnail is a default category image
    $categories = get_the_category($post_id);
    if ($categories && $post_thumbnail_id) {
        foreach ($categories as $category) {
            $default_image_id = get_option('category_default_image_id_' . $category->term_id);
            if ($default_image_id && $default_image_id == $post_thumbnail_id) {
                // Ensure alt text is the post title
                $alt_text = esc_attr($post_title);
                // Replace existing alt attribute or add if missing
                if (preg_match('/alt="[^"]*"/', $html)) {
                    $html = preg_replace('/alt="[^"]*"/', 'alt="' . $alt_text . '"', $html);
                } else {
                    $html = preg_replace('/<img/', '<img alt="' . $alt_text . '"', $html);
                }
                break;
            }
        }
    }
    
    return $html;
}

// 9. Additional helper function for theme developers
function get_category_default_image_with_dynamic_alt($category_id, $post_id = null, $size = 'thumbnail') {
    $post_id = $post_id ?: get_the_ID();
    $default_image_id = get_option('category_default_image_id_' . $category_id);
    
    if ($default_image_id && $post_id) {
        $post_title = get_the_title($post_id);
        return wp_get_attachment_image($default_image_id, $size, false, [
            'alt' => esc_attr($post_title)
        ]);
    }
    
    return '';
}
?>
