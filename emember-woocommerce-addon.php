<?php
/**
* Plugin Name: eMember WooCommerce Addon
* Plugin URI: https://www.tipsandtricks-hq.com/wordpress-emember-easy-to-use-wordpress-membership-plugin-1706
* Description: eMember Addon that allows you to accept membership payment via WooCommerce
* Version: 2.3
* Author: Tips and Tricks HQ
* Author URI: https://www.tipsandtricks-hq.com/
* Requires at least: 3.0
*/

if (!defined('ABSPATH')){
    exit;
}

/*
 * This action hook is triggered from the wp_set_auth_cookie() function (woocommerce calls it for auto-login after registration).
 * We will use it to login the user to emember platform.
 */
add_action('set_logged_in_cookie', 'emember_woo_handle_set_logged_in_cookie', 10, 5);
function emember_woo_handle_set_logged_in_cookie($logged_in_cookie, $expire, $expiration, $user_id, $scheme){
    eMember_log_debug('Woocommerce integration - set_logged_in_cookie action triggered. User ID: ' . $user_id, true);
    $user = get_user_by( 'id', $user_id );

    $userlogin = $user->user_login;

    $emember_config = Emember_Config::getInstance();
    $sign_in_with_wp = $emember_config->getValue('eMember_signin_emem_user');
    //Automatically login to emember system
    if ($sign_in_with_wp) {
        $auth = Emember_Auth::getInstance();
        if (!$auth->isLoggedIn()){
            eMember_log_debug('Woocommerce integration - calling login_through_wp()', true);
            $auth->login_through_wp($userlogin, $user);
        }
    }
}

//Add the meta box in the woocommerce product add/edit interface
add_action('add_meta_boxes', 'emember_woo_meta_boxes');

function emember_woo_meta_boxes() {
    add_meta_box('emember-woo-product-data', 'WP eMember Membership', 'emember_woo_membership_level_data_box', 'product', 'normal', 'high');
}

function emember_woo_membership_level_data_box($wp_post_obj) {
    $level_id = get_post_meta($wp_post_obj->ID, 'emember_woo_product_level_id', true);
    echo "Membership Level ID: ";
    echo '<input type="text" size="10" name="emember_woo_product_level_id" value="' . $level_id . '" />';
    echo '<p>A membership account with the specified level ID will be created for the user who purchase this product.</p>';
}

//Save the membership level data to the post meta with the product when it is saved
add_action('save_post', 'emember_woo_save_product_data', 10, 2);

function emember_woo_save_product_data($post_id, $post_obj) {
    // Check post type for woocommerce product
    if ($post_obj->post_type == 'product') {
        // Store data in post meta table if present in post data
        if (isset($_POST['emember_woo_product_level_id'])) {
            update_post_meta($post_id, 'emember_woo_product_level_id', $_POST['emember_woo_product_level_id']);
        }
    }
}

//Handle membership creation after the transaction (if needed)
add_action('woocommerce_order_status_processing', 'emember_woo_handle_woocommerce_payment'); //Executes when a status changes to processing
add_action('woocommerce_order_status_completed', 'emember_woo_handle_woocommerce_payment'); //Executes when a status changes to completed
add_action('woocommerce_checkout_order_processed', 'emember_woo_handle_woocommerce_payment');

