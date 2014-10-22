<?php

class InfusionsoftOneClickUpsell
{

    /**
     * Setup the plugin. This is called automatically when the plugin is loaded.
     */
    public function __construct()
    {
        // Check to make sure the Infusionsoft SDK is installed.
        add_action( 'admin_notices', array($this, 'adminNotices') );

        // Add action links to the plugin listing
        add_filter( 'plugin_action_links', array($this, 'pluginActionLinks'), 10, 2 );

        // Add admin menus
        add_action('admin_menu', array($this, 'adminMenu') );

        // Add upsell/downsell shortcodes
        add_shortcode('upsell', array($this, 'shortcodeUpsell'));
        add_shortcode('downsell', array($this, 'shortcodeDownsell'));

        // Add shortcode AJAX handlers
        add_action('wp_ajax_process_upsell', array($this, 'processUpsell'));
        add_action('wp_ajax_nopriv_process_upsell', array($this, 'processUpsell'));

        // Enqueue jQuery
        add_action( 'admin_enqueue_scripts', array($this, 'adminEnqueueScripts'), 8 );
    }

    /**
     * Handle upsell shortcode.
     *
     * @param array $attributes
     * @return string
     */
    public function shortcodeUpsell($attributes)
    {
        // Turn testing on or off based on passed in attribute
        $test = isset($attributes['test']);

        // List of possible attributes
        $availableAttributes = array(
            'success_url',
            'failure_url',
            'action_set_id',
            'id',
            'class',
            'button_text',
        );

        // Fill in missing attributes using global options
        foreach($availableAttributes as $attribute) {
            if(!isset($attributes[$attribute])) {
                $attributes[$attribute] = get_option('novaksolutions_upsell_default_' . $attribute);
            }
        }

        // Make sure a button text is always set
        if(empty($attributes['button_text'])) {
            $attributes['button_text'] = 'Yes!';
        }

        // If missing any of the image attributes, use global defaults
        if(!isset($attributes['image'], $attributes['image_width'], $attributes['image_height'])) {
            $attributes['image'] = get_option('novaksolutions_upsell_default_image');
            $attributes['image_width'] = get_option('novaksolutions_upsell_default_image_width');
            $attributes['image_height'] = get_option('novaksolutions_upsell_default_image_height');

            // If missing any of the image attributes, do not use image for the submit button
            if(empty($attributes['image']) || empty($attributes['image_width']) || empty($attributes['image_height'])){
                $useImage = false;
            } else {
                $useImage = true;
            }
        } else {
            $useImage = true;
        }

        // Check to make sure required fields are set
        if(!$attributes['success_url'] || !$attributes['failure_url'] || !get_option('novaksolutions_upsell_merchantaccount_id')) {
            return "ERROR: The Infusionsoft One-click Upsell plugin requires a success URL, failure URL, and merchant account ID.";
        }

        // Product ID is required. Make sure it is set.
        if(empty($attributes['product_id']) && empty($attributes['subscription_id'])){
            return "ERROR: The Infusionsoft One-click Upsell shortcode requires a product ID or subscription ID.";
        }

        if(!empty($attributes['product_id']) && !empty($attributes['subscription_id'])){
            return "ERROR: The Infusionsoft One-click Upsell shortcode cannot have both a product ID and a subscription ID.";
        }

        // Order ID is required. Make sure we have it.
        if(isset($_GET['orderId']) && $_GET['orderId']) {
            $order_id = $_GET['orderId'];
        } else {
            $order_id = '';
        }

        // Contact ID is required. Make sure we have it.
        if(isset($_GET['contactId'])) {
            $contact_id = $_GET['contactId'];
        } elseif(isset($_GET['inf_field_FirstName'], $_GET['inf_field_LastName'])) {
            $contact_id = $this->getContactId($order_id, $_GET['inf_field_FirstName'], $_GET['inf_field_LastName']);
        } else {
            $contact_id = '';
        }

        // Set default checksum. This is used to give unique IDs for elements.
        $checksum = '';

        // Initialize output variable
        $output = '';

        // If test mode is enabled, add to output variable
        $output .= $test ? 'Test Mode Enabled' : '';

        // If we are using an image for the submit button, add appropriate CSS and generate checksum
        if($useImage) {
            $checksum = sprintf("-%u", crc32($attributes['image']));

            $output .= <<<CSS
<style>
.ns-one-click-upsell-button{$checksum} {
    background: transparent url("{$attributes['image']}") 0 0 no-repeat;
    color: transparent !important;
    font-size: 0;
    line-height: 0;
    display: block;
    text-align: center;
    cursor: pointer;
    width: {$attributes['image_width']}px !important;
    height: {$attributes['image_height']}px !important;
    border: 0;
}
</style>
CSS;
        }

        // Add upsell form and button to output
        $output .= '<form action="' . site_url('wp-admin/admin-ajax.php?action=process_upsell') . '" method="post">';

        $output .= $test ? 'Contact ID:' : '';
        $output .= '<input type="' . ($test ? 'text' : 'hidden') . '" name="contact_id" value="' . htmlentities($contact_id) . '"> ' . ($test ? '<br />' : '');

        $output .= $test ? 'Existing Order Id:' : '';
        $output .= '<input type="' . ($test ? 'text' : 'hidden') . '" name="order_id" value="' . htmlentities($order_id) . '"> ' . ($test ? '<br />' : '');

        if(!empty($attributes['product_id'])) {
            $output .= $test ? 'Product Id:' : '';
            $output .= '<input type="' . ($test ? 'text' : 'hidden') . '" name="product_id" value="' . htmlentities($attributes['product_id']) . '"> ' . ($test ? '<br />' : '');
        } else {
            $output .= $test ? 'Subscription Id:' : '';
            $output .= '<input type="' . ($test ? 'text' : 'hidden') . '" name="subscription_id" value="' . htmlentities($attributes['subscription_id']) . '"> ' . ($test ? '<br />' : '');
        }

        $output .= $test ? 'Success Url:' : '';
        $output .= '<input type="' . ($test ? 'text' : 'hidden') . '" name="success_url" value="' . htmlentities($attributes['success_url']) . '"> ' . ($test ? '<br />' : '');

        $output .= $test ? 'Success Url:' : '';
        $output .= '<input type="' . ($test ? 'text' : 'hidden') . '" name="failure_url" value="' . htmlentities($attributes['failure_url']) . '"> ' . ($test ? '<br />' : '');

        $output .= $test ? 'Test:' : '';
        $output .= '<input type="' . ($test ? 'text' : 'hidden') . '" name="test" value="' . ($test ? 'true' : 'false') . '"> ' . ($test ? '<br />' : '');

        $output .= $test ? 'ActionSetId:' : '';
        $output .= '<input type="' . ($test ? 'text' : 'hidden') . '" name="action_set_id" value="' . htmlentities($attributes['action_set_id']) . '"> ' . ($test ? '<br />' : '');

        $output .= '<input type="'.get_option('novaksolutions_upsell_button_type', 'submit').'" onclick="this.disabled=true; this.form.submit(); return false;" value="' . htmlentities($attributes['button_text']) . '"';

        if($attributes['class']) {
            $output .= ' class="ns-one-click-upsell-button'.$checksum.' ' . htmlentities($attributes['class']) . '"';
        } else {
            $output .= ' class="ns-one-click-upsell-button'.$checksum.'"';
        }

        if($attributes['id']) {
            $output .= ' id="' . htmlentities($attributes['id']) . '"';
        }

        $output .= '></form>';

        return $output;
    }

