<?php

/**
 * Plugin Name: API For Smart-Phones.
 * Plugin Uri: http://localhost/gmmco
 * Description: Custome API for Smart Phones.
 * Version: 1.0.0
 * Author: Saravana Kumar
 * Author URL: apexwebbuilders.in
 * 
 */

//  define('ARRAY_A', 'ARRAY_A');
add_action("rest_api_init", "api_smart_phone");


function api_smart_phone()
{
    $p_commonPath = "api/user";
    $p_user = "user";
    $p_product = "product";
    $p_api = "api";
    /** Get OTP API */
    register_rest_route($p_commonPath, "getotp", array(
        'methods' => 'POST',
        'callback' => 'getotpf',
        'permission_callback' => function () {
            return true;
        }
    ));
    /** OTP verification API */
    register_rest_route(
        $p_commonPath,
        "verifyotp",
        array(
            'methods' => 'POST',
            'callback' => 'verifyotpf',
            'permission_callback' => function () {
                return true;
            }
        )
    );

    /**Login API */
    register_rest_route($p_commonPath, "login", array('methods' => "POST", "callback" => "wc_userlogin"));

    /**New password create API */
    register_rest_route($p_commonPath, "updatePassword", array('methods' => 'POST', 'callback' => 'updatePasswordf'));

    /**Registration API */
    register_rest_route($p_commonPath, "register", array('methods' => 'POST', 'callback' => "wc_adduser"));

    /**Notification token update API */
    register_rest_route($p_commonPath, "notificationToken", array('methods' => 'PUT', 'callback' => 'updateNotificationToken'));

    /**Product dropdown API */
    register_rest_route($p_api . "/" . $p_product, "productsDropdown", array('methods' => 'POST', 'callback' => 'getproductsDropdown'));
}


/**
 * Get OTP
 * 
 *  WP_REST_Request $name Send mobile number.
 * return array $args.
 */
function getotpf($request)
{
    // return "test resutl slfjds lds fsf";


    global $wpdb;
    $mobileNumber = $request['mobileNumber'];
    $type = $request['type'] ?? '';
    $otp = rand(1111, 9999);
    // return new WP_REST_Response("test resulty slfj lsfldlf jd $mobileNumber , $otp", 200);
    // exit(0);
    $table_name = 'wp_otp_verification';
    $date = date('Y-m-d H:i:s');
    $data = array(
        'mobile_number' => $mobileNumber,
        'otp' => $otp,
        'otp_createdon' => $date, 'otp_status' => 0
    );
    $resp = array();
    if ($type == 'forgotpassword' || $type == "mobilelogin") {
        $table_namemeta = $wpdb->prefix . "usermeta";
        $sql = "SELECT user_id from $table_namemeta where meta_key=%s and meta_value=%s";

        $user_id = $wpdb->get_var($wpdb->prepare($sql, 'user_phone', $mobileNumber));
        if ($user_id) {
            $resp['user_id'] = $user_id;
        } else {
            return new WP_REST_Response(array('message' => "Mobile number not registered."), 400);
        }
    } else if ($type == "register") {
    } else if ($type == "resendOtp") {
    } else {
        return new WP_REST_Response(array('message' => 'Request type is required.', 'reason' => $wpdb->last_error), 409);
    }
    $sql = "SELECT mobile_number,otp FROM " . $table_name . " WHERE mobile_number='$mobileNumber'";
    $countPhone = $wpdb->get_results($sql, ARRAY_A);

    if ($wpdb->last_error) {
        return new WP_REST_Response(array('message' => 'Unable to preceed check otp. Please try later.', 'reason' => $wpdb->last_error), 409);
    }
    if (count($countPhone) > 0) {
        if ($type == "resendOtp") {
            $otp = $countPhone[0]["otp"];
        }

        // return new WP_REST_Response(array("message"=> "$otp"),400);
        $data_update = array('otp' => $otp, 'otp_createdon' => $date, 'otp_status' => 0);
        $data_where = array('mobile_number' => $mobileNumber);
        $res = $wpdb->update($table_name, $data_update, $data_where);
        if ($wpdb->last_error) {
            return new WP_REST_Response(array('message' => 'Unable to update. Please try later.', 'reason' => $wpdb->last_error), 409);
        }
    } else {
        $res = $wpdb->insert($table_name, $data);
        if ($wpdb->last_error) {
            return new WP_REST_Response(array('message' => 'Unable to save and proceed OTP. Please try later.', 'reason' => $wpdb->last_error), 409);
        }
    }
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'http://boancomm.net/boansms/boansmsinterface.aspx',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => 'mobileno=' . $mobileNumber . '&smsmsg=Dear%20Customer%20' . $otp . '%20is%20Gmmco%20Ltd%20OTP%20to%20verify%20your%20mobile%20number%20for%20Website%20related%20services%20to%20reach%20you&uname=gmmco&pwd=gmmco11&pid=1194',
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded'
        ),
    ));

    $response = curl_exec($curl);
    if (curl_errno($curl)) {
        $err = curl_error($curl);
    }
    curl_close($curl);
    if ($response) {
        $resp1  = $resp + array('message' => 'OTP sent successfully.', 'success' => 1);

        return new WP_REST_Response($resp1, 200);
    } else {
        return new WP_REST_Response(array('message' => 'Error Occurs!!!', 'reason' => $err), 400);
    }
}


