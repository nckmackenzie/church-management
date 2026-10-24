<?php

class Customers extends Controller
{
    public function __construct()
    {
        if (!isset($_SESSION['userId'])) {
            redirect('users');
            exit;
        }
        $this->authmodel = $this->model('Auth');
        checkrights($this->authmodel,'customers');
        $this->customerModel = $this->model('Customer');
    }

    private function json(int $status, array $body): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($body);
        exit;
    }

    private function formData(array $values = []): array
    {
        return array_merge([
            'title' => 'Add Customer', 'id' => 0, 'isedit' => false,
            'customername' => '', 'contact' => '', 'address' => '', 'pin' => '', 'email' => '', 'contactperson' => '',
        ],$values);
    }

    public function index()
    {
        $this->view('customers/index',['customers' => $this->customerModel->index()]);
        exit;
    }

    public function add()
    {
        $this->view('customers/add',$this->formData());
        exit;
    }

    public function create()
    {
        $this->save(false);
    }

    public function update()
    {
        $this->save(true);
    }

    private function save(bool $edit): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->json(405,['message' => 'Method not allowed']); }
        $fields = json_decode(file_get_contents('php://input'),true);
        if (!is_array($fields)) { $this->json(400,['message' => 'Enter customer details']); }
        $fields['isedit'] = $edit;
        $result = $this->customerModel->save($fields);
        if (!$result['success']) {
            $this->json(422,['message' => htmlspecialchars(implode('; ',$result['errors']),ENT_QUOTES,'UTF-8'),'errors' => $result['errors']]);
        }
        $this->json(200,['success' => true,'id' => $result['id']]);
    }

    public function edit($id)
    {
        $customer = $this->customerModel->getCustomer(trim((string)$id));
        if (!$customer) {
            flash('customer_msg','Customer not found for this congregation',alerterrorclass());
            redirect('customers');
            exit;
        }
        $this->view('customers/add',$this->formData([
            'title' => 'Edit Customer', 'id' => (int)$customer->ID, 'isedit' => true,
            'customername' => strtoupper((string)$customer->customerName),
            'contact' => (string)$customer->contact, 'address' => strtoupper((string)$customer->address),
            'pin' => strtoupper((string)$customer->pin), 'email' => (string)$customer->email,
            'contactperson' => strtoupper((string)$customer->contactPerson),
        ]));
        exit;
    }

    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('users/deniedaccess');
            exit;
        }
        $result = $this->customerModel->delete(trim((string)($_POST['id'] ?? '')));
        if (!$result['success']) {
            flash('customer_msg',htmlspecialchars(implode(' ',$result['errors']),ENT_QUOTES,'UTF-8'),alerterrorclass());
            redirect('customers');
            exit;
        }
        flash('customer_msg','Customer Deleted Successfully!');
        redirect('customers');
        exit;
    }
}