    /**
     * Handle downsell shortcode.
     *
     * @param array $attributes
     * @return string
     */
    function shortcodeDownsell()
    {
        $order_id = isset($_GET['orderId']) ? $_GET['orderId'] : false;

        if(isset($_GET['contactId'])) {
            $contact_id = $_GET['contactId'];
        } elseif(isset($_GET['inf_field_FirstName'], $_GET['inf_field_LastName'])) {
            $contact_id = $this->getContactId($order_id, $_GET['inf_field_FirstName'], $_GET['inf_field_LastName']);
        } else {
            $contact_id = false;
        }

        return 'orderId=' . $order_id . '&contactId=' . $contact_id;
    }

    /**
     * Handle admin notices.
     */
    public function adminNotices()
    {
        // Check if the Infusionsoft SDK plugin is active and configured
        if( !is_plugin_active( 'infusionsoft-sdk/infusionsoft-sdk.php' )){
            // Display an error message if the SDK plugin isn't active.
            echo "<div class=\"error\"><p><strong><em>Infusionsoft One-click Upsell</em> requires the <em>Infusionsoft SDK</em> plugin. Please install and activate the <em>Infusionsoft SDK</em> plugin.</strong></p></div>";
        } elseif (!get_option('infusionsoft_sdk_app_name') || !get_option('infusionsoft_sdk_api_key')) {
            // Display an error message if the app name and API key aren't configured.
            echo "<div class=\"error\"><p><strong><em>Infusionsoft One-click Upsell</em> requires the <em>Infusionsoft SDK</em> plugin. Please set your Infusionsoft app name and API key on the <em>Infusionsoft SDK</em> <a href=\"" . admin_url( 'options-general.php?page=infusionsoft-sdk/infusionsoft-sdk.php' ) . "\">settings page.</a></strong></p></div>";
        }
    }

    /**
     * Determine if the SDK is active and configured.
     *
     * @return bool Whether the SDK is added and configured
     */
    public function hasSDK()
    {
        if(
            class_exists( 'Infusionsoft_Classloader' ) &&
            get_option('infusionsoft_sdk_app_name') &&
            get_option('infusionsoft_sdk_api_key')
        ){
            return true;
        } else {
            return false;
        }
    }

    /**
     * Add additional links to the plugin listings page.
     *
     * @param array $links
     * @param string $file
     * @return array
     */
    public function pluginActionLinks( $links, $file )
    {
        if ( $file == plugin_basename( dirname(__FILE__) . '/infusionsoft-one-click-upsell.php' ) ) {
            $links[] = '<a href="' . admin_url( 'admin.php?page=novaksolutions_upsell_admin_menu' ) . '">'.__( 'Settings' ).'</a>';
            $links[] = '<a href="http://novaksolutions.com/integrations/wordpress/?utm_source=wordpress&utm_medium=link&utm_content=upsell&utm_campaign=more-plugins">More Plugins by Novak Solutions</a>';
        }

        return $links;
    }

    /**
     * Add menu pages to the admin navigation.
     */
    public function adminMenu()
    {
        // Add configuration pages to menu
        add_menu_page( "Infusionsoft One-click Upsell", "One-click Upsell", "manage_options", "novaksolutions_upsell_admin_menu", array($this, "adminPage") );
        add_submenu_page( "novaksolutions_upsell_admin_menu", "Infusionsoft One-click Upsell", "Settings", "manage_options", "novaksolutions_upsell_admin_menu", array($this, "adminPage"));
        add_submenu_page( "novaksolutions_upsell_admin_menu", "One-click Upsell Usage Instructions", "Usage", "edit_posts", "novaksolutions_upsell_usage", array($this, "displayUsage"));
        add_submenu_page( "novaksolutions_upsell_admin_menu", "Product Upsell Links", "Product Upsell Links", "edit_posts", "novaksolutions_upsell_product_links", array($this, "displayProductLinks"));
        add_submenu_page( "novaksolutions_upsell_admin_menu", "Subscription Links", "Subscription Links", "edit_posts", "novaksolutions_upsell_subscription_links", array($this, "displaySubscriptionLinks"));

        // Configure menu pages
        add_action('admin_init', array($this, 'adminInit') );
    }

