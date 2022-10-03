<?php
class Bookings extends Controller{
    public function __construct()
    {
        if (!isset($_SESSION['userId']) || $_SESSION['userType'] > 2) {
            redirect('');
        }
    }
    public function index()
    {
        $data =[
            'message' => 'To Book Your Seat At ' .  strtoupper($_SESSION['congName']) .' Click this link http://pceakalimoniparish.or.ke/booking/'
        ];
        $this->view('bookings/index',$data);
    }
    public function send()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
           $_POST = filter_input_array(INPUT_POST,FILTER_SANITIZE_STRING);
           $data = ['message' => trim($_POST['message'])];
           $phone = '+254724466628';
           sendLink($phone,$data['message']);
           redirect('bookings');
        }
    }
}