function emember_woo_handle_woocommerce_payment($order_id) {
    eMember_log_debug("WooCommerce emember integration - Order processed... checking if member account needs to be created or updated.", true);
    $order = new WC_Order($order_id);

    $order_status = $order->get_status();
    eMember_log_debug("WooCommerce emember integration - Order status: " . $order_status, true);
    if (strtolower($order_status) != "completed" && strtolower($order_status) != "processing") {
        eMember_log_debug("WooCommerce emember integration - Order status for this transaction is not in a 'completed' or 'processing' state. Membership update won't be handled at this stage.", true);
        eMember_log_debug("The membership account creation or update for this transaction will be handled when you set the order status to completed.", true);
        return;
    }

    //Mechanism to lock the transaction notification that is being processed.
    $txn_being_processed = get_option('emember_woo_txn_being_processed');
    $notification_txn_id = $order_id;
    if (!empty($txn_being_processed) && $txn_being_processed == $notification_txn_id){
        //This is a duplicate notification. No need to process this notification as it is already being processed.
        eMember_log_debug('This WooCommerce order ('.$notification_txn_id.') is already being procesed. This is likely a duplicate notification. Nothing to do.', true);
        return true;
    }
    update_option('emember_woo_txn_being_processed', $notification_txn_id);

    //Check if the multiple level feature is enabled
    $emember_config = Emember_Config::getInstance();
    $multiple_level_enabled = false;
    if ($emember_config->getValue('eMember_enable_secondary_membership')) {
        $multiple_level_enabled = true;
    }

    //Create the ipn_data structure
    $ipn_data = array();
    $ipn_data['first_name'] = $order->get_billing_first_name();
    $ipn_data['last_name'] = $order->get_billing_last_name();
    $ipn_data['payer_email'] = $order->get_billing_email();
    $ipn_data['address_street'] = $order->get_billing_address_1();
    $ipn_data['address_city'] = $order->get_billing_city();
    $ipn_data['address_state'] = $order->get_billing_state();
    $ipn_data['address_zip'] = $order->get_billing_postcode();
    $ipn_data['address_country'] = $order->get_billing_country();
    $subscr_id = $order_id; //The txn_id

    $order_items = $order->get_items();
    foreach ($order_items as $item_id => $item) {
        if ($item['type'] == 'line_item') {
            $_product = $item->get_product();
            $post_id = $_product->get_id();
            $level_id = get_post_meta($post_id, 'emember_woo_product_level_id', true);
            if (!empty($level_id)) {
                eMember_log_debug("Membership Level ID (" . $level_id . ") is present in this product (Product ID: ".$post_id."). Processing membership account related tasks...", true);

                //Check if there is an existing WP user account for this customer.
                $email = $ipn_data['payer_email'];
                $user = get_user_by( 'email', $email );
                if(is_object($user) && !empty($user->ID)){
                    //There is a WP user record for this user. If the user doesn't have an eMember record, then we will import the WP user into eMember.
                    eMember_log_debug("There is an existing WP User entry for email address (" . $email . "). WP User ID (".$user->ID."). Importing this existing WP user record into eMember.", true);
                    $wp_user_data = array();
                    $wp_user_data['ID'] = $user->ID;
                    $wp_user_data['membership_level'] = $level_id;
                    $wp_user_data['account_state'] = 'active';
                    $wp_user_data['subscription_starts'] = (date("Y-m-d"));
                    $wp_user_data['preserve_wp_role'] = 1;

                    $user_exists = emember_username_exists($user->user_login);
                    if (!$user_exists) {
                        //No emember record exist for this WP user entry. Lets create a new one.
                        eMember_log_debug("There is no eMember entry for this user. So lets import it into eMember.", true);

                        if(function_exists('wp_eMember_add_user_record')){
                            $added = wp_eMember_add_user_record($wp_user_data);
                        } else {
                            $added = __wp_eMember_add($wp_user_data);
                        }

                        if ($added === false) {
                            eMember_log_debug("Error! Failed to import the WP user record into eMember.", false);
                        }else{
                            eMember_log_debug("WP user record with email address (".$email.") imported into eMember system.", true);
                            //Send account upgrade/import email notification.
                            emember_woo_send_account_upgrade_email($email);
                        }

                        if(!$multiple_level_enabled){
                            //No need to continue the loop since multiple level feature is not enabled. User can purchase one level at a time only.
                            return;
                        }

                    }
                    else{
                        //There is an emember record for this user. The standard signup handling code will apply the new level.
                        $eMember_id = $user_exists;
                        eMember_log_debug("There is an eMember entry for this user. Applying new level to eMember ID: " . $eMember_id, true);
                        include_once(WP_EMEMBER_PATH . 'ipn/eMember_handle_subsc_ipn_stand_alone.php');
                        eMember_handle_subsc_signup_stand_alone($ipn_data, $level_id, $subscr_id, $eMember_id);

                        if(!$multiple_level_enabled){
                            //No need to continue the loop since multiple level feature is not enabled. User can purchase one level at a time only.
                            return;
                        }
                    }
                } else {
                    //Brand new user with NO account whatsoever. Handle the signup using standard process.
                    eMember_log_debug("Invoking eMember user signup handling function.", true);
                    include_once(WP_EMEMBER_PATH . 'ipn/eMember_handle_subsc_ipn_stand_alone.php');
                    eMember_handle_subsc_signup_stand_alone($ipn_data, $level_id, $subscr_id);
                    //For a brand new user we can't handle more than ONE level to start with. So we are ending the loop here.
                    return;
                }
            }
        }//End of if ($item['type'] == 'line_item')
    }//End of foreach ($order_items)

    //Clear the transaction being processed flag
    update_option('emember_woo_txn_being_processed', '');
}

function emember_woo_send_account_upgrade_email($member_email){
    //Retrieve the member record for the given email address and then send an account upgraded email to him.

    global $wpdb;
    $emember_config = Emember_Config::getInstance();
    $members_table_name = $wpdb->prefix . "wp_eMember_members_tbl";

    $query_db = $wpdb->get_row("SELECT * FROM $members_table_name WHERE email = '$member_email'", OBJECT);
    if (!$query_db) {
        eMember_log_debug("Error! No eMember entry found for email address: ". $member_email, false);
        return;
    }

    $eMember_id = $query_db->member_id;
    eMember_log_debug("Found a match in the eMember database. Member ID: " . $eMember_id, true);

    $subject = $emember_config->getValue('eMember_account_upgrade_email_subject');
    if (empty($subject)) {
        $subject = "Member Account Upgraded";
    }
    $body = $emember_config->getValue('eMember_account_upgrade_email_body');
    if (empty($body)) {
        $body = "Your account has been upgraded successfully";
    }

    $login_link = $emember_config->getValue('login_page_url');
    $additional_params = array('login_link' => $login_link);
    $email_body = emember_dynamically_replace_member_details_in_message($eMember_id, $body, $additional_params);

    $from_address = get_option('senders_email_address');
    $headers = 'From: ' . $from_address . "\r\n";

    if(strtolower($email_body) == "disabled"){
        eMember_debug_log_subsc("Attention!!! Email body is set to disabled status. No email will be sent for this.", true);
    } else {
        wp_mail($member_email, $subject, $email_body, $headers);
        eMember_log_debug("Member upgrade email successfully sent to: " . $member_email, true);
    }

}

/**************************************************/
/*** WooCommerce Subscription Addon Integration ***/
//Reference - http://docs.woothemes.com/document/subscriptions/develop/action-reference/
add_action('subscriptions_cancelled_for_order', 'emember_woo_deactivate_account');
add_action('subscriptions_expired_for_order', 'emember_woo_deactivate_account');

function emember_woo_deactivate_account($order)
{
    $order_id = $order->id;
    eMember_log_debug("Woo Subscription cancelled/expired. Order ID: ".$order_id, true);
    $ipn_data = array();
    $ipn_data['parent_txn_id'] = $order_id;
    include_once(WP_EMEMBER_PATH . 'ipn/eMember_handle_subsc_ipn_stand_alone.php');
    eMember_handle_subsc_cancel_stand_alone($ipn_data);
}