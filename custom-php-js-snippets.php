<?php
/*
Plugin Name: IntaSend Snippet Shortcode
Description: Allows adding IntaSend payment button and configuration via shortcodes
Version: 2.0
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
            'success_redirect_url',
            'Success Redirect URL',
            array($this, 'success_redirect_url_callback'),
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
        if (isset($input['success_redirect_url'])) {
            $sanitary_values['success_redirect_url'] = sanitize_url($input['success_redirect_url']);
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
        ?>
        <select name="intasend_options[environment]" id="environment">
            <option value="test" <?php selected($this->options['environment'], 'test'); ?>>Test</option>
            <option value="live" <?php selected($this->options['environment'], 'live'); ?>>Live</option>
        </select>
        <?php
    }

    public function success_redirect_url_callback() {
        printf(
            '<input type="text" id="success_redirect_url" name="intasend_options[success_redirect_url]" value="%s" />',
            isset($this->options['success_redirect_url']) ? esc_attr($this->options['success_redirect_url']) : ''
        );
    }

    public function intasend_snippet_shortcode($atts, $content = null) {
        $options = get_option('intasend_options');
        $public_api_key = isset($options['public_api_key']) ? $options['public_api_key'] : '';
        $environment = isset($options['environment']) ? $options['environment'] : 'test';
        $success_redirect_url = isset($options['success_redirect_url']) ? $options['success_redirect_url'] : '';

        $html = '<script src="https://unpkg.com/intasend-inlinejs-sdk@4.0.0/build/intasend-inline.js"></script>';
        $html .= $content;
        
        $js = "
        new window.IntaSend({
            publicAPIKey: '{$public_api_key}',
            live: " . ($environment === 'live' ? 'true' : 'false') . "
        })
        .on('COMPLETE', (results) => {
            console.log('Payment completed', results);
            " . ($success_redirect_url ? "window.location.href = '{$success_redirect_url}';" : "") . "
        })
        .on('FAILED', (results) => {console.log('Payment failed', results)})
        .on('IN-PROGRESS', (results) => {console.log('Payment in progress', results)});
        ";

        $html .= "<script>{$js}</script>";

        return $html;
    }

    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
    }
}

$intasend_snippet_shortcode = new IntaSendSnippetShortcode();