function verifyotpf($request)
{
    global $wpdb;
    $mobileNumber = $request['mobileNumber'];
    $otp = $request['otp'];

    $tablename = 'wp_otp_verification';
    if (strlen($mobileNumber) != 10) {
        return new WP_REST_Response(array('message' => 'Please enter valid mobile number',), 400);
    }
    if (strlen($otp) != 4) {
        return new WP_REST_Response(array('message' => 'OTP required',), 400);
    }
    $sql = "SELECT mobile_number FROM $tablename WHERE mobile_number = '" . $mobileNumber . "' and otp ='" . $otp . "' and otp_status = 0";

    $result = $wpdb->get_results($sql);

    if ($wpdb->last_error) {
        return new WP_REST_Response(array('message' => 'Error in getting data from db', 'reason' => $wpdb->last_error), 400);
    }
    if (count($result) == 1) {
        // $table_name = 'wp_otp_verification';
        $data_update = array('otp_status' => 2);
        $data_where = array('mobile_number' => $mobileNumber);
        $res = $wpdb->update($tablename, $data_update, $data_where);
        if ($wpdb->last_error) {
            return new WP_REST_Response(array('message' => 'Error in getting data from db', 'reason' => $wpdb->last_error), 400);
        }
        if ($res) {
            $resp = array();

            $resp['message'] = 'OTP Verified successfully.';
            $resp['success'] = 1;

            return new WP_REST_Response($resp, 200);
        } else {
            return new WP_REST_Response(array('message' => 'Error Occurs!!!'), 400);
        }
    } else if (count($result) == 0) {
        $res = $wpdb->get_results("SELECT mobile_number FROM $tablename WHERE mobile_number = '" . $mobileNumber . "' and otp ='" . $otp . "' and otp_status = 1");
        if (count($res) == 1) {
            return new WP_REST_Response(array('message' => 'Otp Expired. Try again'), 400);
        } else {
            return new WP_REST_Response(array('message' => 'Invalid OTP'), 400);
        }
    }
}
function wc_userlogin($request = null)
{
    $response = array();
    $parametres =  $request->get_body_params() ?? array();
    // return $parametres;
    $username = sanitize_user(trim($parametres['username']));
    $password = sanitize_user(trim($parametres['password']));
    $login_type = sanitize_user(trim($parametres['login_type']));



    if (empty($login_type) || empty($login_type)) {
        return new WP_REST_Response(array('message' => 'Login type required'), 400);
    }
    if ($login_type == "email") {
        if (empty($username) || empty($password)) {
            return new WP_REST_Response(array('message' => 'Username and password required'),   400);
        }
        $logindata = array('user_login' => $username, 'user_password' => $password, 'remember' => true);
        $user = wp_signon($logindata, false);
        if (is_wp_error($user)) {
            return new WP_REST_Response(array('message' => 'Invalid credentials. Try again with valid login credentials.'), 400);
        } else {
            $response['userDetails'] = $user;
            $response['message'] = "User $username login successful";
            return new WP_REST_Response($response);
        }
    } else if ($login_type == "mobile") {
        if (empty($username) || empty($password)) {
            return new WP_REST_Response(array("message" => "Username and OTP required."), 400);
        }
        global $wpdb;
        $tablename = 'wp_otp_verification';
        // $sql = "sljfldsfkldsjflksd lfds jklfjds";
        $sql = "SELECT mobile_number FROM $tablename WHERE mobile_number = '" . $username . "' and otp ='" . $password . "' and otp_status = 0"; //
        // return new WP_REST_Response($sql,200);

        $result = $wpdb->get_results($sql);

        if ($wpdb->last_error) {
            return new WP_REST_Response(array('message' => 'Error in getting data from db', 'reason' => $wpdb->last_error), 400);
        }
        if (count($result) == 1) {
            // $table_name = 'wp_otp_verification';
            $data_update = array('otp_status' => 2);
            $data_where = array('mobile_number' => $username);
            $res = $wpdb->update($tablename, $data_update, $data_where);
            if ($wpdb->last_error) {
                return new WP_REST_Response(array('message' => 'Error in getting data from db', 'reason' => $wpdb->last_error), 400);
            }
            if ($res) {

                $table_namemeta = $wpdb->prefix . "usermeta";
                $sql = "SELECT user_id from $table_namemeta where meta_key=%s and meta_value=%s";

                $user_id = $wpdb->get_var($wpdb->prepare($sql, 'user_phone', $username));
                if ($wpdb->last_error) {
                    return new WP_REST_Response(array('message' => 'Unable to fetch user details.', 'reason' => $wpdb->last_error), 409);
                }
                $user = get_user_by('ID', $user_id);
                if ($user) {
                    return new WP_REST_Response(array('message' => 'Login successful', 'userDetails' => $user), 200);
                } else {
                    return new WP_REST_Response(array('message' => 'User not found', 'todo' => 'register', 'reason' => $wpdb->last_error), 400);
                }
            } else {
                return new WP_REST_Response(array('message' => 'Verified OTP. Please try to login again'), 400);
            }
        } else {
            return new WP_REST_Response(array('message' => 'Invalid OTP. Please try again.'), 400);
        }
        // return new WP_REST_Response(array('message' => 'wait'), 400);
    } else {
        return new WP_REST_Response(array('message' => 'Login type not matched'), 400);
    }
}