    /**
     * Configure menu pages and shortcode JS.
     */
    public function adminInit()
    {
        // Add the section
        add_settings_section(
            'novaksolutions_upsell_setting_section_app',
            'Infusionsoft App',
            null,
            'novaksolutions-upsell-settings'
        );

        add_settings_section(
            'novaksolutions_upsell_setting_section_defaults',
            'Upsell Defaults',
            null,
            'novaksolutions-upsell-settings'
        );

        add_settings_section(
            'novaksolutions_upsell_setting_section_optional',
            'Optional Settings',
            null,
            'novaksolutions-upsell-settings'
        );

        // Add the settings fields
        add_settings_field(
            'novaksolutions_upsell_merchantaccount_id',
            'Merchant Account ID',
            array($this, 'fieldMerchantAccountId'),
            'novaksolutions-upsell-settings',
            'novaksolutions_upsell_setting_section_app'
        );

        add_settings_field(
            'novaksolutions_upsell_test_merchantaccount_id',
            'Test Merchant Account ID',
            array($this, 'fieldTestMerchantAccountId'),
            'novaksolutions-upsell-settings',
            'novaksolutions_upsell_setting_section_app'
        );

        add_settings_field(
            'novaksolutions_upsell_default_success_url',
            'Default Success URL',
            array($this, 'fieldDefaultSuccessUrl'),
            'novaksolutions-upsell-settings',
            'novaksolutions_upsell_setting_section_defaults'
        );

        add_settings_field(
            'novaksolutions_upsell_default_failure_url',
            'Default Failure URL',
            array($this, 'fieldDefaultFailureUrl'),
            'novaksolutions-upsell-settings',
            'novaksolutions_upsell_setting_section_defaults'
        );

        add_settings_field(
            'novaksolutions_upsell_default_button_text',
            'Default Button Text',
            array($this, 'fieldDefaultButtonText'),
            'novaksolutions-upsell-settings',
            'novaksolutions_upsell_setting_section_defaults'
        );

        add_settings_field(
            'novaksolutions_upsell_default_action_set_id',
            'Default Action Set ID',
            array($this, 'fieldDefaultActionSetId'),
            'novaksolutions-upsell-settings',
            'novaksolutions_upsell_setting_section_defaults'
        );

        add_settings_field(
            'novaksolutions_upsell_default_id',
            'Default Button CSS ID',
            array($this, 'fieldDefaultId'),
            'novaksolutions-upsell-settings',
            'novaksolutions_upsell_setting_section_defaults'
        );

        add_settings_field(
            'novaksolutions_upsell_default_class',
            'Default Button CSS Class',
            array($this, 'fieldDefaultClass'),
            'novaksolutions-upsell-settings',
            'novaksolutions_upsell_setting_section_defaults'
        );

        add_settings_field(
            'novaksolutions_upsell_default_image',
            'Default Button Image',
            array($this, 'fieldDefaultImage'),
            'novaksolutions-upsell-settings',
            'novaksolutions_upsell_setting_section_defaults'
        );

        add_settings_field(
            'novaksolutions_upsell_default_image_width',
            'Default Button Image Width',
            array($this, 'fieldDefaultImageWidth'),
            'novaksolutions-upsell-settings',
            'novaksolutions_upsell_setting_section_defaults'
        );

        add_settings_field(
            'novaksolutions_upsell_default_image_height',
            'Default Button Image Height',
            array($this, 'fieldDefaultImageHeight'),
            'novaksolutions-upsell-settings',
            'novaksolutions_upsell_setting_section_defaults'
        );

        add_settings_field(
            'novaksolutions_upsell_button_type',
            'Button Type',
            array($this, 'fieldButtonType'),
            'novaksolutions-upsell-settings',
            'novaksolutions_upsell_setting_section_optional'
        );

        // Register our setting
        register_setting('novaksolutions-upsell-settings', 'novaksolutions_upsell_merchantaccount_id', array($this, 'callbackSanitizeAbsint'));
        register_setting('novaksolutions-upsell-settings', 'novaksolutions_upsell_test_merchantaccount_id', array($this, 'callbackSanitizeAbsint'));

        register_setting('novaksolutions-upsell-settings', 'novaksolutions_upsell_default_success_url', 'esc_url');
        register_setting('novaksolutions-upsell-settings', 'novaksolutions_upsell_default_failure_url', 'esc_url');
        register_setting('novaksolutions-upsell-settings', 'novaksolutions_upsell_default_button_text');
        register_setting('novaksolutions-upsell-settings', 'novaksolutions_upsell_default_action_set_id', array($this, 'callbackSanitizeAbsint'));
        register_setting('novaksolutions-upsell-settings', 'novaksolutions_upsell_default_id', 'sanitize_html_class');
        register_setting('novaksolutions-upsell-settings', 'novaksolutions_upsell_default_class', 'sanitize_html_class');

        register_setting('novaksolutions-upsell-settings', 'novaksolutions_upsell_default_image', 'esc_url');
        register_setting('novaksolutions-upsell-settings', 'novaksolutions_upsell_default_image_width', array($this, 'callbackSanitizeAbsint'));
        register_setting('novaksolutions-upsell-settings', 'novaksolutions_upsell_default_image_height', array($this, 'callbackSanitizeAbsint'));

        register_setting('novaksolutions-upsell-settings', 'novaksolutions_upsell_button_type', array($this, 'callbackSanitizeButtonType'));

        // Load MCE plugins
        if ( current_user_can( 'edit_posts' ) && current_user_can( 'edit_pages' ) ) {
            if ( in_array(basename($_SERVER['PHP_SELF']), array('post-new.php', 'page-new.php', 'post.php', 'page.php') ) ) {
                add_filter('mce_buttons', array($this, 'filterMceButton'));
                add_filter('mce_external_plugins', array($this, 'filterMcePlugin'));
                add_action('edit_form_advanced', array($this, 'shortcodeMceHandler'));
                add_action('edit_page_form', array($this, 'shortcodeMceHandler'));
            }
        }
    }

    /**
     * Load shortcode button JS
     */
    public function filterMcePlugin($plugins) {
        $plugins['oneclickupsell'] = plugins_url("js/editor_plugin.js", __FILE__);

        return $plugins;
    }

    /**
     * Add shortcode buttons to MCE
     */
    public function filterMceButton($buttons) {
        array_push( $buttons, '|', 'oneclickupsell', 'oneclickdownsell' );

        return $buttons;
    }

    /**
     * Display success URL field
     */
    public function fieldDefaultSuccessUrl()
    {
        echo '<input type="text" name="novaksolutions_upsell_default_success_url" value="' . get_option('novaksolutions_upsell_default_success_url') . '" size="45" /><br />';
        echo '<span class="description"><strong>REQUIRED:</strong> Unless you specify a different success URL in your shortcode, your customer will be directed to this URL after a successful upsell.</span>';
    }

