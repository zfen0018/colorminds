<?php
defined('ABSPATH') or die;

define('PLUGIN_TAG_TOKEN', '%%plugin_tag_token%%');
define('PLUGIN_TOKEN_TYPE_WORD', 0);
define('PLUGIN_TOKEN_TYPE_TAG', 1);
define('PLUGIN_TOKEN_TYPE_SPACE', 2);
define('PLUGIN_TOKEN_TYPE_IGNORE', 3);

if (!function_exists('_arr')) {
    /**
     * Get array value by specified key
     *
     * @param array      $array
     * @param string|int $key
     * @param mixed      $default
     *
     * @return mixed
     */
    function _arr(&$array, $key, $default = false) {
        if (isset($array[$key])) {
            return $array[$key];
        }
        return $default;
    }
}

if (!function_exists('plugin_get_logo')) {
    /**
     * Get logo options
     *
     * @param array $args Arguments
     */
    function plugin_get_logo($args) {
        $logo_src = '';
        $logo_width = '';
        $logo_height = '';
        $default_logo_src = '';
        $default_logo_width = '';
        $default_logo_height = '';

        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            @list($logo_src, $logo_width, $logo_height) = wp_get_attachment_image_src($custom_logo_id, 'full');
        }
        if (!empty($args['default_src'])) {
            if (preg_match('#^(http:|https:|//)#', $args['default_src'])) {
                $default_logo_src = $args['default_src'];
            } else {
                $default_logo_src = get_template_directory_uri() . $args['default_src'];
                @list($default_logo_width, $default_logo_height) = getimagesize(get_template_directory() . $args['default_src']);
            }
        }

        if ($logo_src !== '') {
            $args['default_src'] = $logo_src;
        }
        $parts = explode(".", $args['default_src']);
        $extension = end($parts);
        $isSvgFile = strtolower($extension) == 'svg' ? true : false;

        if ($isSvgFile) {
            $logo_width = '';
            $logo_height = '';
            if ($default_logo_width > $default_logo_height && $default_logo_width) {
                $logo_width = $default_logo_width;
            }
            if ($default_logo_width <= $default_logo_height && $default_logo_height) {
                $logo_height = $default_logo_height;
            }
        }

        if (!$logo_src) {
            $logo_src = $default_logo_src;
            $logo_width = $default_logo_width;
            $logo_height = $default_logo_height;
        }

        $logo_url = plugin_get_option('logo_link');
        if (!$logo_url) {
            $logo_url = $args['default_url'];
        }

        return array(
            'src' => $logo_src,
            'url' => $logo_url,
            'default_src' => $default_logo_src,
            'width' => $logo_width,
            'height' => $logo_height,
            'svg' => $isSvgFile,
        );
    }
}

global $plugin_default_options;

$plugin_default_options = array(
    'colors_css' => '',

    'fonts_css' => '',
    'fonts_link' => '',

    'typography_css' => '',

    'logo_width' => '',
    'logo_height' => '',
    'logo_link' => '',

    'menu_trim_title' => 1,
    'menu_trim_len' => 45,
    'submenu_trim_len' => 40,
    'menu_use_tag_filter' => 1,
    'menu_allowed_tags' => 'span, img',
    'use_default_menu' => '',

    'excerpt_auto' => 1,
    'excerpt_words' => 40,
    'excerpt_min_remainder' => 5,
    'excerpt_strip_shortcodes' => '',
    'excerpt_use_tag_filter' => 1,
    'excerpt_allowed_tags' => 'a, abbr, blockquote, b, cite, pre, code, em, label, i, p, strong, ul, ol, li, h1, h2, h3, h4, h5, h6, object, param, embed',
    'show_morelink' => 1,
    'morelink_template' => '<a href="[url]">[text]</a>',

    'include_jquery' => '',

    'seo_og' => 1,
    'seo_ld' => 1,

    'sidebars_layout_blog' => '',
    'sidebars_layout_post' => '',
    'sidebars_layout_default' => '',
);