/**update new password function updatePassword */
function updatePasswordf(WP_REST_Request $request)
{
    $parametres = $request->get_body_params();
    $otp = $parametres['otp'];
    $mobileNumber = $parametres['mobileNumber'];
    $upwd = $parametres['newpwd'];
    $user_id = $parametres['user_id'];
    global $wpdb;
    $tablename = 'wp_otp_verification';
    $sql = "SELECT mobile_number FROM $tablename WHERE mobile_number = '" . $mobileNumber . "' and otp ='" . $otp . "' and otp_status = 2";

    $result = $wpdb->get_results($sql);

    if ($wpdb->last_error) {
        return new WP_REST_Response(array('message' => 'Error in getting data from db', 'reason' => $wpdb->last_error), 400);
    }
    if (count($result) == 1) {
        $user = get_user_by('ID', $user_id);
        if ($user) {
            wp_set_password($upwd, $user->ID);
            return new WP_REST_Response(array('message' => 'Password updated. Please login again using new password.', 'success' => 1, 'user_id' => $user->ID), 200);
        } else {
            return new WP_REST_Response(array('message' => 'User not found!!!'), 409);
        }
    } else if (count($result) == 0) {
        $res = $wpdb->get_results("SELECT mobile_number,otp FROM $tablename WHERE mobile_number = '" . $mobileNumber . "' and otp ='" . $otp . "' and otp_status <> 2");
        if (count($res) == 1) {
            $dbotp = $res[0]['otp'];
            // if($dbotp != $otp){
            //     return new WP_REST_Response(array('message' => 'Otp Expired. Try again'), 400);
            // }
            return new WP_REST_Response(array('message' => 'Otp not verified. Verify first then update password.'), 400);
        } else {
            return new WP_REST_Response(array('message' => 'Invalid OTP'), 400);
        }
    }
}