    /**
     * Display failure URL field
     */
    public function fieldDefaultFailureUrl()
    {
        echo '<input type="text" name="novaksolutions_upsell_default_failure_url" value="' . get_option('novaksolutions_upsell_default_failure_url') . '" size="45" /><br />';
        echo '<span class="description"><strong>REQUIRED:</strong> Unless you specify a different failure URL in your shortcode, your customer will be directed to this URL if something goes wrong during the upsell (such as a rejected credit card).</span>';
    }

    /**
     * Display button text field
     */
    public function fieldDefaultButtonText()
    {
        echo '<input type="text" name="novaksolutions_upsell_default_button_text" value="' . get_option('novaksolutions_upsell_default_button_text') . '" size="45" /><br />';
        echo '<span class="description">This will be used for the button text unless you specify the button text in your shortcode.</span>';
    }

    /**
     * Display merchant account ID field
     */
    public function fieldMerchantAccountId()
    {
        $merchantId = $this->getMerchantAccounts();
        echo '<input type="text" name="novaksolutions_upsell_merchantaccount_id" value="' . get_option('novaksolutions_upsell_merchantaccount_id') . '" /><br />';
        if($merchantId !== false) {
            echo '<p><span class="description">Based on your Infusionsoft account history, this should probably be set to: <strong>'.$merchantId.'</strong></span></p>';
        }
        echo '<p><span class="description"><strong>REQUIRED:</strong> This merchant account will be used when processing upsell orders. To find your merchant account ID, open Infusionsoft and go to E-Commerce&rarr;Settings&rarr;Merchant Accounts. The account ID will be listed after "ID=" in the edit URL for the merchant account you\'d like to use.</span></p>';
    }

    /**
     * Display test merchant account ID field
     */
    public function fieldTestMerchantAccountId()
    {
        echo '<input type="text" name="novaksolutions_upsell_test_merchantaccount_id" value="' . get_option('novaksolutions_upsell_test_merchantaccount_id') . '" /><br />';
        echo '<span class="description">This merchant account will be used when test="true" is present in the upsell shortcode.</span>';
    }

    /**
     * Display action set ID field
     */
    public function fieldDefaultActionSetId()
    {
        echo '<input type="text" name="novaksolutions_upsell_default_action_set_id" value="' . get_option('novaksolutions_upsell_default_action_set_id') . '" /><br />';
        echo '<span class="description">Unless you specify a different action set ID in your shortcode, we will run this action set after a successful upsell. To find your action set ID, open Infusionsoft and go to E-commerce&rarr;Actions. Click Actions for the set you\'d like to use. The action set ID is listed after "ID=" in the URL of the window that opens after clicking the Actions button.</span>';
    }

    /**
     * Display CSS ID field
     */
    public function fieldDefaultId()
    {
        echo '<input type="text" name="novaksolutions_upsell_default_id" value="' . get_option('novaksolutions_upsell_default_id') . '" /><br />';
        echo '<span class="description">You can style your upsell button by adding a CSS ID to it.</span>';
    }

    /**
     * Display CSS class field
     */
    public function fieldDefaultClass()
    {
        echo '<input type="text" name="novaksolutions_upsell_default_class" value="' . get_option('novaksolutions_upsell_default_class') . '" /><br />';
        echo '<span class="description">You can style your upsell button by adding a CSS class to it.</span>';
    }

    /**
     * Display image field
     */
    public function fieldDefaultImage()
    {
        echo '<input type="text" name="novaksolutions_upsell_default_image" value="' . get_option('novaksolutions_upsell_default_image') . '" size="45" /><br />';
        echo '<span class="description">You can optionally use an image for your upsell button. Please specify the full URL to the image.</span>';
    }

    /**
     * Display image width field
     */
    public function fieldDefaultImageWidth()
    {
        echo '<input type="text" name="novaksolutions_upsell_default_image_width" value="' . get_option('novaksolutions_upsell_default_image_width') . '" />px<br />';
        echo '<span class="description">If you use an image for your upsell button, you must specify the width of the image in pixels.</span>';
    }

    /**
     * Display image height field
     */
    public function fieldDefaultImageHeight()
    {
        echo '<input type="text" name="novaksolutions_upsell_default_image_height" value="' . get_option('novaksolutions_upsell_default_image_height') . '" />px<br />';
        echo '<span class="description">If you use an image for your upsell button, you must specify the height of the image in pixels.</span>';
    }

    /**
     * Display button type field
     */
    public function fieldButtonType()
    {
        $allowed = array('submit', 'button');

        echo '<select name="novaksolutions_upsell_button_type">';
        foreach($allowed as $type) {
            echo '<option';
            if(get_option('novaksolutions_upsell_button_type') == $type) echo ' selected="selected"';
            echo '>' . $type . '</option>';
        }
        echo '</select><br />';
        echo '<span class="description">You can specify which type of button you would like to use, in case your theme doesn\'t work well with submit buttons.</span>';
    }

    /**
     * Get the absolute value of an attribute.
     *
     * @param string $value
     * @return int
     */
    public function callbackSanitizeAbsint($value)
    {
        $value = absint($value);
        return $value === 0 ? '' : $value;
    }

    /**
     * Make sure the selected button type is valid
     *
     * @param string $value
     * @return string
     */
    public function callbackSanitizeButtonType($value)
    {
        $allowed = array('submit', 'button');

        return (in_array($value, $allowed)) ? $value : 'submit';
    }

    /**
     * Use the API to find the most commonly used merchant account ID
     *
     * @return int
     */
    public function getMerchantAccounts()
    {
        // Check if SDK plugin is active
        if( !$this->hasSDK()){
            return false;
        }

        // Find out what merchant ID was used recently so it can be suggested to the user.
        try{
            Infusionsoft_AppPool::addApp(new Infusionsoft_App(get_option('infusionsoft_sdk_app_name') . '.infusionsoft.com', get_option('infusionsoft_sdk_api_key')));
            $merchants = Infusionsoft_DataService::queryWithOrderBy(new Infusionsoft_CCharge(), array('Id' => '%'), 'Id', false, 100, 0, array('MerchantId'));
            $m = array();
            foreach($merchants as $merchant) {
                if(!isset($m[$merchant->MerchantId])) {
                    $m[$merchant->MerchantId] = 0;
                }
                $m[$merchant->MerchantId]++;
            }

            if(!empty($m)){
                $max = array_keys($m, max($m));
                return $max[0];
            }
        } catch(Infusionsoft_Exception $e) {
            return false;
        }
    }