if (!function_exists('plugin_get_option')) {
    /**
     * Get custom option
     *
     * @param string $name Option name
     *
     * @return bool
     */
    function plugin_get_option($name) {
        $result = get_theme_mod($name);
        if ($result === false) {
            global $plugin_default_options;
            $result = isset($plugin_default_options[$name]) ? $plugin_default_options[$name] : false;
        }
        return $result;
    }
}

if (!function_exists('plugin_trim_long_str')) {
    /**
     * Trim string
     *
     * @param string $str String
     * @param int    $len Length
     *
     * @return mixed
     */
    function plugin_trim_long_str($str, $len = 50) {
        $excerpt = plugin_create_excerpt($str, $len, 0, true);
        if ($excerpt) {
            return force_balance_tags($excerpt . '&hellip;');
        }
        return $str;
    }
}

if (!function_exists('plugin_create_excerpt')) {
    /**
     * Create excerpt
     *
     * @param string $excerpt          Excerpt
     * @param int    $max_tokens_count Max tokens count
     * @param int    $min_remainder    Min reminder
     * @param bool   $count_symbols    Count symbols
     *
     * @return bool|string
     */
    function plugin_create_excerpt($excerpt, $max_tokens_count, $min_remainder, $count_symbols = false) {
        $content_parts = explode(PLUGIN_TAG_TOKEN, str_replace(array('<', '>'), array(PLUGIN_TAG_TOKEN . '<', '>' . PLUGIN_TAG_TOKEN), $excerpt));
        $content = array();
        $tokens_count = 0;
        $style_balance = 0;
        $script_balance = 0;
        foreach ($content_parts as $part) {
            if (strpos($part, '<') !== false || strpos($part, '>') !== false) {
                if ($part === '<style>') {
                    $style_balance++;
                } else if ($part === '</style>') {
                    $style_balance--;
                } else if ($part === '<script>') {
                    $script_balance++;
                } else if ($part === '</script>') {
                    $script_balance--;
                }
                $content[] = array(PLUGIN_TOKEN_TYPE_TAG, $part);
            } else {
                $all_chunks = preg_split('/([\s])/u', $part, -1, PREG_SPLIT_DELIM_CAPTURE);
                foreach ($all_chunks as $chunk) {
                    if ('' != trim($chunk)) {
                        if ($style_balance > 0 || $script_balance > 0) {
                            $content[] = array(PLUGIN_TOKEN_TYPE_IGNORE, $chunk);
                        } else {
                            $content[] = array(PLUGIN_TOKEN_TYPE_WORD, $chunk);
                            $tokens_count += $count_symbols ? mb_strlen($chunk) : 1;
                        }
                    } elseif ($chunk != '') {
                        $tokens_count += $count_symbols ? 1 : 0;
                        $content[] = array(PLUGIN_TOKEN_TYPE_SPACE, $chunk);
                    }
                }
            }
        }

        if ($max_tokens_count < $tokens_count && $max_tokens_count + $min_remainder <= $tokens_count) {
            $current_count = 0;
            $excerpt = '';
            foreach ($content as $node) {
                if ($node[0] === PLUGIN_TOKEN_TYPE_WORD) {
                    $current_count += $count_symbols ? mb_strlen($node[1]) : 1;
                } else {
                    $current_count += $count_symbols ? 1 : 0;
                }
                if ($current_count >= $max_tokens_count && $excerpt) { // leave at least 1 token
                    break;
                }
                $excerpt .= $node[1];
            }
            return $excerpt;
        }
        return false;
    }
}

if (!function_exists('get_user_by')) :
    /**
     * Get user by
     *
     * @param string     $field The field to retrieve the user with. id | ID | slug | email | login.
     * @param int|string $value A value for $field. A user ID, slug, email address, or login name.
     *
     * @return WP_User|bool WP_User object on success, false on failure.
     */
    function get_user_by($field, $value) {
        $userdata = WP_User::get_data_by($field, $value);

        if (!$userdata) {
            return false;
        }

        $user = new WP_User;
        $user->init($userdata);

        return $user;
    }
endif;

