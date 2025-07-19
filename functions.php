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
    // Only enqueue scripts for the custom settings page
    if ($hook !== 'toplevel_page_category-featured-images') {
        return;
    }

    wp_enqueue_media(); // Enqueue the WordPress media uploader

    ?>
    <script>
    // Open the media uploader and handle the selection
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

    // Remove selected image
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

// 5. Add Alt Text to Default Featured Images
add_filter('post_thumbnail_html', 'set_default_image_alt_text', 10, 5);
function set_default_image_alt_text($html, $post_id, $post_thumbnail_id, $size, $attr) {
    if (!$post_thumbnail_id) {
        $categories = get_the_category($post_id);
        if ($categories) {
            foreach ($categories as $category) {
                $default_image_id = get_option('category_default_image_id_' . $category->term_id);
                if ($default_image_id) {
                    $alt_text = esc_attr(get_the_title($post_id)); // Post title as alt text
                    $image_url = wp_get_attachment_image_src($default_image_id, $size);
                    if ($image_url) {
                        $html = sprintf(
                            '<img src="%s" alt="%s" class="attachment-%s size-%s" />',
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
    }
    return $html;
}
