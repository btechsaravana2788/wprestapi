# API For Smart-Phones

A custom WordPress plugin that extends the core WordPress REST API, providing dedicated endpoints for smartphone application integration. This includes mobile OTP-based authentication, user management, secure password resets, push notification token registration, and product searching integrated with WooCommerce.

---

## Features

*   **OTP Management:** Generate and send 4-digit OTPs via a custom SMS gateway implementation (`boancomm.net`) and handle secure database verification.
*   **Dual Authentication:** Support for traditional Email/Password login as well as secure Mobile Number/OTP-based authentication.
*   **User Management:** Specialized customer registration flow that dynamically generates unique usernames if collisions occur and saves metadata like phone numbers and locations.
*   **Push Notifications:** Dedicated endpoint to update and sync device notification tokens (`nToken`) for user accounts.
*   **WooCommerce Product Dropdown:** High-performance product lookups complete with images, real-time prices, slugs, and stock metadata.

---

## Setup & Installation

1. Upload the plugin folder to the `/wp-content/plugins/` directory of your WordPress installation.
2. Ensure you have created a custom database table named `wp_otp_verification` with the following schema details:
   * `mobile_number` (VARCHAR)
   * `otp` (VARCHAR)
   * `otp_createdon` (DATETIME)
   * `otp_status` (INT - 0: Unverified, 1: Expired, 2: Verified)
3. Activate the plugin through the **Plugins** menu in the WordPress Admin Dashboard.

---

## API Endpoints Reference

All routes below use your configured site base URL (e.g., `https://yourdomain.com/wp-json/`).

### 1. User & Authentication Routes

#### **Get OTP**
* **Endpoint:** `POST /wp-json/api/user/getotp`
* **Body (Form-Data / URL-Encoded):**
  * `mobileNumber` (string, required): 10-digit mobile number.
  * `type` (string, required): `forgotpassword`, `mobilelogin`, `register`, or `resendOtp`.

#### **Verify OTP**
* **Endpoint:** `POST /wp-json/api/user/verifyotp`
* **Body:**
  * `mobileNumber` (string, required)
  * `otp` (string, required): 4-digit token received via SMS.

#### **User Login**
* **Endpoint:** `POST /wp-json/api/user/login`
* **Body:**
  * `login_type` (string, required): `email` or `mobile`.
  * `username` (string, required): Email address or Mobile number.
  * `password` (string, required): User account password or verified OTP code.

#### **Update Password**
* **Endpoint:** `POST /wp-json/api/user/updatePassword`
* **Body:**
  * `user_id` (int, required)
  * `mobileNumber` (string, required)
  * `otp` (string, required)
  * `newpwd` (string, required): Must be at least 8 characters.

#### **User Registration**
* **Endpoint:** `POST /wp-json/api/user/register`
* **Body:**
  * `first_name` (string, required)
  * `last_name` (string, required)
  * `email` (string, required)
  * `password` (string, required)
  * `mobileNumber` (string, required)
  * `location` (string, required)
  * `nToken` (string, optional): Push notification token.

---

### 2. Device & Notification Routes

#### **Update Notification Token**
* **Endpoint:** `PUT /wp-json/api/user/notificationToken`
* **Body:**
  * `user_id` (int, required)
  * `token` (string, required): The target application push token.

---

### 3. Product & Commerce Routes

#### **Search Products Dropdown**
* **Endpoint:** `POST /wp-json/api/api/product/productsDropdown`
* **Body:**
  * `searchName` (string, required): Search query string targeting WooCommerce product titles.
* **Sample Response Structure:**
```json
  {
    "data": [
      {
        "ID": 105,
        "post_title": "Sample Heavy Machinery",
        "image": "[https://yourdomain.com/wp-content/uploads/machinery.jpg](https://yourdomain.com/wp-content/uploads/machinery.jpg)",
        "rentprice": "INR 5000",
        "slug": "sample-heavy-machinery",
        "stockStatus": "instock",
        "stockCount": 3
      }
    ]
  }