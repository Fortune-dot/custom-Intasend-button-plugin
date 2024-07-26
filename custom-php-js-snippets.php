<?php
/*
Plugin Name: IntaSend Snippet Shortcode
Description: Allows adding IntaSend payment button and configuration via shortcodes
Version: 2.2
Author: Fortune Dev
*/

// Prevent direct access to the plugin file
if (!defined('ABSPATH')) {
    exit;
}

class IntaSendSnippetShortcode {
    private $options;

    public function __construct() {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
        add_shortcode('intasend_snippet', array($this, 'intasend_snippet_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function add_plugin_page() {
        add_options_page(
            'IntaSend Settings',
            'IntaSend Settings',
            'manage_options',
            'intasend-setting-admin',
            array($this, 'create_admin_page')
        );
    }

    public function create_admin_page() {
        $this->options = get_option('intasend_options');
        ?>
        <div class="wrap">
            <h1>IntaSend Settings</h1>
            <form method="post" action="options.php">
            <?php
                settings_fields('intasend_option_group');
                do_settings_sections('intasend-setting-admin');
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    public function page_init() {
        register_setting(
            'intasend_option_group',
            'intasend_options',
            array($this, 'sanitize')
        );

        add_settings_section(
            'intasend_setting_section',
            'IntaSend Settings',
            array($this, 'section_info'),
            'intasend-setting-admin'
        );

        add_settings_field(
            'public_api_key',
            'Public API Key',
            array($this, 'public_api_key_callback'),
            'intasend-setting-admin',
            'intasend_setting_section'
        );

        add_settings_field(
            'environment',
            'Environment',
            array($this, 'environment_callback'),
            'intasend-setting-admin',
            'intasend_setting_section'
        );

        add_settings_field(
            'redirect_urls',
            'Redirect URLs',
            array($this, 'redirect_urls_callback'),
            'intasend-setting-admin',
            'intasend_setting_section'
        );
    }

    public function sanitize($input) {
        $sanitary_values = array();
        if (isset($input['public_api_key'])) {
            $sanitary_values['public_api_key'] = sanitize_text_field($input['public_api_key']);
        }
        if (isset($input['environment'])) {
            $sanitary_values['environment'] = $input['environment'];
        }
        if (isset($input['redirect_urls']) && is_array($input['redirect_urls'])) {
            foreach ($input['redirect_urls'] as $index => $url_data) {
                $sanitary_values['redirect_urls'][$index]['code'] = sanitize_text_field($url_data['code']);
                $sanitary_values['redirect_urls'][$index]['url'] = sanitize_url($url_data['url']);
            }
        }
        return $sanitary_values;
    }

    public function section_info() {
        echo 'Enter your IntaSend settings below:';
    }

    public function public_api_key_callback() {
        printf(
            '<input type="text" id="public_api_key" name="intasend_options[public_api_key]" value="%s" />',
            isset($this->options['public_api_key']) ? esc_attr($this->options['public_api_key']) : ''
        );
    }

    public function environment_callback() {
        $selected = isset($this->options['environment']) ? $this->options['environment'] : 'test';
        ?>
        <select name="intasend_options[environment]" id="environment">
            <option value="test" <?php selected($selected, 'test'); ?>>Test</option>
            <option value="live" <?php selected($selected, 'live'); ?>>Live</option>
        </select>
        <?php
    }

    public function redirect_urls_callback() {
        $redirect_urls = isset($this->options['redirect_urls']) ? $this->options['redirect_urls'] : array();
        echo '<div id="redirect-urls-container">';
        if (!empty($redirect_urls)) {
            foreach ($redirect_urls as $index => $url) {
                echo '<div class="redirect-url-entry">';
                echo '<input type="text" name="intasend_options[redirect_urls][' . $index . '][code]" value="' . esc_attr($url['code']) . '" placeholder="Code" />';
                echo '<input type="text" name="intasend_options[redirect_urls][' . $index . '][url]" value="' . esc_attr($url['url']) . '" placeholder="Redirect URL" />';
                echo '<button type="button" class="remove-redirect-url">Remove</button>';
                echo '</div>';
            }
        }
        echo '</div>';
        echo '<button type="button" id="add-redirect-url">Add Redirect URL</button>';
    
        // Add JavaScript to handle dynamic addition and removal of redirect URL fields
        ?>
        <script>
        jQuery(document).ready(function($) {
            var container = $('#redirect-urls-container');
            var index = <?php echo count($redirect_urls); ?>;
    
            $('#add-redirect-url').on('click', function() {
                var html = '<div class="redirect-url-entry">' +
                    '<input type="text" name="intasend_options[redirect_urls][' + index + '][code]" placeholder="Code" />' +
                    '<input type="text" name="intasend_options[redirect_urls][' + index + '][url]" placeholder="Redirect URL" />' +
                    '<button type="button" class="remove-redirect-url">Remove</button>' +
                    '</div>';
                container.append(html);
                index++;
            });
    
            $(document).on('click', '.remove-redirect-url', function() {
                $(this).parent().remove();
            });
        });
        </script>
        <?php
    }

    public function intasend_snippet_shortcode($atts, $content = null) {
        $options = get_option('intasend_options');
        $public_api_key = isset($options['public_api_key']) ? $options['public_api_key'] : '';
        $environment = isset($options['environment']) ? $options['environment'] : 'test';
        $redirect_urls = isset($options['redirect_urls']) ? $options['redirect_urls'] : array();
    
        // Parse shortcode attributes
        $atts = shortcode_atts(
            array(
                'amount' => '0',
                'currency' => 'KES',
                'id' => uniqid('intasend_'),
                'redirect_code' => ''
            ),
            $atts,
            'intasend_snippet'
        );
    
        $success_redirect_url = '';
        if (!empty($atts['redirect_code'])) {
            foreach ($redirect_urls as $url_data) {
                if ($url_data['code'] === $atts['redirect_code']) {
                    $success_redirect_url = $url_data['url'];
                    break;
                }
            }
        }
    
        $html = '<script src="https://unpkg.com/intasend-inlinejs-sdk@4.0.0/build/intasend-inline.js"></script>';
            // Add CSS for the button
            $html .= '<style>
            .intasend-payment-button {
                background-color: #007bff;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
            }
            .intasend-payment-button:hover {
                background-color: #0056b3;
            }
        </style>';
        $html .= '<div id="' . esc_attr($atts['id']) . '">' . $content . '</div>';
        
        $js = "
        document.addEventListener('DOMContentLoaded', function() {
            var button = document.querySelector('#" . esc_js($atts['id']) . " .intasend-payment-button');
            if (button) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    new window.IntaSend({
                        publicAPIKey: '" . esc_js($public_api_key) . "',
                        live: " . ($environment === 'live' ? 'true' : 'false') . "
                    })
                    .on('COMPLETE', (results) => {
                        console.log('Payment completed', results);
                        " . ($success_redirect_url ? "window.location.href = '" . esc_js($success_redirect_url) . "';" : "") . "
                    })
                    .on('FAILED', (results) => {console.log('Payment failed', results)})
                    .on('IN-PROGRESS', (results) => {console.log('Payment in progress', results)})
                    .run({
                        amount: " . esc_js($atts['amount']) . ",
                        currency: '" . esc_js($atts['currency']) . "'
                    });
                });
            }
        });
        ";
    
        $html .= "<script>{$js}</script>";
    
        return $html;
    }

    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
    }
}

$intasend_snippet_shortcode = new IntaSendSnippetShortcode();