if (!function_exists('plugin_get_post_short_description')) {
    /**
     * Get post short description
     *
     * @param WP_Post $post
     *
     * @return string
     */
    function plugin_get_post_short_description($post) {
        $description = wp_strip_all_tags(plugin_create_excerpt($post->post_content, 55, 1));
        if (!$description) {
            $description = wp_strip_all_tags($post->post_content);
        }
        return str_replace(array("\r", "\n"), ' ', $description);
    }
}

if (!function_exists('plugin_og_meta_tags')) {
    /**
     * Render Open Graph meta tags
     */
    function plugin_og_meta_tags() {
        if (!plugin_get_option('seo_og')) {
            return;
        }

        global $post;

        if (is_front_page() || is_home()) {
            $type = 'website';
        } else if (is_singular()) {
            $type = $post->post_type === 'product' ? 'product' : 'article';
        } else {
            $type = 'object';
        }
        if (is_singular()) {
            $title = $post->post_title;
            if (function_exists('np_data_provider')) {
                $data_provider = np_data_provider($post->ID);
                $description = $data_provider->getPageDescription();
            }
            if (empty($description)) {
                $description = plugin_get_post_short_description($post);
            }
            $url = get_permalink();
        }
        if (is_front_page()) {
            $url = home_url();
        }

        if (empty($title)) {
            $title = wp_get_document_title();
        }
        if (empty($description)) {
            $description = get_bloginfo('description', 'display');
        }

        if (empty($url)) {
            global $wp;
            $url = add_query_arg($wp->query_string, '', home_url($wp->request));
        }

        ?>
        <meta property="og:title" content="<?php echo esc_attr($title); ?>"/>
        <meta property="og:type" content="<?php echo $type; ?>"/>
        <meta property="og:url" content="<?php echo esc_attr($url); ?>"/>
        <meta property="og:site_name" content="<?php echo get_bloginfo('name'); ?>"/>
        <meta property="og:description" content="<?php echo esc_attr($description); ?>"/>

        <?php
        if (is_singular() && has_post_thumbnail($post->ID)) {
            $thumbnail_id = get_post_thumbnail_id($post->ID);
            $thumbnail_src = wp_get_attachment_image_src($thumbnail_id, 'full');

            $alt = trim(strip_tags(get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true)));
            if (isset($thumbnail_src[0])) {
                echo '<meta property="og:image" content="' . esc_url($thumbnail_src[0]) . '">' . "\n";
            }
            if (isset($thumbnail_src[1])) {
                echo '<meta property="og:image:width" content="' . $thumbnail_src[1] . '">' . "\n";
            }
            if (isset($thumbnail_src[2])) {
                echo '<meta property="og:image:height" content="' . $thumbnail_src[2] . '">' . "\n";
            }
            if ($alt) {
                echo '<meta property="og:image:alt" content="' . $alt . '">' . "\n";
            }
        }

        $twitter_account = apply_filters('plugin_twitter_account', '');
        if ($twitter_account) {
            ?>
            <meta name="twitter:site" content="<?php echo esc_attr($twitter_account); ?>">
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:title" content="<?php echo esc_attr($title); ?>">
            <meta name="twitter:description" content="<?php echo esc_attr($description); ?>">
            <?php
        }
    }
}

if (!function_exists('add_meta_open_graph')) {
    /**
     * @param object $data_provider
     */
    function add_meta_open_graph($data_provider) {
        $pageOpenGraph = $data_provider->getPageOpenGraph();
        if (Nicepage::isNpTheme()) {
            if ($data_provider->isNicepage()) {
                if ($pageOpenGraph === 0) {
                    remove_action('wp_head', 'theme_og_meta_tags', 5);
                }
            }
        } else {
            if ($pageOpenGraph === 1) {
                plugin_og_meta_tags();
            }
        }
    }
}

require_once dirname(__FILE__) . '/includes/class-np-data-provider.php';
require_once dirname(__FILE__) . '/options.php';
require_once dirname(__FILE__) . '/includes/class-nicepage.php';
require_once dirname(__FILE__) . '/includes/class-np-forms.php';