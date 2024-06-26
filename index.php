<?php
// Lightweight Learning Tools Interoperability (LTI) Tool Provider
//
// A very basic LTI tool provider with minimal capabilities and PHP 8 coding.
//
// Based on https://www.imsglobal.org/wiki/step-1-lti-launch-request
//
// For the LTI standard, see https://www.1edtech.org/standards/lti
// 
// Copyright 2024 Greg Sink
// Licensed under GPL-3.0-or-later
// Author: gsink22@gmail.com
// Portions generated by ChatGPT 3.5
//
// This program is free software: you can redistribute it and/or modify it under the terms of 
// the GNU General Public License as published by the Free Software Foundation, either 
// version 3 of the License, or any later version.
//
// This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
// without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
// See the GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License along with this program. 
// If not, see <https://www.gnu.org/licenses/>.

// Temporary - simulate POST

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['lti_message_type'] = 'basic-lti-launch-request';
$_POST['lti_version'] = 'LTI-1p0';
$_POST['oauth_consumer_key'] = 'someKey';
$_POST['oauth_nonce'] = 'abc';
$_POST['oauth_signature'] = 'abc';
$_POST['oauth_signature_method'] = 'HMAC-SHA1';
$_POST['oauth_timestamp'] = '0';
$_POST['oauth_version'] = '1.0';
$_POST['resource_link_id'] = 'someLinkId';
$_POST['user_id'] = 'userId';
$_POST['roles'] = 'urn:lti:role:ims/lis/Instructor';
$_POST['lis_person_name_given'] = 'given';
$_POST['lis_person_name_family'] = 'family';
$_POST['lis_outcome_service_url'] = 'outcomeServiceURL.com';
$_POST['lis_result_sourcedid'] = 'resultSourceId';
$consumerSecret = 'your_consumer_secret';

function ltiError($message)
{
    error_log(sprintf("ERROR:%s", $message));
    return false;
}
function ltiInfo($message)
{
    error_log(sprintf("INFO:%s", $message));
    return true;
}

function generateBaseString($url, $params) {
    ksort($params);
    $paramString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    return "POST" . "&" . rawurlencode($url) . "&" . rawurlencode($paramString);
}

function generateSignature($baseString, $consumerSecret) {
    $signingKey = rawurlencode($consumerSecret) . '&';
    return base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));
}

function parseRoles($rolesString)
{
    $rolesArray = explode(',', $rolesString);
    $roles = array();
    foreach ($rolesArray as $role) {
        $role = trim($role);
        if (!empty($role)) {
            if (substr($role, 0, 4) !== 'urn:') {
                $role = "urn:lti:role:ims/lis/{$role}";
            }
            $roles[] = $role;
        }
    }
    return array_unique($roles);
}

$ok = true;

ltiInfo("LLTI Request received.");

// Step 1: Validate LTI launch request parameters.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') $ok = ltiError(sprintf("Missing or wrong REQUEST_METHOD (%s).", $_SERVER['REQUEST_METHOD']));
if (!isset($_POST['lti_message_type']) || $_POST['lti_message_type'] !== 'basic-lti-launch-request') $ok = ltiError(sprintf("Missing or wrong lti_message_type (%s).", $_POST['lti_message_type']));
if (!isset($_POST['lti_version']) || $_POST['lti_version'] !== 'LTI-1p0') $ok = ltiError(sprintf("Missing or wrong lti_version (%s).", $_POST['lti_version']));
if (!isset($_POST['resource_link_id'])) $ok = ltiError("Missing resource_link_id.");
if (!isset($_POST['user_id'])) $ok = ltiError("Missing user_id.");
if (!isset($_POST['roles'])) $ok = ltiError("Missing roles.");

// Step 2: Check OAuth signature.

if (!isset($_POST['oauth_consumer_key'])) $ok = ltiError("Missing oauth_consumer_key.");
if (!isset($_POST['oauth_nonce'])) $ok = ltiError("Missing oauth_nonce.");
if (!isset($_POST['oauth_signature'])) $ok = ltiError("Missing oauth_signature.");
if (!isset($_POST['oauth_signature_method']) || $_POST['oauth_signature_method'] !== 'HMAC-SHA1') $ok = ltiError(sprintf("Missing or invalid oauth_signature_method (%s).",$_POST['oauth_signature_method']));
if (!isset($_POST['oauth_timestamp'])) $ok = ltiError("Missing oauth_timestamp.");
if (!isset($_POST['oauth_version']) || $_POST['oauth_version'] !== '1.0') $ok = ltiError(sprintf("Missing or invalid oauth_version (%s).",$_POST['oauth_version']));

if ($ok) {
    try {   
        // Extract OAuth parameters from the request
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        preg_match_all('/oauth_[a-z_]+="([^"]+)"/', $authHeader, $matches);
        $oauthParams = array_combine($matches[0], $matches[1]);

        // Extract the request URL and method
        $url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        // Extract POST parameters
        $postParams = $_POST;

        // Combine OAuth and POST parameters
        $params = array_merge($oauthParams, $postParams);

        // Reconstruct the base string
        $baseString = generateBaseString($url, $params);

        // Generate the expected signature
        $expectedSignature = generateSignature($baseString, $consumerSecret);

        // Compare the generated signature with the provided one
        $ok = hash_equals($expectedSignature, $oauthParams['oauth_signature']);        
        if (!$ok) {
            ltiError("Signature mismatch.");
            ltiError(sprintf("oauth_consumer_key (%s)", $_POST['oauth_consumer_key']));
            ltiError(sprintf("oauth_nonce (%s)", $_POST['oauth_nonce']));
            ltiError(sprintf("oauth_signature (%s)", $_POST['oauth_signature']));
            ltiError(sprintf("oauth_timestamp (%s)", $_POST['oauth_timestamp']));
        }
    } catch (OAuthException $e) {
        $ok = ltiError(sprintf("OAuth failure (%s).",$e->getMessage));
    } 
}

// Step 3: Establish a user session

session_start();
$_SESSION = array();
session_destroy();
session_start();

$_SESSION['user_id'] = $_POST['user_id'];
$_SESSION['lis_person_name_given'] = $_POST['lis_person_name_given'];
$_SESSION['lis_person_name_family'] = $_POST['lis_person_name_family'];
$_SESSION['resource_link_id'] = $_POST['resource_link_id'];
$_SESSION['roles'] = parseRoles($_POST['roles']);  // Convert to array for convenience
if (in_array('urn:lti:role:ims/lis/Instructor', $_SESSION['roles'])) {
    if (isset($_POST['lis_outcome_service_url']) && isset($_POST['lis_result_sourcedid'])) {
            $_SESSION['lis_outcome_service_url'] = $_POST['lis_outcome_service_url'];
            $_SESSION['lis_result_sourcedid'] = $_POST['lis_result_sourcedid'];
    }
}
elseif (!in_array('urn:lti:role:ims/lis/Learner', $_SESSION['roles'])) {
    $ok = ltiError(sprintf("Missing required instructor or learner role. (%s).",$_POST['roles']));
}

// Step 4: Redirect the user)

if ($ok) {
    header("welcome.php");
} else {
    header("ltiError.php");
}
exit(); 
