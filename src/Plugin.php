<?php

namespace Listings;

use Listings\Admin\Admin;
use Listings\Widgets\FeaturedJobs;
use Listings\Widgets\RecentJobs;

class Plugin {

    /**
     * Constructor - get the plugin hooked in and ready
     */
    public function __construct() {
        if ( is_admin() ) {
            new Admin();
        }

        // Init classes
        $this->install = new Install();
        $this->post_types = new PostTypes();
        $this->ajax = new Ajax();
        $this->shortcodes = new Shortcodes();
        $this->api = new Api();
        $this->forms      = new Forms();
        $this->geocode = new Geocode();

        // Setup cache helper
        CacheHelper::init();

        // Activation - works with symlinks
        register_activation_hook( basename( dirname( __FILE__ ) ) . '/' . basename( __FILE__ ), array( $this, 'activate' ) );

        // Switch theme
        add_action( 'after_switch_theme', array( Ajax::class, 'add_endpoint' ), 10 );
        add_action( 'after_switch_theme', array( $this->post_types, 'register_post_types' ), 11 );
        add_action( 'after_switch_theme', 'flush_rewrite_rules', 15 );

        // Actions
        add_action( 'after_setup_theme', array( $this, 'load_plugin_textdomain' ) );
        add_action( 'widgets_init', array( $this, 'widgets_init' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );
        add_action( 'admin_init', array( $this, 'updater' ) );
    }

    /**
     * Called on plugin activation
     */
    public function activate() {
        Ajax::add_endpoint();
        $this->post_types->register_post_types();
        Install::install();
        flush_rewrite_rules();
    }

    /**
     * Handle Updates
     */
    public function updater() {
        if ( version_compare( JOB_MANAGER_VERSION, get_option( 'wp_job_manager_version' ), '>' ) ) {
            Install::install();
            flush_rewrite_rules();
        }
    }

    /**
     * Localisation
     */
    public function load_plugin_textdomain() {
        load_textdomain( 'wp-job-manager', WP_LANG_DIR . "/wp-job-manager/wp-job-manager-" . apply_filters( 'plugin_locale', get_locale(), 'wp-job-manager' ) . ".mo" );
        load_plugin_textdomain( 'wp-job-manager', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    /**
     * Widgets init
     */
    public function widgets_init() {
        register_widget( RecentJobs::class );
        register_widget( FeaturedJobs::class );
    }

    /**
     * Register and enqueue scripts and css
     */
    public function frontend_scripts() {
        $ajax_url         = Ajax::get_endpoint();
        $ajax_filter_deps = array( 'jquery', 'jquery-deserialize' );
        $ajax_data 		  = array(
            'ajax_url'                => $ajax_url,
            'is_rtl'                  => is_rtl() ? 1 : 0,
            'i18n_load_prev_listings' => __( 'Load previous listings', 'wp-job-manager' ),
        );

        // WPML workaround
        if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
            $ajax_data['lang'] = apply_filters( 'wpml_current_language', NULL );
        }

        if ( apply_filters( 'job_manager_chosen_enabled', true ) ) {
            wp_register_script( 'chosen', JOB_MANAGER_PLUGIN_URL . '/assets/js/jquery-chosen/chosen.jquery.min.js', array( 'jquery' ), '1.1.0', true );
            wp_register_script( 'wp-job-manager-term-multiselect', JOB_MANAGER_PLUGIN_URL . '/assets/js/term-multiselect.min.js', array( 'jquery', 'chosen' ), JOB_MANAGER_VERSION, true );
            wp_register_script( 'wp-job-manager-multiselect', JOB_MANAGER_PLUGIN_URL . '/assets/js/multiselect.min.js', array( 'jquery', 'chosen' ), JOB_MANAGER_VERSION, true );
            wp_enqueue_style( 'chosen', JOB_MANAGER_PLUGIN_URL . '/assets/css/chosen.css' );
            $ajax_filter_deps[] = 'chosen';

            wp_localize_script( 'chosen', 'job_manager_chosen_multiselect_args',
                apply_filters( 'job_manager_chosen_multiselect_args', array( 'search_contains' => true ) )
            );
        }

        if ( apply_filters( 'job_manager_ajax_file_upload_enabled', true ) ) {
            wp_register_script( 'jquery-iframe-transport', JOB_MANAGER_PLUGIN_URL . '/assets/js/jquery-fileupload/jquery.iframe-transport.js', array( 'jquery' ), '1.8.3', true );
            wp_register_script( 'jquery-fileupload', JOB_MANAGER_PLUGIN_URL . '/assets/js/jquery-fileupload/jquery.fileupload.js', array( 'jquery', 'jquery-iframe-transport', 'jquery-ui-widget' ), '9.11.2', true );
            wp_register_script( 'wp-job-manager-ajax-file-upload', JOB_MANAGER_PLUGIN_URL . '/assets/js/ajax-file-upload.min.js', array( 'jquery', 'jquery-fileupload' ), JOB_MANAGER_VERSION, true );

            ob_start();
            get_job_manager_template( 'form-fields/uploaded-file-html.php', array( 'name' => '', 'value' => '', 'extension' => 'jpg' ) );
            $js_field_html_img = ob_get_clean();

            ob_start();
            get_job_manager_template( 'form-fields/uploaded-file-html.php', array( 'name' => '', 'value' => '', 'extension' => 'zip' ) );
            $js_field_html = ob_get_clean();

            wp_localize_script( 'wp-job-manager-ajax-file-upload', 'job_manager_ajax_file_upload', array(
                'ajax_url'               => $ajax_url,
                'js_field_html_img'      => esc_js( str_replace( "\n", "", $js_field_html_img ) ),
                'js_field_html'          => esc_js( str_replace( "\n", "", $js_field_html ) ),
                'i18n_invalid_file_type' => __( 'Invalid file type. Accepted types:', 'wp-job-manager' )
            ) );
        }

        wp_register_script( 'jquery-deserialize', JOB_MANAGER_PLUGIN_URL . '/assets/js/jquery-deserialize/jquery.deserialize.js', array( 'jquery' ), '1.2.1', true );
        wp_register_script( 'wp-job-manager-ajax-filters', JOB_MANAGER_PLUGIN_URL . '/assets/js/ajax-filters.min.js', $ajax_filter_deps, JOB_MANAGER_VERSION, true );
        wp_register_script( 'wp-job-manager-job-dashboard', JOB_MANAGER_PLUGIN_URL . '/assets/js/job-dashboard.min.js', array( 'jquery' ), JOB_MANAGER_VERSION, true );
        wp_register_script( 'wp-job-manager-job-application', JOB_MANAGER_PLUGIN_URL . '/assets/js/job-application.min.js', array( 'jquery' ), JOB_MANAGER_VERSION, true );
        wp_register_script( 'wp-job-manager-job-submission', JOB_MANAGER_PLUGIN_URL . '/assets/js/job-submission.min.js', array( 'jquery' ), JOB_MANAGER_VERSION, true );
        wp_localize_script( 'wp-job-manager-ajax-filters', 'job_manager_ajax_filters', $ajax_data );
        wp_localize_script( 'wp-job-manager-job-dashboard', 'job_manager_job_dashboard', array(
            'i18n_confirm_delete' => __( 'Are you sure you want to delete this listing?', 'wp-job-manager' )
        ) );

        wp_enqueue_style( 'wp-job-manager-frontend', JOB_MANAGER_PLUGIN_URL . '/assets/css/frontend.css' );
    }
}