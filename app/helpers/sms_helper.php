<?php
require 'AT/vendor/autoload.php';
use AfricasTalking\SDK\AfricasTalking;

function sendLink($phone,$info){
    // Set your app credentials
    $username   = USER_SMS;
    $apiKey     = APIKEY;

    // Initialize the SDK
    $AT         = new AfricasTalking($username, $apiKey);

    // Get the SMS service
    $sms        = $AT->sms();

    // Set the numbers you want to send to in international format
    $recipients = $phone;

    // Set your message
    $message    = $info;

    // Set your shortCode or senderId
    $from       = SENDER;

    try {
        // Thats it, hit send and we'll take care of the rest
        $result = $sms->send([
             'to'      => $recipients,
             'message' => $message,
             'from'    => $from
        ]);

         //print_r($result);
        } catch (Exception $e) {
            error_log($e->getMessage(),0);
            exit;
        }
}
function sendSms($phone,$name,$id){
    // Set your app credentials
    $username   = USER_SMS;
    $apiKey     = APIKEY;

    // Initialize the SDK
    $AT         = new AfricasTalking($username, $apiKey);

    // Get the SMS service
    $sms        = $AT->sms();

    // Set the numbers you want to send to in international format
    $recipients = $phone;

    // Set your message
    $message    = 'Hi, ' .$name .',To Update Your Info For PCEA Click On This Link: https://member.pceakalimoniparish.or.ke/?rd='.$id;

    // Set your shortCode or senderId
    $from       = SENDER;

    try {
        // Thats it, hit send and we'll take care of the rest
        $result = $sms->send([
             'to'      => $recipients,
             'message' => $message,
             'from'    => $from
        ]);

         //print_r($result);
        } catch (Exception $e) {
            error_log($e->getMessage(),0);
            exit;
        }
}

//general message
function sendgeneral($contacts,$message){
    $username   = USER_SMS;
    $apiKey     = APIKEY;

    // Initialize the SDK
    $AT         = new AfricasTalking($username, $apiKey);

    $sms        = $AT->sms();
    $recipients = $contacts;

    $from       = SENDER;

    try {
        $result = $sms->send([
             'to'      => $recipients,
             'message' => $message,
             'from'    => $from
        ]);

         return $result;
        } catch (Exception $e) {
            error_log($e->getMessage(),0);
            return false;
            exit;
        }
}