/** User register function. addUser */
function wc_adduser($request = null)
{


    $response = array();
    //print_r($request);
    $parameters = $request->get_body_params();
    // print_r( $parameters );

    $first_name = sanitize_user($parameters['first_name']);
    $last_name = sanitize_user($parameters['last_name']);
    $email = sanitize_email($parameters['email']);
    $password = sanitize_text_field($parameters['password']);
    $user_phone = wc_sanitize_phone_number($parameters['mobileNumber']);
    $location = sanitize_user($parameters['location']);
    // $product_name = sanitize_user($parameters['product_name']);
    // $remarks = sanitize_user($parameters['remarks']);
    $nToken = sanitize_user($parameters['nToken']);

    if (empty($first_name)) {
        return new WP_Error(400, 'First Name is required');
    }
    if (empty($last_name)) {
        return new WP_Error(400, 'Last Name is required');
    }
    if (empty($email)) {
        return new WP_Error(400, 'Email is required');
    }
    if (strlen($password) < 8) {
        return new WP_Error(400, 'Password minimum 8 characters required.' . $password);
    }
    if (empty($user_phone)) {
        return new WP_Error(400, 'Mobile number is required');
    }
    if (empty($location)) {
        return new WP_Error(400, 'Location is required');
    }
    if (wp_verify_nonce($_POST['nonce'], 'woocommerce-register')) {
        return new WP_Error(409, 'Invalid Data');
    }

    global $wpdb;

    $table_namemeta = $wpdb->prefix . "usermeta";
    $sql = "SELECT user_id from $table_namemeta where meta_key=%s and meta_value=%s";

    $user_idmob = $wpdb->get_var($wpdb->prepare($sql, 'user_phone', $user_phone));
    if ($wpdb->last_error) {
        return new WP_REST_Response(array('message' => 'Unable to check user details.', 'reason' => $wpdb->last_error), 409);
    }
    // if ($user_idmob) {
    //     return new WP_REST_Response(array('message' => 'Mobile number already used by other user.',), 400);
    // }

    $username = strtolower($first_name);
    $user_id = username_exists($username);
    if ($user_id) {
        $i = 0;
        do {
            $username .= rand(10, 99);
            $user_id = username_exists($username);
            $i++;
        } while ($user_id && $i < 2);
    }
    if (!$user_id && email_exists($email) == false) {
        // echo "$username , $password, $email";
        $user_id = wp_create_user($username, $password, $email);
        wp_update_user(array('ID' => $user_id, 'display_name' => "$first_name $last_name"));
        if (!is_wp_error($user_id)) {

            $user = get_user_by('id', $user_id);
            $user->set_role('customer');
            $user->display_name = $first_name . " " . $last_name;

            update_user_meta($user_id, 'first_name', $first_name);
            update_user_meta($user_id, 'last_name', $last_name);
            update_user_meta($user_id, 'email', $email);
            update_user_meta($user_id, 'location', $location);
            // update_user_meta($user_id, 'product_name', $product_name);
            // update_user_meta($user_id, 'remark', $remarks);
            update_user_meta($user_id, 'user_phone', $user_phone);
            update_user_meta($user_id, 'user_otp', '');
            update_user_meta($user_id, 'nToken', $nToken);
            update_user_meta($user_id, 'user_otp_verified', 0);
            wp_new_user_notification($user_id, '', 'yes');
            $user_info = get_userdata($user_id);
            if ($user_info) {
                $response = array();
                $response['message'] = sprintf(__("User '%s' registration is successful", 'wp-rest-user'), $user_info->user_login);
                $response['id'] = $user_info->ID;
                $response['success'] = 1;
            }
        } else {
            return new WP_Error(409, "Email already exists, please try login1");
        }
        return new WP_REST_Response($response, 200);
    } else {
        return new WP_REST_Response(array("message" => "Email already exists, please try login2"), 409);
    }
}

function updateNotificationToken($parameters)
{

    $user_id = $parameters["user_id"];
    $token = $parameters["token"];
    if (empty($user_id) || empty($token)) {
        return new WP_Error(400, "User Id and token are required.");
    }
    global $wpdb;
    $table_name = 'wp_usermeta';
    $result = $wpdb->get_results("SELECT * from $table_name where meta_key='nToken' and user_id='$user_id'", ARRAY_A);
    if (count($result) == 1) {
        $up_data = array('meta_value' => $token);
        $up_where = array('user_id' => $user_id, 'meta_key' => 'nToken');
        $result = $wpdb->update($table_name, $up_data, $up_where);
    } else if (count($result) == 0) {
        $result = update_user_meta($user_id, 'nToken', $token);
    } else {
        return new WP_REST_Response(array('message' => 'Duplicate account found'), 409);
    }
    if ($result) {
        $response = array('message' => 'Notification updated.', 'success' => 1);
        return new WP_REST_Response($response, 200);
    } else {
        $response = array('message' => 'Error occured.', 'success' => 0);
        return new WP_REST_Response($response, 123);
    }
}
function getproductsDropdown($request)
{
    $parametres = $request->get_body_params();
    $searchName = $parametres['searchName'];
    $tablename = "wp_posts";
    global $wpdb;
    $sql = "SELECT ID,post_title FROM $tablename WHERE post_type='product' and post_title like '%$searchName%' ";

    $result = $wpdb->get_results($sql, ARRAY_A);
    if ($wpdb->last_error) {
        $resulte =  array('message' => 'Unable to get products list');
        return new WP_REST_Response($resulte, 400);
    }
    try {
        foreach ($result as $key => $value) {
            $product = wc_get_product($value["ID"]);
            $result[$key]["ID"] = (int) $value['ID'];
            //    $image = $product->get_image();
            //    $image->wc_get_attachment_image_attributes( attr )

            $imagepath =  wp_get_attachment_image_url($product->get_image_id());
            if ($imagepath) {
                $result[$key]["image"] = $imagepath;
            }
            $result[$key]["rentprice"] = $product->get_price_suffix() . "" . $product->get_price();
            $result[$key]["slug"] = $product->get_slug();
            $result[$key]["stockStatus"] = $product->get_stock_status();
            $result[$key]["stockCount"] = $product->get_stock_quantity();
        }
    } catch (Exception $e) {

        $resulte = array("message" => $e->getMessage(), "" => $e->getCode());
        return new WP_REST_Response($resulte, 400);
    }
    return new WP_REST_Response(["data" => $result], 123);
}