    /**
     * Display the main admin page.
     */
    public function adminPage()
    {
        echo '<h2>Infusionsoft One-click Upsell Settings</h2>';

        $this->getProducts();
        settings_errors();

        echo '<form method="POST" action="options.php">';
        settings_fields('novaksolutions-upsell-settings');   //pass slug name of page
        do_settings_sections('novaksolutions-upsell-settings');    //pass slug name of page
        submit_button();
        echo '</form>';

        $this->displayLinkBack();
    }

    /**
     * Display usage instructions for the plugin
     */
    public function displayUsage()
    {
        ?>
        <h2>One-click Upsell Usage Instructions</h2>

        <h3>Upsell Usage</h3>

        <p>Several settings are required. Your upsell button will not be displayed unless you have provided a value for all of the required settings.</p>

        <p><strong>Example usage:</strong> [upsell product_id="12"]</p>
        <p>Use the upsell shortcode on any Thank You page or post. Make sure you select the option in Infusionsoft to pass the contact's information to the Thank You page.</p>
        <p>Once the customer clicks the upsell button, One-click Upsell will charge the customer's last used credit card and place the order in Infusionsoft.</p>

        <h4>Available attributes:</h4>

        <p><strong>product_id</strong> &ndash; Unless you specify a subscription_id, you must specify a product_id. This should contain the numeric product ID from Infusionsoft. You can find a sample list of your products on the <a href="<?php echo admin_url( 'admin.php?page=novaksolutions_upsell_product_links' ); ?>">Product Upsell Links</a> page.</p>

        <p><strong>subscription_id</strong> &ndash; Unless you specify a product_id, you must specify a subscription_id. This should contain the numeric subscription ID from Infusionsoft. You can find a sample list of your subscriptions on the <a href="<?php echo admin_url( 'admin.php?page=novaksolutions_upsell_subscription_links' ); ?>">Subscription Plan Upsell Links</a> page.</p>

        <p><strong>button_text</strong> &ndash; You can change the text of the upsell button by setting the button_text parameter. Default value is: Yes!</p>

        <p><strong>id</strong> &ndash; You can add a CSS ID to your upsell button with the ID parameter.</p>

        <p><strong>class</strong> &ndash; You can add a CSS class to your upsell button with the class parameter.</p>

        <p><strong>success_url</strong> &ndash; If the upsell is successful, the customer will be redirected to this URL.</p>

        <p><strong>failure_url</strong> &ndash; If the upsell fails (for example, if the credit card is declined), the customer will be redirected to this URL.</p>

        <p><strong>action_set_id</strong> &ndash; Optionally run an Infusionsoft action set if the order is successful.</p>

        <p><strong>image</strong> &ndash; A full URL to an image to use for the button. If you don't specify an image, a standard submit button will be used.</p>

        <p><strong>image_width</strong> &ndash; If you choose to use an image, you <strong>must</strong> specify the image width in pixels. Your image will not be used if you do not use this attribute.</p>

        <p><strong>image_height</strong> &ndash; If you choose to use an image, you <strong>must</strong> specify the image height in pixels. Your image will not be used if you do not use this attribute.</p>

        <p><strong>test</strong> &ndash; To enable test mode for this upsell, set this parameter to <em>true</em>. This will give you additional debugging information, and send the transaction through the test merchant account that you've configured.</p>

        <h3>Downsell Usage</h3>

        <p><strong>Example usage:</strong> <em>&lt;a href="/some-wordpress-post/?[downsell]"&gt;No Thanks&lt;/a&gt;</em></p>

        <p>To add contact information to a URL so you can handle downsells, add <strong>?[downsell]</strong> to the end of any link. This will add the required URL parameters to the URL as if the user had come directly from the Infusionsoft cart or order form.</p>

        <?php
        $this->displayLinkBack();
    }

    public function displayLinkBack()
    {
        echo '<h2>Like this plugin?</h2>';
        echo '<p>If you found this plugin useful, please <a href="http://wordpress.org/support/view/plugin-reviews/infusionsoft-one-click-upsell">rate it in the plugin directory</a>.</p>';
        echo '<p>Visit <a href="http://novaksolutions.com/?utm_source=wordpress&utm_medium=link&utm_campaign=upsell">Novak Solutions</a> to find dozens of free tips, tricks, and tools to help you get the most out of Infusionsoft.</p>';
    }

    public function displayProductLinks()
    {
        echo '<h2>Product Upsell Links</h2>';

        $products = $this->getProducts();
        settings_errors();

        echo '<p>Getting started with the <em>Infusionsoft One-click Upsell</em> plugin is easy. Simply include one of these <em>upsell</em> shortcodes in your order "thank you" page.</p>';

        if(count($products) > 0){
            echo '<p><em>Showing the first <strong>' . number_format(count($products)). '</strong> products in your Infusionsoft app.</em></p>';
            echo '<ul>';
            foreach($products as $product){
                echo '<li>[upsell product_id="' . $product->Id . '" button_text="Buy ' . htmlentities($product->ProductName) . ' Now!"]</li>';
            }
            echo '</ul>';
        } else {
            echo "<p><strong>We weren't able to find any products in your Infusionsoft app. Please make sure your app name and API key are correct on the settings page.</strong></p>";
        }

        $this->displayLinkBack();
    }

    public function displaySubscriptionLinks()
    {
        echo '<h2>Subscription Plan Upsell Links</h2>';

        $subscriptions = $this->getSubscriptions();
        $products = $this->getProducts();
        settings_errors();

        echo '<p>Getting started with the <em>Infusionsoft One-click Upsell</em> plugin is easy. Simply include one of these <em>upsell</em> shortcodes in your order "thank you" page.</p>';

        if(count($subscriptions) > 0){
            echo '<p><em>Showing the first <strong>' . number_format(count($subscriptions)). '</strong> subscription plans in your Infusionsoft app.</em></p>';
            echo '<ul>';
            foreach($subscriptions as $subscription){
                if(isset($products[$subscription->ProductId])){
                    echo '<li>[upsell subscription_id="' . $subscription->Id . '" button_text="Subscribe to ' . htmlentities($products[$subscription->ProductId]->ProductName) . ' for ' . $subscription->getHumanizedPrice() . ' "]</li>';
                }
            }
            echo '</ul>';
        } else {
            echo "<p><strong>We weren't able to find any subscription plans in your Infusionsoft app. Please make sure your app name and API key are correct on the settings page.</strong></p>";
        }

        $this->displayLinkBack();
    }

