<?php
require 'AT/vendor/autoload.php';
use AfricasTalking\SDK\AfricasTalking;

function sendLink($phone,$info){
    // Set your app credentials
    $username   = "kalimonicms";
    $apiKey     = "9c52381e8eea1cfda5638316567149dd2519b30e0e9b80a4802164df2470d532";

    // Initialize the SDK
    $AT         = new AfricasTalking($username, $apiKey);

    // Get the SMS service
    $sms        = $AT->sms();

    // Set the numbers you want to send to in international format
    $recipients = $phone;

    // Set your message
    $message    = $info;

    // Set your shortCode or senderId
    $from       = "PCEAKALIMON";

    try {
        // Thats it, hit send and we'll take care of the rest
        $result = $sms->send([
             'to'      => $recipients,
             'message' => $message,
             'from'    => $from
        ]);

         //print_r($result);
        } catch (Exception $e) {
            echo "Error: ".$e->getMessage();
        }
}
function sendSms($phone,$name,$id){
    // Set your app credentials
    $username   = "kalimonicms";
    $apiKey     = "9c52381e8eea1cfda5638316567149dd2519b30e0e9b80a4802164df2470d532";

    // Initialize the SDK
    $AT         = new AfricasTalking($username, $apiKey);

    // Get the SMS service
    $sms        = $AT->sms();

    // Set the numbers you want to send to in international format
    $recipients = $phone;

    // Set your message
    $message    = 'Hi, ' .$name .',To Update Your Info For PCEA Click On This Link: http://pceakalimoniparish.or.ke/update/mbs.php?rd='.$id;

    // Set your shortCode or senderId
    $from       = "PCEAKALIMON";

    try {
        // Thats it, hit send and we'll take care of the rest
        $result = $sms->send([
             'to'      => $recipients,
             'message' => $message,
             'from'    => $from
        ]);

         //print_r($result);
        } catch (Exception $e) {
            echo "Error: ".$e->getMessage();
        }
}