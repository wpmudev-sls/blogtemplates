<?php

if ( ! class_exists( 'blog_templates' ) ) {

    class blog_templates {

        /**
        * @var string The options string name for this plugin
        *
        * @since 1.0
        */
        var $options_name = 'blog_templates_options';

        /**
        * @var string $localization_domain Domain used for localization
        *
        * @since 1.0
        */
        var $localization_domain = 'blog_templates';

        /**
        * @var string $pluginurl The path to this plugin
        *
        * @since 1.0
        */
        var $thispluginurl = '';

        /**
        * @var string $pluginurlpath The path to this plugin
        *
        * @since 1.0
        */
        var $thispluginpath = '';

        /**
        * @var string $currenturl_with_querystring Complete current URL
        *
        * @since 1.0
        */
        var $currenturl_with_querystring;

        /**
        * @var array $options Stores the options for this plugin
        *
        * @since 1.0
        */
        var $options = array();

        /**
        * PHP 4 Compatible Constructor
        *
        * @since 1.0
        */
        function blog_templates() {
            $this->__construct();
        }

        /**
        * PHP 5 Constructor
        *
        * @since 1.0
        */
        function __construct() {
            global $wp_version;

            if ( defined( 'WPMU_PLUGIN_DIR' ) && strpos( __FILE__, WPMU_PLUGIN_DIR ) === false ) {
                $this->thispluginpath = WP_PLUGIN_DIR . '/' . dirname( plugin_basename( __FILE__ ) ) . '/';
                $this->thispluginurl = WP_PLUGIN_URL . '/' . dirname( plugin_basename( __FILE__ ) ) . '/';
                load_plugin_textdomain( $this->localization_domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
            } else {
                $this->thispluginpath = WPMU_PLUGIN_DIR . '/' . dirname( plugin_basename( __FILE__ ) ) . '/';
                $this->thispluginurl = WPMU_PLUGIN_URL . '/' . dirname( plugin_basename( __FILE__ ) ) . '/';
                load_muplugin_textdomain( $this->localization_domain, '/blogtemplatesfiles/languages/' );
            }

            add_action( 'init', array( &$this, 'maybe_upgrade' ) );

            $this->currenturl_with_querystring = is_ssl() ? 'https://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'] : 'http://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];

            // Initialize the options
            $this->get_options();


            // Add the super admin page
            if( version_compare( $wp_version , '3.0.9', '>' ) ) {
                add_action( 'network_admin_menu', array($this, 'network_admin_page' ) );
            } else {
                add_action( 'admin_menu', array($this, 'pre_3_1_network_admin_page' ) );
            }

            // Admin notices and data processing
            add_action( 'network_admin_notices', array($this, 'admin_options_page_posted' ) );
            add_action( 'admin_notices', array($this, 'admin_options_page_posted' ) );

            // Actions
            $action_order = defined('NBT_APPLY_TEMPLATE_ACTION_ORDER') && NBT_APPLY_TEMPLATE_ACTION_ORDER ? NBT_APPLY_TEMPLATE_ACTION_ORDER : 9999;
            add_action('wpmu_new_blog', array($this, 'set_blog_defaults'), apply_filters('blog_templates-actions-action_order', $action_order), 6); // Set to *very high* so this runs after every other action; also, accepts 6 params so we can get to meta
            add_action('admin_footer', array($this,'add_template_dd'));

            add_action('wp_enqueue_scripts', create_function('', 'wp_enqueue_script("jquery");'));

            // Special features for Multi-Domains
            add_action( 'add_multi_domain_form_field', array($this, 'multi_domain_form_field' ) ); // add field to domain addition form
            add_action( 'edit_multi_domain_form_field', array($this, 'multi_domain_form_field' ) ); // add field to domain edition form
            add_filter( 'md_update_domain', array($this, 'multi_domain_update_domain' ), 10, 2 ); // saves blog template value on domain update
            add_filter( 'manage_multi_domains_columns', array($this, 'manage_multi_domains_columns' ) ); // add column to multi domain table
            add_action( 'manage_multi_domains_custom_column', array($this, 'manage_multi_domains_custom_column' ), 10, 2 ); // populate blog template column in multi domain table
            add_action( 'blogs_directory_blogs_list', array($this, 'blogs_directory_blogs_list' ) );
            
            // Signup: WordPress            
            add_action('signup_blogform', array($this, 'registration_template_selection'));
            add_filter('add_signup_meta', array($this, 'registration_template_selection_add_meta'));
            // Signup: BuddyPress
            add_action('bp_after_blog_details_fields', array($this, 'registration_template_selection'));
            add_filter('bp_signup_usermeta', array($this, 'registration_template_selection_add_meta'));

            /**
             * From 1.7.1 version we are not allowing to template the main site
             * This will alert the user to remove that template
             */
            add_action( 'all_admin_notices', array( &$this, 'alert_main_site_templated' ) );


        }

        function maybe_upgrade() {

            // Split posts option into posts and pages options
            $current_version = get_site_option( 'nbt_plugin_version', false );

            if ( ! $current_version || version_compare( $current_version, '1.7.2' ) == -1 ) {
                $new_options = $this->options;
                foreach ( $this->options['templates'] as $key => $template ) {
                    $to_copy = $template['to_copy'];
                    if ( in_array( 'posts', $to_copy ) )
                        $new_options['templates'][ $key ]['to_copy'][] = 'pages';
                }
                $this->options = $new_options;
                $this->save_admin_options();
                update_site_option( 'nbt_plugin_version', '1.7.2' );
            }
            


        }

        function alert_main_site_templated() {
            if ( ! empty( $this->options['templates'] ) ) {
                $main_site_templated = false;
                foreach ( $this->options['templates'] as $template ) {
                    if ( is_main_site( absint( $template['blog_id'] ) ) )
                        $main_site_templated = true;
                }

                if ( $main_site_templated && is_super_admin() ) {
                    $settings_url = add_query_arg( 'page', basename(__FILE__), network_admin_url( 'settings.php' ) );
                    ?>
                        <div class="error">
                            <p><?php printf( __( '<strong>New Blog Templates alert:</strong> The main site cannot be templated from 1.7.1 version, please <a href="%s">go to settings page</a> and remove that template (will not be shown as a choice from now on)', $this->localization_domain ), $settings_url ); ?></p>
                        </div>
                    <?php
                }
            }
        }

        /**
        * Returns a dropdown of all blog templates
        *
        * @since 1.0
        */
        function get_template_dropdown( $tag_name, $include_none ) {

            $templates = array();
            foreach ($this->options['templates'] as $key=>$template) {
                if ( ! is_main_site( absint( $template['blog_id'] ) ) )
                    $templates[$key] = $template['name'];
            }

            if ( is_array( $templates ) ) {
                echo "<select name=\"$tag_name\">";
                echo ($include_none ? "<option value=\"none\">None</option>" : '');
                foreach ($templates as $key=>$value) {
                    echo "<option value=\"$key\">" . esc_attr( $value ) . "</option>";
                }
                echo '</select>';
            }
        }

        /**
        * Adds the Template dropdown to the WPMU New Blog form
        *
        * @since 1.0
        */
        function add_template_dd() {
            global $pagenow;
            if( ! in_array( $pagenow, array( 'ms-sites.php', 'site-new.php' ) ) || isset( $_GET['action'] ) && 'editblog' == $_GET['action'] )
                return;

            ?>
            <script type="text/javascript">
                jQuery(document).ready(function() {
                    jQuery('.form-table:last tr:last').before('\
                    <tr class="form-field form-required">\
                        <th scope="row"><?php _e('Template', $this->localization_domain) ?></th>\
                        <td><?php $this->get_template_dropdown('blog_template',true); ?></td>\
                    </tr>');
                });
            </script>
            <?php
        }


        /**
        * Checks for a template to use, and if it exists, copies the templated settings to the new blog
        *
        * @param mixed $blog_id
        * @param mixed $user_id
        *
        * @since 1.0
        */
        function set_blog_defaults( $blog_id, $user_id, $_passed_domain=false, $_passed_path=false, $_passed_site_id=false, $_passed_meta=false ) {
            global $wpdb, $multi_dm;

            /* Start special Multi-Domain feature */
            if( !empty( $multi_dm ) ) {
                $bloginfo = get_blog_details( (int) $blog_id, false );
                foreach( $multi_dm->domains as $multi_domain ) {
                    if( strpos( $bloginfo->domain, $multi_domain['domain_name'] ) ) {
                        if( isset( $multi_domain['blog_template'] ) && !empty( $this->options['templates'][$multi_domain['blog_template']] ) )
                            $default = $this->options['templates'][$multi_domain['blog_template']];
                    }
                }
            }
            /* End special Multi-Domain feature */

            if( empty( $default ) && isset( $this->options['default'] ) && is_numeric( $this->options['default'] ) ) { // select global default
                $default = isset($this->options['templates'][$this->options['default']]) 
                    ? $this->options['templates'][$this->options['default']]
                    : false
                ;
            }


            $template = '';
            // Check $_POST first for passed template and use that, if present.
            // Otherwise, check passed meta from blog signup.
            // Lastly, apply the default.
            if ( isset( $_POST['blog_template'] ) && is_numeric( $_POST['blog_template'] ) ) { //If they've chosen a template, use that. For some reason, when PHP gets 0 as a posted var, it doesn't recognize it as is_numeric, so test for that specifically
                $template = $this->options['templates'][$_POST['blog_template']];
            } else if ($_passed_meta && isset($_passed_meta['blog_template']) && is_numeric($_passed_meta['blog_template'])) { // Do we have a template in meta?
                $template = $this->options['templates'][$_passed_meta['blog_template']]; // Why, yes. Yes, we do. Use that. 
            } else if ( $default ) { //If they haven't chosen a template, use the default if it exists
                $template = $default;
            }
            $template = apply_filters('blog_templates-blog_template', $template, $blog_id, $user_id, $this);

            if (!$template || 'none' == $template)
                return; //No template, lets leave

            //Begin the transaction
            $wpdb->query("BEGIN;");

            switch_to_blog($blog_id); //Switch to the blog that was just created

            // Attachments URL for the new blog
            $new_content_url = get_bloginfo('wpurl');


            //Get the prefixes, so we don't have to worry about regex, or changes to WP's naming conventions
            $new_prefix = $wpdb->prefix;

            //Don't forget to get the template blog's prefix
            switch_to_blog($template['blog_id']);
            $template_prefix = $wpdb->prefix;

            // Attachments URL for the template blogç
            $template_attachments = get_posts( array( 'post_type' => 'attachment' ) );
            $template_content_url = get_bloginfo('wpurl');

            //Now, go back to the new blog that was just created
            restore_current_blog();

            // In case we are not copying posts, we'll have to reset the terms count to 0
            $copying_posts = in_array( 'posts', $template['to_copy'] );
            $copying_pages = in_array( 'pages', $template['to_copy'] );

            if ( $copying_posts || $copying_pages ) {
                $this->clear_table($wpdb->posts);
                $this->clear_table($wpdb->postmeta);
            }

            foreach ( $template['to_copy'] as $value ) {
                switch ( $value ) {
                    case 'settings':
                        //We can't use the helper functions here, because we need to save some of the settings

                        $exclude_settings = apply_filters( 'blog_template_exclude_settings', "`option_name` != 'siteurl' AND `option_name` != 'blogname' AND `option_name` != 'admin_email' AND `option_name` != 'new_admin_email' AND `option_name` != 'home' AND `option_name` != 'upload_path' AND `option_name` != 'db_version' AND `option_name` != 'secret' AND `option_name` != 'fileupload_url' AND `option_name` != 'nonce_salt'" );

                        //Delete the current options, except blog-specific options
                        $wpdb->query("DELETE FROM $wpdb->options WHERE $exclude_settings");

                        if (!$wpdb->last_error) { //No error. Good! Now copy over the old settings

                            //Switch to the template blog, then grab the settings/plugins/templates values from the template blog
                            switch_to_blog($template['blog_id']);

                            $templated = $wpdb->get_results("SELECT * FROM $wpdb->options WHERE $exclude_settings");
                            restore_current_blog(); //Switch back to the newly created blog

                            //Now, insert the templated settings into the newly created blog
                            foreach ($templated as $row) {
                                //Make sure none of the options are using wp_X_ convention, and if they are, replace the value with the new blog ID
                                $row->option_name = str_replace($template_prefix, $new_prefix,$row->option_name);
                                if ('sidebars_widgets' != $row->option_name) /* <-- Added this to prevent unserialize() call choking on badly formatted widgets pickled array */ $row->option_value = str_replace($template_prefix, $new_prefix,$row->option_value);

                                //To prevent duplicate entry errors, since we're not deleting ALL of the options, there could be an ID collision
                                unset($row->option_id);
                                // For template blogs with deprecated DB schema (WP3.4+)
                                if (!(defined('NBT_TIGHT_ROW_DUPLICATION') && NBT_TIGHT_ROW_DUPLICATION)) unset($row->blog_id);

                                // Add further processing for options row
                                $row = apply_filters('blog_templates-copy-options_row', $row, $template, $blog_id, $user_id);
                                
                                if (!$row) continue; // Prevent empty row insertion

                                //Insert the row
                                $wpdb->insert($wpdb->options,(array) $row);

                                //Check for errors
                                if (!empty($wpdb->last_error)) {
                                    $error = '<div id="message" class="error"><p>' . sprintf( __( 'Insertion Error: %s - The template was not applied. (New Blog Templates - While inserting templated settings)', $this->localization_domain ), $wpdb->last_error ) . '</p></div>';
                                    $wpdb->query("ROLLBACK;");

                                    //We've rolled it back and thrown an error, we're done here
                                    restore_current_blog();
                                    wp_die($error);
                                }
                            }
                            do_action('blog_templates-copy-options', $template);
                        } else {
                            $error = '<div id="message" class="error"><p>' . sprintf( __( 'Deletion Error: %s - The template was not applied. (New Blog Templates - While removing auto-generated settings)', $this->localization_domain ), $wpdb->last_error ) . '</p></div>';
                            $wpdb->query("ROLLBACK;");
                            restore_current_blog(); //Switch back to our current blog
                            wp_die($error);
                        }
                    break;
                    case 'posts':
                        $this->copy_posts_table($template['blog_id'],"posts");
                        do_action('blog_templates-copy-posts', $template, $blog_id, $user_id);

                        $this->copy_posts_table($template['blog_id'],"postmeta");
                        do_action('blog_templates-copy-postmeta', $template, $blog_id, $user_id);

                    break;
                    case 'pages':
                        $this->copy_posts_table($template['blog_id'],"pages");
                        do_action('blog_templates-copy-pages', $template, $blog_id, $user_id);

                        $this->copy_posts_table($template['blog_id'],"pagemeta");
                        do_action('blog_templates-copy-pagemeta', $template, $blog_id, $user_id);
                    break;
                    case 'terms':
                        $this->clear_table($wpdb->links);
                        $this->copy_table($template['blog_id'],"links");
                        do_action('blog_templates-copy-links', $template, $blog_id, $user_id);

                        $this->clear_table($wpdb->terms);
                        $this->copy_table($template['blog_id'],"terms");
                        do_action('blog_templates-copy-terms', $template, $blog_id, $user_id);

                        $this->clear_table($wpdb->term_relationships);
                        $this->copy_table($template['blog_id'],"term_relationships");
                        do_action('blog_templates-copy-term_relationships', $template, $blog_id, $user_id);

                        $this->clear_table($wpdb->term_taxonomy);
                        $this->copy_table($template['blog_id'],"term_taxonomy");
                        do_action('blog_templates-copy-term_taxonomy', $template, $blog_id, $user_id);

                        if ( ! $copying_posts ) {
                            // The new blog will not have any post
                            // So we have to set the terms count to 0
                            $this->reset_terms_counts( $template['blog_id'] );
                        }
                    break;
                    case 'users':
                        //Copy over the users to this blog
                        $users = $wpdb->get_results("SELECT * FROM $wpdb->usermeta WHERE user_id != {$user_id} AND meta_key REGEXP '^" . mysql_escape_string($template_prefix) . "[^0-9]'");
                        if (empty($users)) continue; //If there are no users to copy, just leave. We don't want to leave this blog without any users

                        //Delete the auto user from the blog, to prevent duplicates or erroneous users
                        $wpdb->query("DELETE FROM $wpdb->usermeta WHERE user_id != {$user_id} AND meta_key LIKE '" . mysql_escape_string($new_prefix) . "%'");
                        if (!empty($wpdb->last_error)) {
                            $error = '<div id="message" class="error"><p>' . sprintf( __( 'Deletion Error: %s - The template was not applied. (New Blog Templates - While removing auto-generated users)', $this->localization_domain ), $wpdb->last_error ) . '</p></div>';
                            $wpdb->query("ROLLBACK;");

                            //We've rolled it back and thrown an error, we're done here
                            restore_current_blog();
                            wp_die($error);
                        }

                        $postprocess_add_new_user_action = false;
                        //Now, insert the templated settings into the newly created blog
                        foreach ($users as $user) {
                            // Check if the user ID from email entered via the New Blog form has been added, and if not, add them after the foreach loop...
                            if (!$postprocess_add_new_user_action && $user->user_id != $user_id) $postprocess_add_new_user_action = true;
                            if ($user->user_id == $user_id) continue;

                            // Carry on...
                            $user->meta_key = str_replace($template_prefix, $new_prefix,$user->meta_key);
                            unset($user->umeta_id); //Remove the umeta_id field, let it autoincrement

                            // Further user entry processing
                            $user = apply_filters('blog_templates-copy-user_entry', $user, $template, $blog_id, $user_id);
                            if (!$user) continue; // Skip empty user objects

                            //Insert the user
                            $wpdb->insert($wpdb->usermeta, (array)$user);
                            //Check for errors
                            if (!empty($wpdb->last_error)) {
                                $error = '<div id="message" class="error"><p>' . sprintf( __( 'Insertion Error: %s - The template was not applied. (New Blog Templates - While inserting templated users)', $this->localization_domain ), $wpdb->last_error ) . '</p></div>';
                                $wpdb->query("ROLLBACK;");

                                //We've rolled it back and thrown an error, we're done here
                                restore_current_blog();
                                wp_die($error);
                            }
                        }
                        if ($postprocess_add_new_user_action) {
                            add_user_to_blog($blog_id, $user_id, 'administrator');
                        }
                        do_action('blog_templates-copy-users', $template, $blog_id, $user_id);
                    break;
                    case 'menus':
                        $this->copy_menu( $template['blog_id'], $blog_id );
                    break;
                    case 'files':
                        global $wp_filesystem;

                        $dir_to_copy = $this->_get_files_fs_path($template['blog_id']); //ABSPATH . 'wp-content/blogs.dir/' . $template['blog_id'] . '/files';
                        $dir_to_copy = apply_filters('blog_templates-copy-source_directory', $dir_to_copy, $template, $blog_id, $user_id);

                        if (defined('NBT_LEGACY_PATH_RESOLUTION') && NBT_LEGACY_PATH_RESOLUTION) {
                            switch_to_blog( $blog_id );
                            $dir_to_copy_into = WP_CONTENT_DIR . '/blogs.dir/' . $blog_id . '/files/';
                            restore_current_blog();
                        }
                        else {
                            $dir_to_copy_into = $this->_get_files_fs_path($blog_id); //ABSPATH .'wp-content/blogs.dir/' . $blog_id . '/files';
                        }
                        $dir_to_copy_into = apply_filters('blog_templates-copy-target_directory', $dir_to_copy_into, $template, $blog_id, $user_id);

                        if ( is_dir( $dir_to_copy ) ) {
                            $result = wp_mkdir_p( $dir_to_copy_into );
                            if ($result) {

                                include_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php' );
                                include_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php' );

                                if ( is_object( $wp_filesystem ) )
                                    $orig_filesystem = wp_clone( $wp_filesystem );
                                else
                                    $orig_filesystem = $wp_filesystem;

                                $wp_filesystem = new WP_Filesystem_Direct( false );

                                if ( ! defined('FS_CHMOD_DIR') )
                                    define('FS_CHMOD_DIR', 0755 );
                                if ( ! defined('FS_CHMOD_FILE') )
                                    define('FS_CHMOD_FILE', 0644 );

                                $result = copy_dir( $dir_to_copy, $dir_to_copy_into );

                                unset( $wp_filesystem );
                                
                                if ( is_object( $orig_filesystem ) )
                                    $wp_filesystem = wp_clone( $orig_filesystem );
                                else
                                    $wp_filesystem = $orig_filesystem;

                                if ( @file_exists( $dir_to_copy_into . '/sitemap.xml' ) )
                                    @unlink( $dir_to_copy_into . '/sitemap.xml' );
                            } else {
                                $error = '<div id="message" class="error"><p>' . sprintf( __( 'File System Error: Unable to create directory %s. (New Blog Templates - While copying files)', $this->localization_domain ), $dir_to_copy_into ) . '</p></div>';
                                $wpdb->query( 'ROLLBACK;' );
                                restore_current_blog();
                                wp_die( $error );

                            }
                        }
                    break;
                }
            }

            //Are there any additional tables we need to copy?
            /*error_log('Begin Additional Tables code');
            echo 'Before additional tables code<br/>';*/
            if (isset($template['additional_tables']) && is_array($template['additional_tables'])) {
                //echo 'is array<br/>';
                foreach ($template['additional_tables'] as $add) {
                    $add = mysql_escape_string($add); //Just in case

                    $result = $wpdb->get_results("SHOW TABLES LIKE '" . str_replace($template_prefix,$new_prefix,$add) . "'", ARRAY_N);
                    if (!empty($result)) { //Is the table present? Clear it, then copy
                        //echo ("table exists: $add<br/>");
                        $this->clear_table($add);
                        //Copy the DB
                        $this->copy_table($template['blog_id'],str_replace($template_prefix,'',$add));
                    } else { //The table's not present, add it and copy the data from the old one
                        //echo ('table doesn\'t exist<br/>');
/* -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */
                        // Changed
                        if (class_exists("m_wpdb")) {
                            $conns = $wpdb->dbh_connections;
                            //$multi_db = $conns['global']['name'] . '.';
                            unset($conns['global']);
                            $current = current($conns);
                            $current_db = $current['name'] . '.';
                            $add_table = explode('.', $add);
                            $add_table = $add_table[1];
                        } else {
                            $multi_db = $current_db = '';
                            $add_table = $add;
                        }
                        $wpdb->query("CREATE TABLE IF NOT EXISTS {$current_db}" . str_replace($template_prefix,$new_prefix,$add_table) . " LIKE {$add}");
                        $wpdb->query("INSERT {$current_db}" . str_replace($template_prefix,$new_prefix,$add_table) . " SELECT * FROM {$add}");
                        // End changed
/* -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */

                        if (!empty($wpdb->last_error)) {
                            $error = '<div id="message" class="error"><p>' . sprintf( __( 'Insertion Error: %s - The template was not applied. (New Blog Templates - With CREATE TABLE query for Additional Tables)', $this->localization_domain ), $wpdb->last_error ) . '</p></div>';
                            $wpdb->query("ROLLBACK;");

                            //We've rolled it back and thrown an error, we're done here
                            restore_current_blog();
                            wp_die($error);
                        }
                    }
                }
            }
            //error_log('Finished Successfully');
            $wpdb->query("COMMIT;"); //If we get here, everything's fine. Commit the transaction

            // We need now to change the attachments URLs
            $attachment_guids = array();
            foreach ( $template_attachments as $attachment ) {
                $new_url = str_replace( 
                    $template_content_url, 
                    $new_content_url, 
                    dirname($attachment->guid) 
                );
                $new_url = str_replace(
                    'sites/' . $template['blog_id'],
                    'sites/' . $blog_id,
                    $new_url
                );
            
                // We get an array with key = old_url and value = new_url
                $attachment_guids[ dirname( $attachment->guid ) ] = $new_url;
            }
            
            $this->set_attachments_urls( $attachment_guids );
            
            // Now we need to update the blog status because of a conflict with Multisite Privacy Plugin
            if ( isset( $template['copy_status'] ) && $template['copy_status'] &&  is_plugin_active( 'sitewide-privacy-options/sitewide-privacy-options.php' ) )
                update_blog_status( $blog_id, 'public', get_blog_status( $template['blog_id'], 'public' ) ); 

            do_action("blog_templates-copy-after_copying", $template, $blog_id, $user_id);

            restore_current_blog(); //Switch back to our current blog

        }

        /**
        * Added to automate comparing the two tables, and making sure no old fields that have been removed get copied to the new table
        *
        * @param mixed $new_table_name
        * @param mixed $old_table_row
        *
        * @since 1.0
        */
        function get_fields_to_remove( $new_table_name, $old_table_row ) {
            //make sure we have something to compare it to
            if (empty($old_table_row)) return false;

            //We need the old table row to be in array format, so we can use in_array()
            $old_table_row = (array)$old_table_row;

            global $wpdb;

            //Get the new table structure
            $new_table = (array)$wpdb->get_results("SHOW COLUMNS FROM {$wpdb->$new_table_name}");

            $new_fields = array();
            foreach($new_table as $row) {
                $new_fields[] = $row->Field;
            }

            $results = array();

            //Now, go through the columns in the old table, and check if there are any that don't show up in the new table
            foreach ($old_table_row as $key=>$value) {
                if (!in_array($key,$new_fields)) { //If the new table doesn't have this field
                    //There's a column that isn't in the new one, make note of that
                    $results[] = $key;
                }
            }

            //Return the results array, which should contain all of the fields that don't appear in the new table
            return $results;
        }

        /**
        * Copy the templated blog table
        *
        * @param int $templated_blog_id The ID of the blog to copy
        * @param string $table The name of the table to copy
        *
        * @since 1.0
        */
        function copy_table( $templated_blog_id, $table ) {
            global $wpdb;
            
            do_action('blog_templates-copying_table', $table, $templated_blog_id);

            //Switch to the template blog, then grab the values
            switch_to_blog($templated_blog_id);
            $templated = $wpdb->get_results("SELECT * FROM {$wpdb->$table}");
            restore_current_blog(); //Switch back to the newly created blog

            if (count($templated))
                $to_remove = $this->get_fields_to_remove($table, $templated[0]);

            //Now, insert the templated settings into the newly created blog
            foreach ($templated as $row) {
                $row = (array)$row;

                foreach ($row as $key=>$value) {
                    if (in_array($key,$to_remove))
                        unset($row[$key]);
                }
                
                $process = apply_filters('blog_templates-process_row', $row, $table, $templated_blog_id);
                if (!$process) continue; 

                //$wpdb->insert($wpdb->$table, $row);
                $wpdb->insert($wpdb->$table, $process);
                if (!empty($wpdb->last_error)) {
                    $error = '<div id="message" class="error"><p>' . sprintf( __( 'Insertion Error: %1$s - The template was not applied. (New Blog Templates - While copying %2$s)', $this->localization_domain ), $wpdb->last_error, $table ) . '</p></div>';
                    $wpdb->query("ROLLBACK;");

                    //We've rolled it back and thrown an error, we're done here
                    restore_current_blog();
                    wp_die($error);
                }
            }
        }

        /**
        * Copy the templated blog posts table. Bit different from the
        * previous one, it can make difference between
        * posts and pages
        *
        * @param int $templated_blog_id The ID of the blog to copy
        * @param string $type post, page, postmeta or pagemeta
        *
        * @since 1.0
        */
        function copy_posts_table( $templated_blog_id, $type ) {
            global $wpdb;

            switch( $type ) {
                case 'posts': $table = 'posts'; break;
                case 'postmeta': $table = 'postmeta'; break;
                case 'pages': $table = 'posts'; break;
                case 'pagemeta': $table = 'postmeta'; break;
                case 'attachment': $table = 'post'; break;
                case 'attachmentmeta': $table = 'postmeta'; break;
            }
            
            do_action('blog_templates-copying_table', $table, $templated_blog_id);

            //Switch to the template blog, then grab the values
            switch_to_blog($templated_blog_id);
            $query = "SELECT t1.* FROM {$wpdb->$table} t1 ";

            if ( 'posts' == $type )
                $query .= "WHERE t1.post_type != 'page'";
            elseif ( 'postmeta' == $type )
                $query .= "INNER JOIN $wpdb->posts t2 ON t1.post_id = t2.ID WHERE t2.post_type != 'page'";
            elseif ( 'pages' == $type )
                $query .= "WHERE t1.post_type = 'page'";
            elseif ( 'pagemeta' == $type )
                $query .= "INNER JOIN $wpdb->posts t2 ON t1.post_id = t2.ID WHERE t2.post_type = 'page'";

            $templated = $wpdb->get_results( $query );
            restore_current_blog(); //Switch back to the newly created blog

            if (count($templated))
                $to_remove = $this->get_fields_to_remove($table, $templated[0]);

            //Now, insert the templated settings into the newly created blog
            foreach ($templated as $row) {
                $row = (array)$row;

                foreach ($row as $key=>$value) {
                    if (in_array($key,$to_remove))
                        unset($row[$key]);
                }
                
                $process = apply_filters('blog_templates-process_row', $row, $table, $templated_blog_id);
                if (!$process) continue; 

                //$wpdb->insert($wpdb->$table, $row);
                $wpdb->insert($wpdb->$table, $process);
                if (!empty($wpdb->last_error)) {
                    $error = '<div id="message" class="error"><p>' . sprintf( __( 'Insertion Error: %1$s - The template was not applied. (New Blog Templates - While copying %2$s)', $this->localization_domain ), $wpdb->last_error, $table ) . '</p></div>';
                    $wpdb->query("ROLLBACK;");

                    //We've rolled it back and thrown an error, we're done here
                    restore_current_blog();
                    wp_die($error);
                }
            }
        }


         /**
         * Copy the templated menu and locations
         * 
         * @since 1.6.6
         * 
         * @param int $templated_blog_id The ID of the blog to copy
         * @param int $new_blog_id The ID of the new blog
         *
         */
        function copy_menu( $templated_blog_id, $new_blog_id ) {
            global $wpdb;

            do_action( 'blog_templates-copying_menu', $templated_blog_id, $new_blog_id);

            switch_to_blog( $templated_blog_id );
            $templated_posts_table = $wpdb->posts;
            $templated_postmeta_table = $wpdb->postmeta;
            $templated_term_relationships_table = $wpdb->term_relationships;

            $menu_locations = get_nav_menu_locations();
            restore_current_blog();

            switch_to_blog( $new_blog_id );
            $new_posts_table = $wpdb->posts;
            $new_postmeta_table = $wpdb->postmeta;
            $new_term_relationships_table = $wpdb->term_relationships;

            $new_blog_locations = $menu_locations;

            restore_current_blog();
            
            $menus = $wpdb->get_col(
                "SELECT ID FROM $templated_posts_table
                WHERE post_type = 'nav_menu_item'"
            );

            if ( ! empty( $menus ) ) {

                // Duplicating the menu locations
                set_theme_mod( 'nav_menu_locations', $new_blog_locations );

                // Duplicating every menu item
                // We cannot use nav-menu functions as we need
                // to keep all the old IDs
                $menus = '(' . implode( ',', $menus ) . ')';
                $wpdb->query(
                    "INSERT IGNORE INTO $new_posts_table
                    SELECT * FROM $templated_posts_table
                    WHERE ID IN $menus"
                );

                $wpdb->query(
                    "INSERT IGNORE INTO $new_postmeta_table
                    SELECT * FROM $templated_postmeta_table
                    WHERE post_id IN $menus"
                );

                $wpdb->query(
                    "INSERT IGNORE INTO $new_term_relationships_table
                    SELECT * FROM $templated_term_relationships_table
                    WHERE object_id IN $menus"
                );


            }
        }
       

        /**
        * Deletes everything from a table
        *
        * @param string $table The name of the table to clear
        *
        * @since 1.0
        */
        function clear_table( $table ) {
            global $wpdb;

            do_action('blog_templates-clearing_table', $table);

            //Delete the current categories
            $wpdb->query("DELETE FROM $table");

            if ($wpdb->last_error) { //No error. Good! Now copy over the terms from the templated blog
                $error = '<div id="message" class="error"><p>' . sprintf( __( 'Deletion Error: %1$s - The template was not applied. (New Blog Templates - While clearing %2$s)', $this->localization_domain ), $wpdb->last_error, $table ) . '</p></div>';
                $wpdb->query("ROLLBACK;");
                restore_current_blog(); //Switch back to our current blog
                wp_die($error);
            }
        }


        /**
         * Changes the base URL for all attachments
         * 
         * @since 1.6.5
         */
        function set_attachments_urls( $attachment_guids ) {
            global $wpdb;

            $queries = array();
            foreach ( $attachment_guids as $old_guid => $new_guid ) {
                $queries[] = $wpdb->prepare( "UPDATE $wpdb->posts SET guid = REPLACE( guid, '%s', '%s' ) WHERE post_type = 'attachment'",
                    $old_guid,
                    $new_guid
                );
            }

            if ( count( $queries ) > 0 ) {
                $queries = implode( ';', $queries );
                $wpdb -> query( $queries );
            }

        }

        /**
        * Retrieves the plugin options from the database.
        *
        * @since 1.0
        */
        function get_options() {
            //Don't forget to set up the default options
            if (!$theOptions = get_site_option($this->options_name)) {
                $theOptions = array('templates'=>array());
                update_site_option($this->options_name, $theOptions);
            }
            $this->options = $theOptions;
        }

        /**
        * Saves the admin options to the database.
        *
        * @since 1.0
        **/
        function save_admin_options() {
            return update_site_option( $this->options_name, $this->options );
        }

        /**
        * Adds the options subpanel
        *
        * @since 1.2.1
        */
        function network_admin_page() {
            add_submenu_page( 'settings.php', __( 'Blog Templates', $this->localization_domain ), __( 'Blog Templates', $this->localization_domain ), 'administrator', basename(__FILE__), array($this,'admin_options_page'));
        }

        /**
        * Adds the options subpanel
        *
        * @since 1.0
        */
        function pre_3_1_network_admin_page() {
            if ( get_bloginfo('version') >= 3 )
                add_submenu_page( 'ms-admin.php', __( 'Blog Templates', $this->localization_domain ), __( 'Blog Templates', $this->localization_domain ), 'administrator', basename(__FILE__), array($this,'admin_options_page'));
            else
                add_submenu_page( 'wpmu-admin.php', __( 'Blog Templates', $this->localization_domain ), __( 'Blog Templates', $this->localization_domain ), 'administrator', basename(__FILE__), array($this,'admin_options_page'));
            add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'filter_plugin_actions' ) );
        }

        /**
        * Adds the Settings link to the plugin activate/deactivate page
        *
        * @param array $links The ID of the blog to copy
        *
        * @since 1.0
        */
        function filter_plugin_actions( $links ) {
            $wp_version;

            if ( version_compare( $wp_version , '3.0.9', '>' ) )
                $settings_link = '<a href="' . network_admin_url( 'settings.php?page=' . basename(__FILE__) ) . '">' . __( 'Settings', $this->localization_domain ) . '</a>';
            elseif ( version_compare( $wp_version , '3.0', '<' ) )
                $settings_link = '<a href="wpmu-admin.php?page=' . basename(__FILE__) . '">' . __( 'Settings', $this->localization_domain ) . '</a>';
            else
                $settings_link = '<a href="ms-admin.php?page=' . basename(__FILE__) . '">' . __( 'Settings', $this->localization_domain ) . '</a>';
            array_unshift( $links, $settings_link ); // add before other links

            return $links;
        }

        /**
        * Returns the WP Options table for this blog as a data array so we can use/save it easily
        *
        * @since 1.0
        */
        function get_wp_options_as_array() {
            global $wpdb;
            $wp_options = array();

            $exclude_settings = apply_filters( 'blog_template_exclude_settings', "`option_name` != 'siteurl' AND `option_name` != 'blogname' AND `option_name` != 'admin_email' AND `option_name` != 'new_admin_email' AND `option_name` != 'home' AND `option_name` != 'upload_path' AND `option_name` != 'db_version' AND `option_name` != 'secret' AND `option_name` != 'fileupload_url' AND `option_name` != 'nonce_salt'" );

            $results = $wpdb->get_results("SELECT * FROM $wpdb->options WHERE `option_name` NOT LIKE '_transient%' AND $exclude_settings");
            foreach ($results as $row) {
                $wp_options[$row->option_name] = array('blog_id'=>$row->blog_id,'option_value'=>$row->option_value,'autoload'=>$row->autoload);
            }
            return $wp_options;
        }

        /**
        * Separated into its own function so we could include it in the init hook
        *
        * @since 1.0
        */
        function admin_options_page_posted() {
            if ( !isset( $_GET['page'] ) || $_GET['page'] !== 'blog_templates.php' )
                return;

            unset( $this->options['templates'][''] ); //Delete the [] item, this will fix corrupted data

            $t = isset( $_GET['t'] ) ? (string) $_GET['t'] : '';

            if( !empty( $_POST['save_updated_template'] ) ) {
                if (! wp_verify_nonce($_POST['_nbtnonce'], 'blog_templates-update-options') )
                    die( __( 'Whoops! There was a problem with the data you posted. Please go back and try again. (Generated by New Blog Templates)', $this->localization_domain ) );

                $this->options['templates'][$t]['name'] = stripslashes($_POST['template_name']);
                $this->options['templates'][$t]['description'] = stripslashes( preg_replace('~<\s*\bscript\b[^>]*>(.*?)<\s*\/\s*script\s*>~is', '', $_POST['template_description'] ) );
                $this->options['templates'][$t]['to_copy'] = isset( $_POST['to_copy'] ) ? (array)$_POST['to_copy'] : array();
                $this->options['templates'][$t]['additional_tables'] = isset( $_POST['additional_template_tables'] ) ? $_POST['additional_template_tables'] : array();
                $this->options['templates'][$t]['copy_status'] = isset( $_POST['copy_status'] ) ? true : false;

                $this->save_admin_options();

                echo '<div class="updated fade"><p>Success! Your changes were sucessfully saved!</p></div>';
            } elseif( !empty( $_POST['save_new_template'] ) ) {
                if ( ! wp_verify_nonce( $_POST['_nbtnonce'], 'blog_templates-update-options' ) )
                    die( __( 'Whoops! There was a problem with the data you posted. Please go back and try again. (Generated by New Blog Templates)', $this->localization_domain ) );

                if ( ! get_blog_details( (int) $_POST['copy_blog_id'] ) )
                    die( __( 'Whoops! The blog ID you posted is incorrect. Please go back and try again. (Generated by New Blog Templates)', $this->localization_domain ) );

                if ( is_main_site( (int) $_POST['copy_blog_id'] ) )
                    die( __( 'Whoops! The blog ID you posted is incorrect. You cannot template the main site. Please go back and try again. (Generated by New Blog Templates)', $this->localization_domain ) );

                $this->options['templates'][] = array(
                    'name' => (!empty($_POST['template_name']) ? stripslashes( $_POST['template_name'] ) : ''),
                    'description' => (!empty($_POST['template_description']) ? stripslashes( preg_replace('~<\s*\bscript\b[^>]*>(.*?)<\s*\/\s*script\s*>~is', '', $_POST['template_description'] ) ) : ''),
                    'blog_id' => (int)$_POST['copy_blog_id'],
                    'to_copy' => (!empty($_POST['to_copy']) ? (array)$_POST['to_copy'] : array()),
                    'copy_status' => isset( $_POST['copy_status'] ) ? true : false
                );

                $this->save_admin_options();

                echo '<div class="updated fade"><p>' . __( 'Success! Your changes were sucessfully saved!', $this->localization_domain ) . '</p></div>';
            } elseif( isset( $_GET['remove_default'] ) ) {
                if ( ! wp_verify_nonce($_GET['_wpnonce'], 'blog_templates-remove_default') )
                    die( __( 'Whoops! There was a problem with the data you posted. Please go back and try again. (Generated by New Blog Templates)', $this->localization_domain ) );
                unset($this->options['default']);

                $this->save_admin_options();

                if ( ( isset( $_GET['default'] ) && is_numeric( $_GET['default'] ) ) || ( isset( $_GET['d'] ) && is_numeric( $_GET['d'] ) ) ) {
                    //These querystring vars must have been left over from an earlier link click, remove them
                    $to_url = remove_query_arg(array('default','d'),$this->currenturl_with_querystring);
                } else {
                    $to_url = $this->currenturl_with_querystring;
                }
                echo '<div class="updated fade"><p>' . __( 'Success! The default option was successfully turned off.', $this->localization_domain ) . '</p></div>';
            } elseif ( isset( $_GET['default'] ) && is_numeric( $_GET['default'] ) ) {
                if (! wp_verify_nonce($_GET['_wpnonce'], 'blog_templates-make_default') )
                    die( __( 'Whoops! There was a problem with the data you posted. Please go back and try again. (Generated by New Blog Templates)', $this->localization_domain ) );

                $this->options['default'] = (int)$_GET['default'];

                $this->save_admin_options();

                echo '<div class="updated fade"><p>' . __( 'Success! The default template was sucessfully updated.', $this->localization_domain ) . '</p></div>';
            } elseif ( isset( $_GET['d'] ) && is_numeric( $_GET['d'] ) ) {
                if (! wp_verify_nonce($_GET['_wpnonce'], 'blog_templates-delete_template') )
                    die( __( 'Whoops! There was a problem with the data you posted. Please go back and try again. (Generated by New Blog Templates)', $this->localization_domain ) );
                unset($this->options['templates'][$_GET['d']]);

                $this->save_admin_options();

                echo '<div class="updated fade"><p>' . __( 'Success! The template was sucessfully deleted.', $this->localization_domain ) . '</p></div>';
            } else if (isset($_POST['save_options'])) {
                if (! wp_verify_nonce($_POST['_nbtnonce'], 'blog_templates-update-options') )
                    die( __( 'Whoops! There was a problem with the data you posted. Please go back and try again. (Generated by New Blog Templates)', $this->localization_domain ) );
                $this->options['show-registration-templates'] = isset($_POST['show-registration-templates']) ? (int)$_POST['show-registration-templates'] : 0;
                $this->options['registration-templates-appearance'] = isset($_POST['registration-templates-appearance']) ? $_POST['registration-templates-appearance'] : '';
                $this->save_admin_options(); 
            }
        }

        private function render_row( $title, $markup ) {
            ?>
                <tr valign="top">
                    <th scope="row"><label for="site_name"><?php echo $title; ?></label></th>
                    <td>
                        <?php echo $markup; ?>          
                    </td>
                </tr>
            <?php
        }

        /**
        * Adds settings/options page
        *
        * @since 1.0
        */
        function admin_options_page() {
            $t = isset( $_GET['t'] ) ? (string) $_GET['t'] : '';

            global $pagenow;
            $url = $pagenow . '?page=blog_templates.php';

?>

<div class="wrap">
    <form method="post" id="options">
        <?php wp_nonce_field('blog_templates-update-options', '_nbtnonce'); 
        if ( ! is_numeric( $t ) ) { ?>
            <h2>Blog Templates</h2>
            <?php 
                $templates_table = new NBT_Templates_Table( $this->options ); 
                $templates_table->prepare_items();
                $templates_table->display();
            ?>
            
            <h2><?php _e('Create New Blog Template',$this->localization_domain); ?></h2>
            <p><?php _e('Create a blog template based on the blog of your choice! This allows you (and other admins) to copy all of the selected blog\'s settings and allow you to create other blogs that are almost exact copies of that blog. (Blog name, URL, etc will change, so it\'s not a 100% copy)',$this->localization_domain); ?></p>
            <p><?php _e('Simply fill out the form below and click "Create Blog Template!" to generate the template for later use!',$this->localization_domain); ?></p>
            <table class="form-table">
                <?php ob_start(); ?>
                    <input name="template_name" type="text" id="template_name" class="regular-text"/>
                <?php $this->render_row( __( 'Template Name:', $this->localization_domain ), ob_get_clean() ); ?>

                <?php ob_start(); ?>
                    <input name="copy_blog_id" type="text" id="copy_blog_id" class="small-text"/>
                <?php $this->render_row( __( 'Blog ID:', $this->localization_domain ), ob_get_clean() ); ?>

                <?php ob_start(); ?>
                    <textarea class="large-text" name="template_description" type="text" id="template_description" cols="45" rows="5"></textarea>
                <?php $this->render_row( __( 'Template Description:', $this->localization_domain ), ob_get_clean() ); ?>

                <?php 
                    ob_start(); 
                    $options_to_copy = array(
                        'settings' => __( 'Wordpress Settings, Current Theme, and Active Plugins', $this->localization_domain ),
                        'posts'    => __( 'Posts', $this->localization_domain ),
                        'pages'    => __( 'Pages', $this->localization_domain ),
                        'terms'    => __( 'Categories, Tags, and Links', $this->localization_domain ),
                        'users'    => __( 'Users', $this->localization_domain ),
                        'menus'    => __( 'Menus', $this->localization_domain ),
                        'files'    => __( 'Files', $this->localization_domain )
                        
                    );
                    foreach ( $options_to_copy as $key => $value ) {
                        echo "<span style='padding-right: 10px;'><input type='checkbox' name='to_copy[]' id='nbt-{$key}' value='$key'>&nbsp;<label for='nbt-{$key}'>$value</label></span><br/>";
                    }
                ?>
                <?php $this->render_row( __( 'What To Copy To New Blog?', $this->localization_domain ), ob_get_clean() ); ?>

                
                <?php if ( is_plugin_active( 'sitewide-privacy-options/sitewide-privacy-options.php' ) ): ?>
                    <?php ob_start(); ?>
                        <input type='checkbox' name='copy_status' id='nbt-copy-status' <?php checked( ! empty( $template['copy_status'] ) ); ?>>
                        <label for='nbt-copy-status'><?php _e( 'Check if you want also to copy the blog status (Public or not)', $this->localization_domain ); ?></label>
                    <?php $this->render_row( __( 'Copy Status?', $this->localization_domain ), ob_get_clean() ); ?>
                <?php endif; ?>

                <?php ob_start(); ?>
                    <p><?php _e('After you add this template, an advanced options area will show up on the edit screen (Click on the template name when it appears in the list above). In that advanced area, you can choose to add full tables to the template, in case you\'re using a plugin that creates its own database tables. Note that this is not required for new Blog Templates to work', $this->localization_domain); ?></p>
                <?php $this->render_row( __( 'Advanced', $this->localization_domain ), ob_get_clean() ); ?>
                
            </table>
            <p><?php _e('Please note that this will turn the blog you selected into a template blog. Any changes you make to this blog will change the template, as well! We recommend creating specific "Template Blogs" for this purpose, so you don\'t accidentally add new settings, content, or users that you don\'t want in your template.',$this->localization_domain); ?></p>
            <p><?php printf( __( 'This means that if you would like to create a dedicated template blog for this template, please <a href="%1$s">create a new blog</a> and then visit this page to create the template.',$this->localization_domain ), '<a href="' . ( get_bloginfo('version') >= 3 ) ? network_admin_url('site-new.php') : admin_url('wpmu-blogs.php') . '">'); ?></p>

            <p><div class="submit"><input type="submit" name="save_new_template" class="button-primary" value="Create Blog Template!" /></div></p>
            
            <h2><?php _e('Options', $this->localization_domain); ?></h2>
            <p>
                <label for="show-registration-templates">
                    <?php _e('Show templates selection on registration:', $this->localization_domain); ?>
                    <input type="checkbox"
                        <?php echo (
                            !empty($this->options['show-registration-templates']) ? 'checked="checked"' : ''
                        ); ?>
                        name="show-registration-templates" id="show-registration-templates" value="1" 
                    />
                </label>
            </p>
            <p>
                <?php _e('Selecting this option will allow your new users to choose between templates when they sign up for a site.', $this->localization_domain); ?>
            </p>
            <?php $appearance_template = $this->_get_config_option('registration-templates-appearance'); ?>
            <p>
                <label for="registration-templates-appearance-select">
                    <input type="radio"
                        <?php echo (
                            empty($appearance_template) ? 'checked="checked"' : ''
                        ); ?>
                        name="registration-templates-appearance" id="registration-templates-appearance-select" value="" 
                    />
                    <?php _e('As simple selection box', $this->localization_domain); ?>
                </label>
            </p>
            <p>
                <label for="registration-templates-appearance-description">
                    <input type="radio"
                        <?php echo (
                            'description' == $appearance_template ? 'checked="checked"' : ''
                        ); ?>
                        name="registration-templates-appearance" id="registration-templates-appearance-description" value="description" 
                    />
                    <?php _e('As radio-box selection with descriptions', $this->localization_domain); ?>
                </label>
            </p>
            <p>
                <label for="registration-templates-appearance-screenshot">
                    <input type="radio"
                        <?php echo (
                            'screenshot' == $appearance_template ? 'checked="checked"' : ''
                        ); ?>
                        name="registration-templates-appearance" id="registration-templates-appearance-screenshot" value="screenshot" 
                    />
                    <?php _e('As theme screenshot selection', $this->localization_domain); ?>
                </label>
            </p>
            <p>
                <label for="registration-templates-appearance-screenshot_plus">
                    <input type="radio"
                        <?php echo (
                            'screenshot_plus' == $appearance_template ? 'checked="checked"' : ''
                        ); ?>
                        name="registration-templates-appearance" id="registration-templates-appearance-screenshot_plus" value="screenshot_plus" 
                    />
                    <?php _e('As theme screenshot selection with titles and description', $this->localization_domain); ?>
                </label>
            </p>
            <?php
            /* Will be on next releases
            <p>
                <label for="registration-templates-appearance-previewer">
                    <input type="radio" <?php checked( 'previewer' == $appearance_template ); ?> name="registration-templates-appearance" id="registration-templates-appearance-previewer" value="previewer" />
                    <?php _e('As a theme previewer', $this->localization_domain); ?>
                </label>
            </p>*/
            ?>
            <p><div class="submit"><input type="submit" name="save_options" class="button-primary" value="<?php esc_attr_e(__('Save Options', $this->localization_domain));?>" /></div></p>
            
        <?php
            } else {
                $template = $this->options['templates'][$t];
        ?>
            <p><a href="<?php echo $url; ?>">&laquo; <?php _e('Back to Blog Templates', $this->localization_domain); ?></a></p>
            <h2><?php _e('Edit Blog Template', $this->localization_domain); ?></h2>
             <table class="form-table">
                <?php ob_start(); ?>
                    <input name="template_name" type="text" id="template_name" class="regular-text" value="<?php esc_attr_e( $template['name'] );?>"/>
                <?php $this->render_row( __( 'Template Name:', $this->localization_domain ), ob_get_clean() ); ?>

                <?php ob_start(); ?>
                    <textarea class="widefat" name="template_description" id="template_description" cols="45" rows="5"><?php echo esc_textarea($template['description']);?></textarea>
                <?php $this->render_row( __( 'Template Description', $this->localization_domain ), ob_get_clean() ); ?>

                <?php 
                    ob_start(); 
                    $options_to_copy = array(
                        'settings' => __( 'Wordpress Settings, Current Theme, and Active Plugins', $this->localization_domain ),
                        'posts'    => __( 'Posts', $this->localization_domain ),
                        'pages'    => __( 'Pages', $this->localization_domain ),
                        'terms'    => __( 'Categories, Tags, and Links', $this->localization_domain ),
                        'users'    => __( 'Users', $this->localization_domain ),
                        'menus'    => __( 'Menus', $this->localization_domain ),
                        'files'    => __( 'Files', $this->localization_domain )
                        
                    );
                    foreach ( $options_to_copy as $key => $value ) {
                        ?>
                            <input type="checkbox" name="to_copy[]" id="nbt-<?php echo $key; ?>" value="<?php echo $key; ?>" <?php checked( in_array( $key, $template['to_copy'] ) ); ?>> <label for='nbt-<?php echo $key; ?>'><?php echo $value; ?></label><br/>
                        <?php
                    }
                ?>
                <?php $this->render_row( __( 'What To Copy To New Blog?', $this->localization_domain ), ob_get_clean() ); ?>

                
                <?php if ( is_plugin_active( 'sitewide-privacy-options/sitewide-privacy-options.php' ) ): ?>
                    <?php ob_start(); ?>
                        <input type='checkbox' name='copy_status' id='nbt-copy-status' <?php checked( ! empty( $template['copy_status'] ) ); ?>>
                        <label for='nbt-copy-status'><?php _e( 'Check if you want also to copy the blog status (Public or not)', $this->localization_domain ); ?></label>
                    <?php $this->render_row( __( 'Copy Status?', $this->localization_domain ), ob_get_clean() ); ?>
                <?php endif; ?>
                
            </table>
            
            <p><div class="submit"><input type="submit" name="save_updated_template" value="<?php _e('Save', $this->localization_domain); ?> &raquo;" class="button-primary" /></div></p>

            
            <?php
                global $wpdb;

                switch_to_blog($template['blog_id']);
            ?>
            <h2><?php _e('Advanced Options',$this->localization_domain); ?></h2>
                        
            <p><?php printf(__('The tables listed here were likely created by plugins you currently have or have had running on this blog. If you want the data from these tables copied over to your new blogs, add a checkmark next to the table. Note that the only tables displayed here begin with %s, which is the standard table prefix for this specific blog. Plugins not following this convention will not have their tables listed here.',$this->localization_domain),$wpdb->prefix); ?></p>
            <table class="form-table">
                <?php ob_start();

                //Grab all non-core tables and display them as options
                // Changed
                $pfx = class_exists("m_wpdb") ? $wpdb->prefix : str_replace('_','\_',$wpdb->prefix);
                

                //$results = $wpdb->get_results("SHOW TABLES LIKE '" . str_replace('_','\_',$wpdb->prefix) . "%'", ARRAY_N);
                $results = $wpdb->get_results("SHOW TABLES LIKE '{$pfx}%'", ARRAY_N);

                if (!empty($results)) {

                    foreach($results as $result) {
                        if (!in_array(str_replace($wpdb->prefix,'',$result['0']),$wpdb->tables)) {

                            if (class_exists("m_wpdb")) {
                                $db = $wpdb->analyze_query("SHOW TABLES LIKE '{$pfx}%'");
                                $dataset = $db['dataset'];
                                $current_db = $wpdb->dbh_connections[$dataset];
                                $val = $current_db['name'] . '.' . $result[0];
                            } else {
                                $val =  $result[0];
                            }
                            if ( stripslashes_deep( $pfx ) == $wpdb->base_prefix ) {
                                // If we are on the main blog, we'll have to avoid those tables from other blogs
                                $pattern = '/^' . stripslashes_deep( $pfx ) . '[0-9]/';
                                if ( preg_match( $pattern, $result[0] ) )
                                    continue;
                            }
                            //echo "<input type='checkbox' name='additional_template_tables[]' value='$result[0]'";
                            echo "<input type='checkbox' name='additional_template_tables[]' value='{$val}'";
                            if ( isset( $template['additional_tables'] ) && is_array( $template['additional_tables'] ) )
                                //if ( in_array( $result[0], $template['additional_tables'] ) )
                                if ( in_array( $val, $template['additional_tables'] ) )
                                    echo ' checked="CHECKED"';
                            echo " id='nbt-{$val}'>&nbsp;<label for='nbt-{$val}'>{$result[0]}</label><br/>";
                        }
                    }
                } else {
                    _e('There are no additional tables to display for this blog',$this->localization_domain);
                }
                // End changed
                
                
                $this->render_row( __( 'Additional Tables', $this->localization_domain ), ob_get_clean() ); ?>

            </table>
            <p><div class="submit"><input type="submit" name="save_updated_template" value="<?php _e('Save', $this->localization_domain); ?> &raquo;" class="button-primary" /></div></p>
            <?php restore_current_blog(); ?>
        <?php } ?>
    </form>
<?php
        }

        /**
        * Adds field for Multi Domain addition and edition forms
        *
        * @since 1.2
        */
        function multi_domain_form_field( $domain = '' ) {
            if( count( $this->options['templates'] ) <= 1 ) // don't display field if there is only one template or none
                return false;
            ?>
            <tr>
                <th scope="row"><label for="blog_template"><?php _e( 'Default Blog Template', $this->localization_domain ) ?>:</label></th>
                <td>
                    <select id="blog_template" name="blog_template">
                        <option value="">Default</option>
                        <?php
                        foreach( $this->options['templates'] as $key => $blog_template ) {
                            $selected = isset( $domain['blog_template'] ) ? selected( $key, $domain['blog_template'], false ) : '';
                            echo "<option value='$key'$selected>$blog_template[name]</option>";
                        }
                        ?>
                    </select><br />
                    <span class="description"><?php _e( 'Default Blog Template used for this domain.', $this->localization_domain ) ?></span>
                </td>
            </tr>
            <?php
        }

        private function _get_config_option ($key, $default=false) {
            if (empty($this->options)) return $default;
            if (empty($this->options[$key])) return $default;
            return $this->options[$key];
        }

        /**
         * Proper blog filesystem path finding.
         * @param  int $blog_id Blog ID to check
         * @return string Filesystem path
         */
        private function _get_files_fs_path ($blog_id) {
            if (!is_numeric($blog_id)) return false;
            switch_to_blog($blog_id);
            $info = wp_upload_dir();
            restore_current_blog();
            return !empty($info['basedir']) 
                ? $info['basedir'] 
                : false
            ;
        }

        /**
        * Save Blog Template value in the current domain array
        *
        * @since 1.2
        */
        function multi_domain_update_domain( $current_domain, $domain ) {
            $current_domain['blog_template'] = isset( $domain['blog_template'] ) ? $domain['blog_template'] : '';

            return $current_domain;
        }

        /**
        * Adds Blog Template column to Multi-Domains table
        *
        * @since 1.2
        */
        function manage_multi_domains_columns( $columns ) {
            $columns['blog_template'] = __( 'Blog Template', $this->localization_domain );
            return $columns;
        }

        /**
        * Display content of the Blog Template column in the Multi-Domains table
        *
        * @since 1.2
        */
        function manage_multi_domains_custom_column( $column_name, $domain ) {
            if( 'blog_template' == $column_name ) {
                if( !isset( $domain['blog_template'] ) ) {
                    echo 'Default';
                } elseif( !is_numeric( $domain['blog_template'] ) ) {
                    echo 'Default';
                } else {
                    $key = $domain['blog_template'];
                    echo $this->options['templates'][$key]['name'];
                }
            }
        }

        /**
         * Exclude blog templates from Blogs Directory list
         **/
        function blogs_directory_blogs_list( $blogs ) {
            $blog_templates = $this->options;
            $blog_templates_ids = array();
            foreach ( $blog_templates['templates'] as $template ) {
                $blog_templates_ids[] = $template['blog_id'];
            }

            foreach ( $blogs as $key => $blog ) {
                if ( in_array( $blog['blog_id'], $blog_templates_ids ) )
                    unset( $blogs[$key] );
            }

            return $blogs;
        }

        /**
         * Exclude blog templates from Blogs Directory count
         **/
        function blogs_directory_blogs_count( $count ) {
            return $count - count( $this->options );
        }
        
        /**
         * Shows template selection on registration.
         */
        function registration_template_selection () {
            if (!$this->options['show-registration-templates']) return false;
            // Setup vars
            $templates = $this->options['templates'];

            $templates_to_remove = array();
            foreach ( $templates as $key => $template ) {

                if ( is_main_site( $template['blog_id'] ) )
                    $templates_to_remove[] = $key;
            }

            if ( ! empty( $templates_to_remove ) ) {
                foreach ( $templates_to_remove as $key )
                    unset( $templates[ $key ] );
            }


            $tpl_file_suffix = $this->options['registration-templates-appearance'] ? '-' . $this->options['registration-templates-appearance'] : '';
            $tpl_file = "blog_templates-registration{$tpl_file_suffix}.php";

            // Setup theme file
            $theme_file = locate_template(array($tpl_file));
            $theme_file = $theme_file ? $theme_file : $this->thispluginpath . 'template/' . $tpl_file;
            if (!file_exists($theme_file)) return false;
            
            // Do the thing
            @include $theme_file;
        }
        
        /**
         * Store selected template in blog meta on signup.
         */
        function registration_template_selection_add_meta ($meta) {
            $meta = $meta ? $meta : array();
            $meta['blog_template'] = @$_POST['blog_template'];
            return $meta;
        }

        /**
         * Reset the terms counts to 0
         * 
         * @param Integer $blog_id 
         */
        function reset_terms_counts( $blog_id ) {
            
            global $wpdb;
            $result = $wpdb->query( "UPDATE $wpdb->term_taxonomy SET count = 0" );
        }

    } // End Class

    // instantiate the class
    $blog_templates =& new blog_templates();

} // End if blog_templates class exists statement