    public function getProducts()
    {
        // Make sure the SDK is enabled and configured
        if (!$this->hasSDK()) {
            $products = array();
            return $products;
        }

        $enough_to_go = false;
        $valid_key = true;
        if(get_option('infusionsoft_sdk_app_name') != '' && get_option('infusionsoft_sdk_api_key') != ''){
            $enough_to_go = true;

            try{
                Infusionsoft_AppPool::addApp(new Infusionsoft_App(get_option('infusionsoft_sdk_app_name') . '.infusionsoft.com', get_option('infusionsoft_sdk_api_key')));

                $products = array();
                $page = 0;

                do {
                    $products_orig = Infusionsoft_DataService::query(new Infusionsoft_Product(), array('Id' => '%'), 1000, $page, array('Id', 'ProductName'));

                    foreach($products_orig as $product) {
                        $products[$product->Id] = $product;
                    }

                    $page++;
                } while(count($products_orig) == 1000);
            } catch(Infusionsoft_Exception $e) {
                $enough_to_go = false;

                if($e == '[InvalidKey]Invalid Key') {
                    $valid_key = false;
                    add_settings_error("infusionsoft_sdk_api_key", "infusionsoft_sdk_api_key", "The API key you entered is invalid.", "error");
                }
            }
        } else {
            $products = array();

            if(!get_option('infusionsoft_sdk_app_name')){
                add_settings_error("infusionsoft_sdk_app_name", "infusionsoft_sdk_app_name", "Please enter your Infusionsoft app name.", "error");
            }

            if(!get_option('infusionsoft_sdk_api_key')){
                $valid_key = false;
                add_settings_error("infusionsoft_sdk_api_key", "infusionsoft_sdk_api_key", "Please enter your Infusionsoft API key.", "error");
            }
        }

        if($enough_to_go && Infusionsoft_DataService::ping()){
            try{
                Infusionsoft_DataService::findByField(new Infusionsoft_Contact(), 'Id', -1);
            } catch(Exception $e){
                add_settings_error("infusionsoft_sdk_api_key", "infusionsoft_sdk_api_key", "The API key you entered is invalid.", "error");
            }
        } else {
            if($valid_key){
                add_settings_error("infusionsoft_sdk_app_name", "infusionsoft_sdk_app_name", "The app name you entered is invalid.", "error");
            }
        }

        if(!is_numeric(get_option('novaksolutions_upsell_merchantaccount_id'))){
            add_settings_error("novaksolutions_upsell_merchantaccount_id", "novaksolutions_upsell_merchantaccount_id", "The merchant account ID you entered is invalid. Your upsell shortcodes will not work until you enter your merchant account ID.", "error");
        }

        return $products;
    }

    public function getSubscriptions()
    {
        // Make sure the SDK is enabled and configured
        if (!$this->hasSDK()) {
            $subscriptions = array();
            return $subscriptions;
        }

        $enough_to_go = false;
        $valid_key = true;
        if(get_option('infusionsoft_sdk_app_name') != '' && get_option('infusionsoft_sdk_api_key') != ''){
            $enough_to_go = true;

            try{
                Infusionsoft_AppPool::addApp(new Infusionsoft_App(get_option('infusionsoft_sdk_app_name') . '.infusionsoft.com', get_option('infusionsoft_sdk_api_key')));
                $subscriptions = Infusionsoft_DataService::query(new Infusionsoft_SubscriptionPlan(), array('Id' => '%'), 1000, 0);
            } catch(Infusionsoft_Exception $e) {
                $enough_to_go = false;

                if($e == '[InvalidKey]Invalid Key') {
                    $valid_key = false;
                    add_settings_error("infusionsoft_sdk_api_key", "infusionsoft_sdk_api_key", "The API key you entered is invalid.", "error");
                }
            }
        } else {
            $subscriptions = array();

            if(!get_option('infusionsoft_sdk_app_name')){
                add_settings_error("infusionsoft_sdk_app_name", "infusionsoft_sdk_app_name", "Please enter your Infusionsoft app name.", "error");
            }

            if(!get_option('infusionsoft_sdk_api_key')){
                $valid_key = false;
                add_settings_error("infusionsoft_sdk_api_key", "infusionsoft_sdk_api_key", "Please enter your Infusionsoft API key.", "error");
            }
        }

        if($enough_to_go && Infusionsoft_DataService::ping()){
            try{
                Infusionsoft_DataService::findByField(new Infusionsoft_Contact(), 'Id', -1);
            } catch(Exception $e){
                add_settings_error("infusionsoft_sdk_api_key", "infusionsoft_sdk_api_key", "The API key you entered is invalid.", "error");
            }
        } else {
            if($valid_key){
                add_settings_error("infusionsoft_sdk_app_name", "infusionsoft_sdk_app_name", "The app name you entered is invalid.", "error");
            }
        }

        if(!is_numeric(get_option('novaksolutions_upsell_merchantaccount_id'))){
            add_settings_error("novaksolutions_upsell_merchantaccount_id", "novaksolutions_upsell_merchantaccount_id", "The merchant account ID you entered is invalid. Your upsell shortcodes will not work until you enter your merchant account ID.", "error");
        }

        return $subscriptions;
    }

