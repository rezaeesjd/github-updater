<?php
/**
 * Plugin Name: Github Plugin Installer and Updater
 * Description: Adds GitHub installation and update tooling for the Bokun Bookings Management plugin.
 * Version: 1.0.1
 * Author: Hitesh (HWT)
 * Text Domain: github-plugin-installer-and-updater
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Github_Plugin_Installer_And_Updater_Addon' ) ) {
    /**
     * GitHub installer and updater integration for Bokun Bookings.
     */
    class Github_Plugin_Installer_And_Updater_Addon {
        const OPTION_KEY = 'github_plugin_installer_and_updater_settings';
        const ADMIN_SLUG = 'github-plugin-installer-and-updater';
        const NOTICE_KEY = 'github_plugin_installer_and_updater_notice';
        const VERSION    = '1.0.1';

        /**
         * Cached settings.
         *
         * @var array
         */
        private $settings = array();

        /**
         * Constructor.
         */
        public function __construct() {
            $this->settings = $this->get_settings();

            add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
            add_action( 'admin_init', array( $this, 'register_settings' ) );
            add_action( 'admin_post_github_plugin_installer_and_updater', array( $this, 'handle_update_request' ) );
            add_action( 'admin_bar_menu', array( $this, 'register_admin_bar_items' ), 120 );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

            add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'maybe_inject_self_update' ) );
            add_filter( 'plugins_api', array( $this, 'maybe_provide_self_update_details' ), 10, 3 );
            add_filter( 'http_request_args', array( $this, 'maybe_authorize_github_download' ), 10, 2 );
            add_action( 'wp_clean_plugins_cache', array( $this, 'handle_plugins_cache_cleared' ) );
        }

        /**
         * Register the settings page under the Tools menu.
         */
        public function register_settings_page() {
            add_management_page(
                __( 'Github Plugin Installer and Updater', 'github-plugin-installer-and-updater' ),
                __( 'Github Plugin Installer and Updater', 'github-plugin-installer-and-updater' ),
                'manage_options',
                self::ADMIN_SLUG,
                array( $this, 'render_settings_page' )
            );
        }

        /**
         * Register settings for the updater.
         */
        public function register_settings() {
            register_setting(
                'github_plugin_installer_and_updater',
                self::OPTION_KEY,
                array( $this, 'sanitize_settings' )
            );

            add_settings_section(
                'github_plugin_installer_and_updater_repo',
                __( 'Repository Settings', 'github-plugin-installer-and-updater' ),
                array( $this, 'render_repo_section_description' ),
                self::ADMIN_SLUG
            );

            add_settings_field(
                'repository_url',
                __( 'Repository URL', 'github-plugin-installer-and-updater' ),
                array( $this, 'render_repository_url_field' ),
                self::ADMIN_SLUG,
                'github_plugin_installer_and_updater_repo'
            );

            add_settings_field(
                'repository_branch',
                __( 'Branch or Tag', 'github-plugin-installer-and-updater' ),
                array( $this, 'render_repository_branch_field' ),
                self::ADMIN_SLUG,
                'github_plugin_installer_and_updater_repo'
            );

            add_settings_field(
                'github_token',
                __( 'GitHub Token (optional)', 'github-plugin-installer-and-updater' ),
                array( $this, 'render_github_token_field' ),
                self::ADMIN_SLUG,
                'github_plugin_installer_and_updater_repo'
            );

            add_settings_section(
                'github_plugin_installer_and_updater_account',
                __( 'Repositories From Your GitHub Account', 'github-plugin-installer-and-updater' ),
                array( $this, 'render_account_section_description' ),
                self::ADMIN_SLUG
            );

            add_settings_section(
                'github_plugin_installer_and_updater_self_update',
                __( 'Self Update Settings', 'github-plugin-installer-and-updater' ),
                array( $this, 'render_self_update_section_description' ),
                self::ADMIN_SLUG
            );

            add_settings_field(
                'self_update_repository_url',
                __( 'Plugin Repository URL', 'github-plugin-installer-and-updater' ),
                array( $this, 'render_self_update_repository_url_field' ),
                self::ADMIN_SLUG,
                'github_plugin_installer_and_updater_self_update'
            );

            add_settings_field(
                'self_update_repository_branch',
                __( 'Branch or Tag', 'github-plugin-installer-and-updater' ),
                array( $this, 'render_self_update_repository_branch_field' ),
                self::ADMIN_SLUG,
                'github_plugin_installer_and_updater_self_update'
            );
        }

        /**
         * Sanitize settings input.
         *
         * @param array $input Raw input from the form.
         *
         * @return array
         */
        public function sanitize_settings( $input ) {
            $sanitized = $this->get_default_settings();

            if ( isset( $input['repository_url'] ) ) {
                $sanitized['repository_url'] = esc_url_raw( trim( $input['repository_url'] ) );
            }

            if ( isset( $input['repository_branch'] ) ) {
                $sanitized['repository_branch'] = sanitize_text_field( $input['repository_branch'] );
            }

            if ( isset( $input['github_token'] ) ) {
                $sanitized['github_token'] = sanitize_text_field( $input['github_token'] );
            }

            if ( isset( $input['self_update_repository_url'] ) ) {
                $sanitized['self_update_repository_url'] = esc_url_raw( trim( $input['self_update_repository_url'] ) );
            }

            if ( isset( $input['self_update_repository_branch'] ) ) {
                $sanitized['self_update_repository_branch'] = sanitize_text_field( $input['self_update_repository_branch'] );
            }

            $this->maybe_clear_self_update_cache( $sanitized );

            $this->settings = $sanitized;

            return $sanitized;
        }

        /**
         * Render repository section description.
         */
        public function render_repo_section_description() {
            $token_link = sprintf(
                '<a target="_blank" rel="noopener noreferrer" href="%1$s">%2$s</a>',
                esc_url( 'https://github.com/settings/personal-access-tokens' ),
                esc_html__( 'GitHub Token', 'github-plugin-installer-and-updater' )
            );

            $description = sprintf(
                /* translators: %s: GitHub token URL. */
                __( 'You can get it from here: %s. Provide details about the GitHub repository that hosts the plugin. For public repositories you only need the URL and branch. For private repositories supply a personal access token with the <code>repo</code> scope.', 'github-plugin-installer-and-updater' ),
                $token_link
            );

            printf( '<p>%s</p>', wp_kses_post( $description ) );
        }

        /**
         * Render the repository URL field.
         */
        public function render_repository_url_field() {
            printf(
                '<input type="url" class="regular-text" name="%1$s[repository_url]" id="bokun_repository_url" value="%2$s" placeholder="%3$s" />',
                esc_attr( self::OPTION_KEY ),
                esc_attr( $this->settings['repository_url'] ),
                esc_attr__( 'https://github.com/owner/repository', 'github-plugin-installer-and-updater' )
            );
        }

        /**
         * Render the repository branch field.
         */
        public function render_repository_branch_field() {
            printf(
                '<input type="text" class="regular-text" name="%1$s[repository_branch]" id="bokun_repository_branch" value="%2$s" placeholder="%3$s" />',
                esc_attr( self::OPTION_KEY ),
                esc_attr( $this->settings['repository_branch'] ),
                esc_attr__( 'main', 'github-plugin-installer-and-updater' )
            );
        }

        /**
         * Render the GitHub token field.
         */
        public function render_github_token_field() {
            printf(
                '<input type="password" class="regular-text" name="%1$s[github_token]" id="bokun_github_token" value="%2$s" autocomplete="off" />',
                esc_attr( self::OPTION_KEY ),
                esc_attr( $this->settings['github_token'] )
            );

            echo '<p class="description">' . esc_html__( 'Token is optional for public repositories but required for private repositories. Token will be stored in plain text inside the WordPress options table; avoid reusing sensitive tokens.', 'github-plugin-installer-and-updater' ) . '</p>';
        }

        /**
         * Render the self update section description.
         */
        public function render_self_update_section_description() {
            echo '<p>' . esc_html__( 'Provide the GitHub repository where this plugin is hosted to enable automatic updates for the installer itself.', 'github-plugin-installer-and-updater' ) . '</p>';
        }

        /**
         * Render the self update repository URL field.
         */
        public function render_self_update_repository_url_field() {
            printf(
                '<input type="url" class="regular-text" name="%1$s[self_update_repository_url]" id="bokun_self_update_repository_url" value="%2$s" placeholder="%3$s" />',
                esc_attr( self::OPTION_KEY ),
                esc_attr( $this->settings['self_update_repository_url'] ),
                esc_attr__( 'https://github.com/owner/repository', 'github-plugin-installer-and-updater' )
            );

            echo '<p class="description">' . esc_html__( 'The plugin will check this repository for new versions of itself.', 'github-plugin-installer-and-updater' ) . '</p>';
        }

        /**
         * Render the self update repository branch field.
         */
        public function render_self_update_repository_branch_field() {
            printf(
                '<input type="text" class="regular-text" name="%1$s[self_update_repository_branch]" id="bokun_self_update_repository_branch" value="%2$s" placeholder="%3$s" />',
                esc_attr( self::OPTION_KEY ),
                esc_attr( $this->settings['self_update_repository_branch'] ),
                esc_attr__( 'main', 'github-plugin-installer-and-updater' )
            );
        }

        /**
         * Render the account section description and repository list.
         */
        public function render_account_section_description() {
            echo '<p>' . esc_html__( 'When a GitHub personal access token is provided, repositories from that account can be listed here to quickly select one for updates.', 'github-plugin-installer-and-updater' ) . '</p>';

            $token = $this->settings['github_token'];

            if ( empty( $token ) ) {
                echo '<p>' . esc_html__( 'Provide a token above and save changes to view available repositories.', 'github-plugin-installer-and-updater' ) . '</p>';
                return;
            }

            $repositories = $this->get_cached_repositories( $token );

            if ( is_wp_error( $repositories ) ) {
                printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $repositories->get_error_message() ) );
                return;
            }

            if ( empty( $repositories ) ) {
                echo '<p>' . esc_html__( 'No repositories were found for the authenticated user.', 'github-plugin-installer-and-updater' ) . '</p>';
                return;
            }

            echo '<p>' . esc_html__( 'Click a repository to fill in the repository URL and default branch automatically.', 'github-plugin-installer-and-updater' ) . '</p>';
            echo '<ul class="bokun-github-repo-list">';

            foreach ( $repositories as $repository ) {
                printf(
                    '<li><button type="button" class="button bokun-select-repo" data-url="%1$s" data-branch="%2$s">%3$s</button></li>',
                    esc_attr( $repository['html_url'] ),
                    esc_attr( $repository['default_branch'] ),
                    esc_html( $repository['full_name'] )
                );
            }

            echo '</ul>';

            $refresh_url = add_query_arg(
                array(
                    'page'          => self::ADMIN_SLUG,
                    'refresh_repos' => 1,
                ),
                admin_url( 'tools.php' )
            );

            printf(
                '<p><a class="button" href="%1$s">%2$s</a></p>',
                esc_url( $refresh_url ),
                esc_html__( 'Refresh repository list', 'github-plugin-installer-and-updater' )
            );
        }

        /**
         * Enqueue assets required for the settings screen.
         *
         * @param string $hook_suffix Current admin page hook suffix.
         */
        public function enqueue_admin_assets( $hook_suffix ) {
            if ( 'tools_page_' . self::ADMIN_SLUG !== $hook_suffix ) {
                return;
            }

            $handle = 'github-plugin-installer-and-updater-settings';

            wp_register_script(
                $handle,
                plugins_url( 'assets/js/github-plugin-installer-and-updater-settings.js', __FILE__ ),
                array(),
                self::VERSION,
                true
            );

            wp_enqueue_script( $handle );

            wp_register_style( $handle, false, array(), self::VERSION );
            wp_enqueue_style( $handle );
            wp_add_inline_style(
                $handle,
                '.bokun-github-repo-list {display:flex;flex-wrap:wrap;gap:8px;padding-left:0;list-style:none;}' .
                '.bokun-github-repo-list li {margin:0;}'
            );
        }

        /**
         * Render settings page markup.
         */
        public function render_settings_page() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            $notice               = $this->consume_notice();
            $main_plugin_active   = $this->is_main_plugin_active();
            $main_plugin_path     = $this->get_main_plugin_path();
            $main_plugin_installed = ! empty( $main_plugin_path ) && file_exists( $main_plugin_path );
            $refresh_repos        = filter_input( INPUT_GET, 'refresh_repos', FILTER_SANITIZE_NUMBER_INT );

            if ( $refresh_repos && ! empty( $this->settings['github_token'] ) ) {
                delete_transient( $this->get_repo_cache_key( $this->settings['github_token'] ) );
            }

            $this->settings = $this->get_settings( true );
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Github Plugin Installer and Updater', 'github-plugin-installer-and-updater' ); ?></h1>

                <?php if ( $notice ) : ?>
                    <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?>"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
                <?php endif; ?>

                <?php if ( ! $main_plugin_installed ) : ?>
                    <div class="notice notice-warning"><p><?php esc_html_e( 'The Bokun Bookings Management plugin is not currently installed. Use the updater below to install it from GitHub.', 'github-plugin-installer-and-updater' ); ?></p></div>
                <?php elseif ( ! $main_plugin_active ) : ?>
                    <div class="notice notice-info"><p><?php esc_html_e( 'The Bokun Bookings Management plugin is installed but not active. You can still update the files using this tool.', 'github-plugin-installer-and-updater' ); ?></p></div>
                <?php else : ?>
                    <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'The Bokun Bookings Management plugin is active. Use the GitHub updater to fetch new versions or reinstall as needed.', 'github-plugin-installer-and-updater' ); ?></p></div>
                <?php endif; ?>

                <form method="post" action="options.php">
                    <?php
                    settings_fields( 'github_plugin_installer_and_updater' );
                    do_settings_sections( self::ADMIN_SLUG );
                    submit_button( __( 'Save Settings', 'github-plugin-installer-and-updater' ) );
                    ?>
                </form>

                <hr />

                <h2><?php esc_html_e( 'Install or Update From GitHub', 'github-plugin-installer-and-updater' ); ?></h2>
                <p><?php esc_html_e( 'Once you have saved the repository settings, use the button below to download the plugin from GitHub, ensure the folder name matches the plugin header, and install/update it.', 'github-plugin-installer-and-updater' ); ?></p>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'github_plugin_installer_and_updater_action' ); ?>
                    <input type="hidden" name="action" value="github_plugin_installer_and_updater" />
                    <?php submit_button( __( 'Run GitHub Update', 'github-plugin-installer-and-updater' ), 'primary', 'submit', false ); ?>
                </form>
            </div>
            <?php
        }

        /**
         * Handle the GitHub update request.
         */
        public function handle_update_request() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You do not have permission to perform this action.', 'github-plugin-installer-and-updater' ) );
            }

            check_admin_referer( 'github_plugin_installer_and_updater_action' );

            $settings = $this->get_settings();
            $result   = $this->update_from_github( $settings );

            if ( is_wp_error( $result ) ) {
                $this->persist_notice( $result->get_error_message(), 'error' );
            } else {
                $this->persist_notice( $result, 'success' );
            }

            wp_safe_redirect( add_query_arg( array( 'page' => self::ADMIN_SLUG ), admin_url( 'tools.php' ) ) );
            exit;
        }

        /**
         * Run update from GitHub repository.
         *
         * @param array $settings Settings.
         *
         * @return string|WP_Error
         */
        private function update_from_github( $settings ) {
            if ( empty( $settings['repository_url'] ) ) {
                return new WP_Error( 'missing_repo', __( 'Please provide a repository URL before running the update.', 'github-plugin-installer-and-updater' ) );
            }

            $parsed_repo = $this->parse_repository_url( $settings['repository_url'] );

            if ( is_wp_error( $parsed_repo ) ) {
                return $parsed_repo;
            }

            $branch = ! empty( $settings['repository_branch'] ) ? $settings['repository_branch'] : 'main';
            $token  = $settings['github_token'];

            $download_url = sprintf( 'https://api.github.com/repos/%1$s/%2$s/zipball/%3$s', $parsed_repo['owner'], $parsed_repo['repo'], rawurlencode( $branch ) );
            $zip_file     = $this->download_package( $download_url, $token );

            if ( is_wp_error( $zip_file ) ) {
                return $zip_file;
            }

            $result = $this->install_package( $zip_file );

            if ( file_exists( $zip_file ) ) {
                @unlink( $zip_file );
            }

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            return __( 'GitHub package downloaded and installed successfully.', 'github-plugin-installer-and-updater' );
        }

        /**
         * Download GitHub package to a temporary file.
         *
         * @param string $download_url URL for zipball.
         * @param string $token        Optional GitHub token.
         *
         * @return string|WP_Error Path to downloaded file or WP_Error.
         */
        private function download_package( $download_url, $token = '' ) {
            if ( ! function_exists( 'wp_tempnam' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            $tmp = wp_tempnam( $download_url );

            if ( ! $tmp ) {
                return new WP_Error( 'temp_file', __( 'Unable to create a temporary file for the download.', 'github-plugin-installer-and-updater' ) );
            }

            $headers = array(
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
            );

            if ( ! empty( $token ) ) {
                $headers['Authorization'] = 'Bearer ' . $token;
            }

            $response = wp_remote_get(
                $download_url,
                array(
                    'timeout'  => 60,
                    'stream'   => true,
                    'filename' => $tmp,
                    'headers'  => $headers,
                )
            );

            if ( is_wp_error( $response ) ) {
                @unlink( $tmp );
                return $response;
            }

            $code = (int) wp_remote_retrieve_response_code( $response );

            if ( $code >= 400 ) {
                $message = wp_remote_retrieve_body( $response );
                @unlink( $tmp );

                if ( empty( $message ) ) {
                    $message = sprintf( __( 'Unexpected response from GitHub (HTTP %d).', 'github-plugin-installer-and-updater' ), $code );
                }

                return new WP_Error( 'download_failed', $message );
            }

            return $tmp;
        }

        /**
         * Install package from downloaded zip file.
         *
         * @param string $zip_file Path to zip file.
         *
         * @return true|WP_Error
         */
        private function install_package( $zip_file ) {
            if ( ! class_exists( 'WP_Filesystem_Base' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            global $wp_filesystem;

            if ( ! $wp_filesystem ) {
                WP_Filesystem();
            }

            if ( ! $wp_filesystem ) {
                return new WP_Error( 'filesystem', __( 'The WordPress filesystem could not be initialized.', 'github-plugin-installer-and-updater' ) );
            }

            $working_dir = trailingslashit( get_temp_dir() ) . 'bokun-github-' . wp_generate_password( 8, false );

            if ( ! wp_mkdir_p( $working_dir ) ) {
                return new WP_Error( 'temp_dir', __( 'Unable to create a temporary extraction folder.', 'github-plugin-installer-and-updater' ) );
            }

            $unzipped = unzip_file( $zip_file, $working_dir );

            if ( is_wp_error( $unzipped ) ) {
                $wp_filesystem->delete( $working_dir, true );
                return $unzipped;
            }

            $directories = glob( trailingslashit( $working_dir ) . '*', GLOB_ONLYDIR );

            if ( empty( $directories ) ) {
                $wp_filesystem->delete( $working_dir, true );
                return new WP_Error( 'extraction_failed', __( 'The downloaded archive did not contain any directories.', 'github-plugin-installer-and-updater' ) );
            }

            $package_root = trailingslashit( $directories[0] );
            $slug_data    = $this->determine_plugin_slug( $package_root );

            if ( is_wp_error( $slug_data ) ) {
                $wp_filesystem->delete( $working_dir, true );
                return $slug_data;
            }

            $destination = trailingslashit( WP_PLUGIN_DIR ) . $slug_data['slug'];

            if ( $wp_filesystem->is_dir( $destination ) ) {
                $wp_filesystem->delete( $destination, true );
            }

            if ( ! wp_mkdir_p( WP_PLUGIN_DIR ) ) {
                $wp_filesystem->delete( $working_dir, true );
                return new WP_Error( 'destination', __( 'Unable to access the plugins directory.', 'github-plugin-installer-and-updater' ) );
            }

            $source = rtrim( $package_root, '/' );
            $moved  = $wp_filesystem->move( $source, $destination, true );

            if ( ! $moved ) {
                $php_moved = @rename( $source, $destination );

                if ( ! $php_moved ) {
                    $copied = copy_dir( $source, $destination );

                    if ( is_wp_error( $copied ) ) {
                        $wp_filesystem->delete( $working_dir, true );
                        return $copied;
                    }

                    $moved = true;
                } else {
                    $moved = true;
                }
            }

            if ( ! $moved ) {
                $wp_filesystem->delete( $working_dir, true );
                return new WP_Error( 'move_failed', __( 'Unable to move the extracted plugin into the plugins directory.', 'github-plugin-installer-and-updater' ) );
            }

            $wp_filesystem->delete( $working_dir, true );

            return true;
        }

        /**
         * Determine plugin slug from extracted package.
         *
         * @param string $package_root Path to extracted package root.
         *
         * @return array|WP_Error
         */
        private function determine_plugin_slug( $package_root ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $package_root, FilesystemIterator::SKIP_DOTS ),
                RecursiveIteratorIterator::SELF_FIRST
            );

            $primary_match  = null;
            $fallback_match = null;

            foreach ( $iterator as $file ) {
                if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
                    continue;
                }

                $path = $file->getPathname();

                if ( 'bokun-bookings-management.php' === $file->getFilename() ) {
                    $primary_match = array(
                        'slug'      => 'bokun-bookings-management',
                        'main_file' => $path,
                    );
                    break;
                }

                $plugin_data = get_plugin_data( $path, false, false );

                if ( empty( $plugin_data['Name'] ) ) {
                    continue;
                }

                $match = array(
                    'slug'      => sanitize_title( $plugin_data['Name'] ),
                    'main_file' => $path,
                );

                if ( ! $fallback_match ) {
                    $fallback_match = $match;
                }

                $text_domain = isset( $plugin_data['TextDomain'] ) ? strtolower( $plugin_data['TextDomain'] ) : '';

                if ( 'bokun-bookings-management' === $match['slug'] || 'bokun_text_domain' === $text_domain ) {
                    $primary_match = array(
                        'slug'      => 'bokun-bookings-management',
                        'main_file' => $path,
                    );
                    break;
                }
            }

            if ( $primary_match ) {
                return $primary_match;
            }

            if ( $fallback_match ) {
                return $fallback_match;
            }

            return new WP_Error( 'plugin_slug', __( 'Unable to determine the plugin slug from the downloaded package.', 'github-plugin-installer-and-updater' ) );
        }

        /**
         * Parse repository URL into owner/repo.
         *
         * @param string $url GitHub repository URL.
         *
         * @return array|WP_Error
         */
        private function parse_repository_url( $url ) {
            $parts = wp_parse_url( $url );

            if ( empty( $parts['host'] ) || false === stripos( $parts['host'], 'github.com' ) ) {
                return new WP_Error( 'invalid_repo', __( 'Please provide a valid GitHub repository URL.', 'github-plugin-installer-and-updater' ) );
            }

            $path = isset( $parts['path'] ) ? trim( $parts['path'], '/' ) : '';

            if ( empty( $path ) ) {
                return new WP_Error( 'invalid_repo', __( 'The repository URL must include the owner and repository name.', 'github-plugin-installer-and-updater' ) );
            }

            $segments = explode( '/', $path );

            if ( count( $segments ) < 2 ) {
                return new WP_Error( 'invalid_repo', __( 'Unable to detect repository owner and name from the provided URL.', 'github-plugin-installer-and-updater' ) );
            }

            $owner = $segments[0];
            $repo  = preg_replace( '#\.git$#', '', $segments[1] );

            return array(
                'owner' => $owner,
                'repo'  => $repo,
            );
        }

        /**
         * Add the admin bar menu entry for the updater.
         *
         * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
         */
        public function register_admin_bar_items( $wp_admin_bar ) {
            if ( ! current_user_can( 'manage_options' ) || ! is_admin_bar_showing() ) {
                return;
            }

            $parent_id = false;

            if ( $this->is_main_plugin_active() ) {
                $existing_parent = $wp_admin_bar->get_node( 'bokun-bookings-management' );

                if ( $existing_parent ) {
                    $parent_id = 'bokun-bookings-management';
                } else {
                    $wp_admin_bar->add_node(
                        array(
                            'id'    => 'bokun-bookings-management',
                            'title' => __( 'Bokun Bookings', 'github-plugin-installer-and-updater' ),
                            'href'  => admin_url( 'edit.php?post_type=bokun_booking' ),
                        )
                    );
                    $parent_id = 'bokun-bookings-management';
                }
            }

            $wp_admin_bar->add_node(
                array(
                    'id'     => 'github-plugin-installer-and-updater',
                    'parent' => $parent_id,
                    'title'  => __( 'GitHub Updater', 'github-plugin-installer-and-updater' ),
                    'href'   => add_query_arg( array( 'page' => self::ADMIN_SLUG ), admin_url( 'tools.php' ) ),
                )
            );
        }

        /**
         * Check if the main Bokun plugin is active.
         *
         * @return bool
         */
        private function is_main_plugin_active() {
            if ( ! function_exists( 'is_plugin_active' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            return is_plugin_active( 'bokun-bookings-management/bokun-bookings-management.php' );
        }

        /**
         * Retrieve settings with defaults.
         *
         * @param bool $force_refresh Whether to bypass the cached property.
         *
         * @return array
         */
        private function get_settings( $force_refresh = false ) {
            if ( $force_refresh || empty( $this->settings ) ) {
                $this->settings = wp_parse_args( get_option( self::OPTION_KEY, array() ), $this->get_default_settings() );
            }

            return $this->settings;
        }

        /**
         * Default settings.
         *
         * @return array
         */
        private function get_default_settings() {
            return array(
                'repository_url'    => '',
                'repository_branch' => 'main',
                'github_token'      => '',
                'self_update_repository_url'    => '',
                'self_update_repository_branch' => 'main',
            );
        }

        /**
         * Persist an admin notice for later display.
         *
         * @param string $message Notice message.
         * @param string $type    Notice type.
         */
        private function persist_notice( $message, $type = 'success' ) {
            set_transient(
                $this->get_notice_key(),
                array(
                    'message' => $message,
                    'type'    => $type,
                ),
                MINUTE_IN_SECONDS
            );
        }

        /**
         * Consume persisted notice.
         *
         * @return array|null
         */
        private function consume_notice() {
            $key    = $this->get_notice_key();
            $notice = get_transient( $key );

            if ( $notice ) {
                delete_transient( $key );
            }

            return $notice;
        }

        /**
         * Build notice cache key.
         *
         * @return string
         */
        private function get_notice_key() {
            return self::NOTICE_KEY . '_' . get_current_user_id();
        }

        /**
         * Fetch repositories using GitHub API and cache the results.
         *
         * @param string $token GitHub token.
         *
         * @return array|WP_Error
         */
        private function get_cached_repositories( $token ) {
            $cache_key = $this->get_repo_cache_key( $token );
            $cached    = get_transient( $cache_key );
            $refresh   = filter_input( INPUT_GET, 'refresh_repos', FILTER_SANITIZE_NUMBER_INT );

            if ( false !== $cached && ! $refresh ) {
                return $cached;
            }

            $repositories = $this->fetch_repositories( $token );

            if ( is_wp_error( $repositories ) ) {
                return $repositories;
            }

            set_transient( $cache_key, $repositories, HOUR_IN_SECONDS );

            return $repositories;
        }

        /**
         * Build cache key for repository listings.
         *
         * @param string $token GitHub token.
         *
         * @return string
         */
        private function get_repo_cache_key( $token ) {
            return 'bokun_github_repos_' . md5( $token );
        }

        /**
         * Maybe add a self update entry to the plugin update transient.
         *
         * @param stdClass $transient Update transient.
         *
         * @return stdClass
         */
        public function maybe_inject_self_update( $transient ) {
            if ( ! is_object( $transient ) ) {
                $transient = new stdClass();
            }

            if ( ! function_exists( 'get_plugin_data' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $plugin_file = plugin_basename( __FILE__ );

            $settings = $this->get_settings();

            if ( empty( $settings['self_update_repository_url'] ) ) {
                return $transient;
            }

            $remote = $this->get_self_update_remote_info( $settings );

            if ( is_wp_error( $remote ) ) {
                return $transient;
            }

            $plugin_data     = get_plugin_data( __FILE__, false, false );
            $current_version = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '0';

            if ( version_compare( $remote['version'], $current_version, '>' ) ) {
                $update = (object) array(
                    'slug'        => 'github-plugin-installer-and-updater',
                    'plugin'      => $plugin_file,
                    'new_version' => $remote['version'],
                    'package'     => $remote['package'],
                    'url'         => $remote['homepage'],
                );

                $transient->response[ $plugin_file ] = $update;
            } else {
                if ( isset( $transient->response[ $plugin_file ] ) ) {
                    unset( $transient->response[ $plugin_file ] );
                }

                if ( ! isset( $transient->no_update ) ) {
                    $transient->no_update = array();
                }

                $transient->no_update[ $plugin_file ] = (object) array(
                    'slug'        => 'github-plugin-installer-and-updater',
                    'plugin'      => $plugin_file,
                    'new_version' => $current_version,
                    'package'     => '',
                    'url'         => $remote['homepage'],
                );
            }

            if ( ! isset( $transient->checked ) || ! is_array( $transient->checked ) ) {
                $transient->checked = array();
            }

            $transient->checked[ $plugin_file ] = $current_version;

            return $transient;
        }

        /**
         * Provide plugin information for the self update dialog.
         *
         * @param false|object|array $result Existing result.
         * @param string             $action Requested action.
         * @param object             $args   Arguments.
         *
         * @return false|object|array
         */
        public function maybe_provide_self_update_details( $result, $action, $args ) {
            if ( 'plugin_information' !== $action || empty( $args->slug ) || 'github-plugin-installer-and-updater' !== $args->slug ) {
                return $result;
            }

            if ( ! function_exists( 'get_plugin_data' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $settings = $this->get_settings();

            if ( empty( $settings['self_update_repository_url'] ) ) {
                return $result;
            }

            $remote = $this->get_self_update_remote_info( $settings );

            if ( is_wp_error( $remote ) ) {
                return $result;
            }

            $plugin_data = get_plugin_data( __FILE__, false, false );

            $info = (object) array(
                'name'          => isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : __( 'GitHub Plugin Installer and Updater', 'github-plugin-installer-and-updater' ),
                'slug'          => 'github-plugin-installer-and-updater',
                'version'       => $remote['version'],
                'author'        => isset( $plugin_data['AuthorName'] ) ? $plugin_data['AuthorName'] : '',
                'homepage'      => $remote['homepage'],
                'download_link' => $remote['package'],
                'sections'      => array(
                    'description' => wp_kses_post( __( 'Installs and updates Bokun plugins directly from GitHub repositories.', 'github-plugin-installer-and-updater' ) ),
                ),
            );

            return $info;
        }

        /**
         * Inject authorization headers for GitHub downloads when required.
         *
         * @param array  $args Request arguments.
         * @param string $url  Request URL.
         *
         * @return array
         */
        public function maybe_authorize_github_download( $args, $url ) {
            $settings = $this->get_settings();

            if ( empty( $settings['github_token'] ) ) {
                return $args;
            }

            if ( false === stripos( $url, 'api.github.com/repos' ) ) {
                return $args;
            }

            if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
                $args['headers'] = array();
            }

            if ( empty( $args['headers']['Authorization'] ) ) {
                $args['headers']['Authorization'] = 'Bearer ' . $settings['github_token'];
            }

            if ( empty( $args['headers']['User-Agent'] ) ) {
                $args['headers']['User-Agent'] = 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url();
            }

            return $args;
        }

        /**
         * Retrieve remote information required for self updates.
         *
         * @param array $settings Current plugin settings.
         *
         * @return array|WP_Error
         */
        private function get_self_update_remote_info( $settings ) {
            $cache_key = $this->get_self_update_cache_key( $settings['self_update_repository_url'], $settings['self_update_repository_branch'] );

            if ( $this->should_bypass_self_update_cache() ) {
                delete_transient( $cache_key );
            } else {
                $cached = get_transient( $cache_key );

                if ( false !== $cached ) {
                    return $cached;
                }
            }

            $parsed_repo = $this->parse_repository_url( $settings['self_update_repository_url'] );

            if ( is_wp_error( $parsed_repo ) ) {
                return $parsed_repo;
            }

            $branch = ! empty( $settings['self_update_repository_branch'] ) ? $settings['self_update_repository_branch'] : 'main';
            $token  = $settings['github_token'];

            $file_url = sprintf( 'https://raw.githubusercontent.com/%1$s/%2$s/%3$s/github-plugin-installer-and-updater.php', $parsed_repo['owner'], $parsed_repo['repo'], rawurlencode( $branch ) );

            $headers = array(
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
            );

            if ( ! empty( $token ) ) {
                $headers['Authorization'] = 'Bearer ' . $token;
            }

            $response = wp_remote_get(
                $file_url,
                array(
                    'headers' => $headers,
                    'timeout' => 30,
                )
            );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $code = (int) wp_remote_retrieve_response_code( $response );

            if ( 200 !== $code ) {
                $message = wp_remote_retrieve_body( $response );

                if ( empty( $message ) ) {
                    $message = __( 'Unable to fetch plugin details from GitHub for self updates.', 'github-plugin-installer-and-updater' );
                }

                return new WP_Error( 'self_update_remote', $message );
            }

            $body = wp_remote_retrieve_body( $response );

            if ( ! $body ) {
                return new WP_Error( 'self_update_remote', __( 'The GitHub response did not contain plugin metadata.', 'github-plugin-installer-and-updater' ) );
            }

            $version = $this->extract_version_from_plugin_header( $body );

            if ( empty( $version ) ) {
                return new WP_Error( 'self_update_version', __( 'Unable to determine the remote plugin version for self updates.', 'github-plugin-installer-and-updater' ) );
            }

            $remote = array(
                'version'  => $version,
                'package'  => sprintf( 'https://api.github.com/repos/%1$s/%2$s/zipball/%3$s', $parsed_repo['owner'], $parsed_repo['repo'], rawurlencode( $branch ) ),
                'homepage' => sprintf( 'https://github.com/%1$s/%2$s', $parsed_repo['owner'], $parsed_repo['repo'] ),
            );

            set_transient( $cache_key, $remote, 5 * MINUTE_IN_SECONDS );

            return $remote;
        }

        /**
         * Flush the cached self-update lookup when WordPress clears plugin updates.
         *
         * @param bool $clear_update_cache Whether to clear update caches.
         */
        public function handle_plugins_cache_cleared( $clear_update_cache ) {
            if ( ! $clear_update_cache ) {
                return;
            }

            $settings = $this->get_settings();

            $this->maybe_clear_self_update_cache( $settings );
        }

        /**
         * Determine whether the remote self-update cache should be bypassed.
         *
         * @return bool
         */
        private function should_bypass_self_update_cache() {
            if ( isset( $_GET['force-check'] ) && '1' === $_GET['force-check'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                return true;
            }

            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                return true;
            }

            return false;
        }

        /**
         * Derive the cache key used for self update lookups.
         *
         * @param string $repository_url Repository URL.
         * @param string $branch         Branch.
         *
         * @return string
         */
        private function get_self_update_cache_key( $repository_url, $branch ) {
            return 'github_plugin_installer_self_update_' . md5( $repository_url . '|' . $branch );
        }

        /**
         * Clear the cached self update information when settings change.
         *
         * @param array $settings Current settings.
         */
        private function maybe_clear_self_update_cache( $settings ) {
            if ( empty( $settings['self_update_repository_url'] ) ) {
                return;
            }

            delete_transient( $this->get_self_update_cache_key( $settings['self_update_repository_url'], $settings['self_update_repository_branch'] ) );
        }

        /**
         * Extract the version string from a plugin file header.
         *
         * @param string $plugin_contents Plugin file contents.
         *
         * @return string
         */
        private function extract_version_from_plugin_header( $plugin_contents ) {
            if ( ! preg_match( '/^\s*\*\s*Version:\s*(.+)$/mi', $plugin_contents, $matches ) ) {
                return '';
            }

            return trim( $matches[1] );
        }

        /**
         * Fetch repositories from GitHub API using provided token.
         *
         * @param string $token GitHub token.
         *
         * @return array|WP_Error
         */
        private function fetch_repositories( $token ) {
            $response = wp_remote_get(
                'https://api.github.com/user/repos?per_page=100',
                array(
                    'headers' => array(
                        'Accept'        => 'application/vnd.github+json',
                        'Authorization' => 'Bearer ' . $token,
                        'User-Agent'    => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
                    ),
                    'timeout' => 30,
                )
            );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $code = (int) wp_remote_retrieve_response_code( $response );

            if ( 200 !== $code ) {
                $message = wp_remote_retrieve_body( $response );

                if ( empty( $message ) ) {
                    $message = sprintf( __( 'Unable to fetch repositories. GitHub returned HTTP %d.', 'github-plugin-installer-and-updater' ), $code );
                }

                return new WP_Error( 'github_repos', $message );
            }

            $body = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( ! is_array( $body ) ) {
                return new WP_Error( 'github_repos', __( 'Invalid response received from GitHub.', 'github-plugin-installer-and-updater' ) );
            }

            $repositories = array();

            foreach ( $body as $repo ) {
                if ( empty( $repo['full_name'] ) || empty( $repo['html_url'] ) ) {
                    continue;
                }

                $repositories[] = array(
                    'full_name'      => $repo['full_name'],
                    'html_url'       => $repo['html_url'],
                    'default_branch' => isset( $repo['default_branch'] ) ? $repo['default_branch'] : 'main',
                );
            }

            return $repositories;
        }

        /**
         * Get the absolute path to the main plugin file if it exists.
         *
         * @return string
         */
        private function get_main_plugin_path() {
            return trailingslashit( WP_PLUGIN_DIR ) . 'bokun-bookings-management/bokun-bookings-management.php';
        }
    }
}

new Github_Plugin_Installer_And_Updater_Addon();