    /**
     * Use the order ID and name to find the contact ID
     *
     * @param int $orderId
     * @param string $firstName
     * @param string $lastName
     * @return int
     */
    public function getContactId($orderId, $firstName, $lastName)
    {
        // Make sure the SDK is configured
        if(!$this->hasSDK()){
            return false;
        }

        Infusionsoft_AppPool::addApp(new Infusionsoft_App(get_option('infusionsoft_sdk_app_name') . '.infusionsoft.com', get_option('infusionsoft_sdk_api_key')));

        try {
            $order = new Infusionsoft_Job($orderId);
        } catch (Exception $e) {
            return false;
        }

        $contactId = (string) $order->ContactId;

        if($contactId) {

            try {
                $contact = new Infusionsoft_Contact($contactId);
            } catch (Exception $e) {
                return false;
            }

            if(strcasecmp((string) $contact->FirstName, $firstName) == 0 && strcasecmp((string) $contact->LastName, $lastName) == 0){
                return $contactId;
            }
        }

        return false;
    }

    /**
     * Correctly add parameters to the end of a URL
     *
     * @param string $url
     * @param array $params
     * @return string
     */
    public function addParamsToUrl($url, $params)
    {
        if(strpos($url, "?") === false){
            $url .= "?";
        } else {
            if(substr($url, -1, 1) != '&'){
                $url .= '&';
            }
        }
        $url .= http_build_query($params);
        return $url;
    }

    /**
     * Handle the actual upsell
     */
    public function processUpsell()
    {
        $merchantaccount_id = 0;

        if($_POST['test'] == 'true'){
            $merchantaccount_id = get_option('novaksolutions_upsell_test_merchantaccount_id');
        } else {
            $merchantaccount_id = get_option('novaksolutions_upsell_merchantaccount_id');
        }

        Infusionsoft_AppPool::addApp(new Infusionsoft_App(get_option('infusionsoft_sdk_app_name') . '.infusionsoft.com', get_option('infusionsoft_sdk_api_key')));

        // Load information from request
        $contact_id = $_POST['contact_id'];
        $order_id = $_POST['order_id'];

        // Determine if doing a product or subscription upsell
        $type = false;
        if(isset($_POST['product_id'])) {
            $product_id = $_POST['product_id'];
            $type = 'product';
        } elseif(isset($_POST['subscription_id'])) {
            $subscription_id = $_POST['subscription_id'];
            $type = 'subscription';
        }

        if(empty($type)) {
            header('Location: ' . $this->addParamsToUrl($failure_url, array('msg' => 'Missing product_id and subscription_id.')));
        }

        $error = false;

        $success_url = $_POST['success_url'];
        $failure_url = $_POST['failure_url'];

        $quantity = 1;

        $pass_along_params = array('orderId' => $order_id, 'contactId' => $contact_id);
        try {
            $contact = new Infusionsoft_Contact($contact_id);

            $authed = false;

            if($order_id != ''){
                $order = new Infusionsoft_Job($order_id);
                if($order->ContactId == $contact_id){
                    $authed = true;
                    $invoices = Infusionsoft_DataService::query(new Infusionsoft_Invoice(), array('JobId' => $order->Id));
                    if(count($invoices) > 0){
                        $invoice = array_shift($invoices);
                        $lead_affiliate_id = $invoice->LeadAffiliateId;
                        $sale_affiliate_id = $invoice->AffiliateId;
                    }
                } else {
                    $authed = false;
                }
            }

            if($authed){
                $creditCards = Infusionsoft_DataService::queryWithOrderBy(new Infusionsoft_CreditCard(), array('ContactId' => $contact_id), 'Id', false, 1, 0, array('Id'));

                if (count($creditCards) > 0) {
                    $creditCard = array_shift($creditCards);
                    $creditCardId = $creditCard->Id;

                    $original_time_zone = date_default_timezone_get();
                    date_default_timezone_set('America/New_York');

                    if($type == 'product') {
                        // Products

                        $product = new Infusionsoft_Product($product_id);

                        $invoiceId = Infusionsoft_InvoiceService::createBlankOrder($contact_id, 'Upsell - ' . $product->ProductName, date('Ymd') . 'T00:00:00', $lead_affiliate_id, $sale_affiliate_id);

                        Infusionsoft_InvoiceService::addOrderItem($invoiceId, $product_id, 4, $product->ProductPrice, $quantity, $product->ProductName, $product->ProductName);

                        $amountOwed = Infusionsoft_InvoiceService::calculateAmountOwed($invoiceId);
                        Infusionsoft_InvoiceService::addPaymentPlan($invoiceId, true, $creditCardId, $merchantaccount_id, 3, 3, $amountOwed, date('Ymd') . 'T00:00:00', date('Ymd') . 'T00:00:00', 0, 0);
                        date_default_timezone_set($original_time_zone);
                        $orderInfo = Infusionsoft_InvoiceService::getOrderId($invoiceId);
                        $orderId = $orderInfo['orderId'];
                        $pass_along_params['addnOrderId'] = $orderId;

                        $order = new Infusionsoft_Job($orderId);
                        $order->save();
                    } else {
                        // Subscriptions

                        $subscriptionPlan = new Infusionsoft_SubscriptionPlan($subscription_id);
                        $subscriptionId = Infusionsoft_InvoiceService::addRecurringOrder($contact_id, false, $subscriptionPlan->Id, 1, $subscriptionPlan->PlanPrice, false, $merchantaccount_id, $creditCardId, 0, 0);
                        $invoiceId = Infusionsoft_InvoiceService::createInvoiceForRecurring($subscriptionId);

                        $orderInfo = Infusionsoft_InvoiceService::getOrderId($invoiceId);
                        $orderId = $orderInfo['orderId'];
                        $pass_along_params['addnOrderId'] = $orderId;
                    }

                    $result = Infusionsoft_InvoiceService::chargeInvoice($invoiceId, "Upsell Payment", $creditCardId, $merchantaccount_id, false);

                    if($result['Successful'] == true){
                        if(!empty($_POST['action_set_id'])){
                            Infusionsoft_ContactService::runActionSequence($contact->Id, $_POST['action_set_id']);
                        }
                    } else {
                        $error = true;
                        $message = 'Payment failed';
                    }
                } else {
                    $error = true;
                    $message = 'No credit cards on file for contact: ' . $contact_id;
                }

                if (!$error) {
                    header('Location: ' . $this->addParamsToUrl($success_url, $pass_along_params));
                } else{
                    header('Location: ' . $this->addParamsToUrl($failure_url, array('msg' => $message)));
                }
            } else {
                header('Location: ' . $this->addParamsToUrl($failure_url, array('msg' => 'Order or Subscription do not belong to specified contact.')));
            }
        } catch (Exception $e) {
            $msg = 'Exception Caught';

            if(stripos($e->getMessage, 'duplicate order') !== false){
                $msg = 'Duplicate order';
            }

            header('Location: ' . $this->addParamsToUrl($failure_url, array('msg' => $msg)));
        }
    }

    /**
     * Output the required Javascript to handle the MCE shortcode button
     */
    public function shortcodeMceHandler(){
        ?>
        <script type="text/javascript">
            var defaultSettings = {},
                outputOptions = '',
                selected ='',
                content = '';

            defaultSettings['upsell'] = {
                test: {
                    name: 'Test Mode',
                    defaultvalue: 'No',
                    description: 'Enable test mode to display troubleshooting information.',
                    type: 'select',
                    options: 'Yes|No'
                },
                product_id: {
                    name: 'Product ID',
                    defaultvalue: '',
                    description: 'The ID for the product you are offering with your upsell.',
                    type: 'text'
                },
                subscription_id: {
                    name: 'Subscription ID',
                    defaultvalue: '',
                    description: 'The ID for the subscription you are offering with your upsell.',
                    type: 'text'
                },
                success_url: {
                    name: 'Success URL',
                    defaultvalue: '',
                    description: 'Your customer will be directed to this URL after a successful upsell.',
                    type: 'text'
                },
                failure_url: {
                    name: 'Failure URL',
                    defaultvalue: '',
                    description: 'Your customer will be directed to this URL after an unsuccessful upsell.',
                    type: 'text'
                },
                action_set_id: {
                    name: 'Action Set ID',
                    defaultvalue: '',
                    description: 'This action set will be run after a successful upsell.',
                    type: 'text'
                },
                id: {
                    name: 'Button CSS ID',
                    defaultvalue: '',
                    description: 'You can style your upsell button by adding a CSS ID to it.',
                    type: 'text'
                },
                class: {
                    name: 'Button CSS Class',
                    defaultvalue: '',
                    description: 'You can style your upsell button by adding a CSS class to it.',
                    type: 'text'
                },
                button_text: {
                    name: 'Button Text',
                    defaultvalue: '',
                    description: 'The text that will be shown for the upsell button.',
                    type: 'text'
                },
                image: {
                    name: 'Button Image',
                    defaultvalue: '',
                    description: 'You can optionally use an image for your upsell button. Please specify the full URL to the image.',
                    type: 'text'
                },
                image_width: {
                    name: 'Button Image Width',
                    defaultvalue: '',
                    description: 'If you use an image for your upsell button, you must specify the width of the image in pixels.',
                    type: 'text'
                },
                image_height: {
                    name: 'Button Image Height',
                    defaultvalue: '',
                    description: 'If you use an image for your upsell button, you must specify the height of the image in pixels.',
                    type: 'text'
                }
            };

            function CustomButtonClick(tag){

                var index = tag;

                for (var index2 in defaultSettings[index]) {
                    outputOptions += '<tr>\n';
                    outputOptions += '<th><label for="oneclick-' + index2 + '">'+ defaultSettings[index][index2]['name'] +'</label></th>\n';
                    outputOptions += '<td>';

                    if (defaultSettings[index][index2]['type'] === 'select') {
                        var optionsArray = defaultSettings[index][index2]['options'].split('|');

                        outputOptions += '\n<select name="oneclick-'+index2+'" id="oneclick-'+index2+'">\n';

                        for (var index3 in optionsArray) {
                            selected = (optionsArray[index3] === defaultSettings[index][index2]['defaultvalue']) ? ' selected="selected"' : '';
                            outputOptions += '<option value="'+optionsArray[index3]+'"'+ selected +'>'+optionsArray[index3]+'</option>\n';
                        }

                        outputOptions += '</select>\n';
                    }

                    if (defaultSettings[index][index2]['type'] === 'text') {
                        outputOptions += '\n<input type="text" name="oneclick-'+index2+'" id="oneclick-'+index2+'" value="'+defaultSettings[index][index2]['defaultvalue']+'" />\n';
                    }

                    outputOptions += '\n<br/><small>'+ defaultSettings[index][index2]['description'] +'</small>';
                    outputOptions += '\n</td>';

                }


                var width = jQuery(window).width(),
                    tbHeight = jQuery(window).height(),
                    tbWidth = ( 720 < width ) ? 720 : width;

                tbWidth = tbWidth - 80;
                tbHeight = tbHeight - (84 + 35);

                var tbOptions = "<div id='oneclick_shortcodes_div'><form id='oneclick_shortcodes'><table id='shortcodes_table' class='form-table oneclick-"+ tag +"'>";
                tbOptions += outputOptions;
                tbOptions += '</table>\n<p class="submit">\n<input type="button" id="shortcodes-submit" class="button-primary" value="Add shortcode" name="submit" /></p>\n</form></div>';

                var form = jQuery(tbOptions);

                var table = form.find('table');
                form.appendTo('body').hide();

                form.find('#shortcodes-submit').click(function(){

                    var shortcode = '['+tag;

                    for( var index in defaultSettings[tag]) {
                        var value = table.find('#oneclick-' + index).val();
                        if (index === 'content') {
                            content = value;
                            continue;
                        }

                        if ( value !== defaultSettings[tag][index]['defaultvalue'] )
                            shortcode += ' ' + index + '="' + value + '"';

                    }

                    shortcode += '] ' + "\n";

                    if (content != '') {
                        shortcode += content;
                        shortcode += '[/'+tag+'] ' + "\n";
                    }

                    tinyMCE.activeEditor.execCommand('mceInsertContent', 0, shortcode + ' ');

                    tb_remove();
                });

                tb_show( 'Infusionsoft One-click ' + tag.charAt(0).toUpperCase() + tag.slice(1) + ' Shortcode', '#TB_inline?width=' + tbWidth + '&height=' + tbHeight + '&inlineId=oneclick_shortcodes_div' );
                jQuery('#oneclick_shortcodes_div').remove();
                outputOptions = '';
            }
        </script>
    <?php }

    /**
     * Enqueue scripts
     */
    public function adminEnqueueScripts(){
        wp_enqueue_script('jquery');
    }

}

