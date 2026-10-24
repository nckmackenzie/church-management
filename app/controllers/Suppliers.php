<?php
class Suppliers extends Controller
{
    public function __construct()
    {
        if(!isset($_SESSION['userId'])){
            redirect('users');
            exit;
        }
        $this->authmodel = $this->model('Auth');
        checkrights($this->authmodel,'suppliers');
        $this->suppliermodel = $this->model('Supplier');
    }

    private function json(int $status, array $body)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($body);
        exit;
    }

    private function formData(array $values = []): array
    {
        return array_merge([
            'title' => 'Add Supplier',
            'id' => 0,
            'isedit' => false,
            'suppliername' => '',
            'contact' => '',
            'address' => '',
            'contactperson' => '',
            'email' => '',
            'pin' => '',
            'openingbal' => '',
            'asof' => '',
            'payable' => (string)($this->suppliermodel->getDefaultPayable() ?? ''),
            'payables' => $this->suppliermodel->getPayableAccounts(),
            'balancelocked' => false,
        ],$values);
    }

    //index
    public function index()
    {
        $data = [
            'suppliers' => $this->suppliermodel->GetSuppliers()
        ];
        $this->view('suppliers/index',$data);
        exit;
    }

    //add suppliers method
    public function add()
    {
        $this->view('suppliers/add',$this->formData());
        exit;
    }

    //create or update supplier method
    public function createupdate()
    {
        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            $this->json(405,['message' => 'Method not allowed']);
        }
        $fields = json_decode(file_get_contents('php://input'),true); //extract submited json data
        if(!is_array($fields)){
            $this->json(400,['message' => 'Enter supplier name']);
        }
        $result = $this->suppliermodel->CreateUpdate([
            'id' => $fields['id'] ?? 0,
            'isedit' => converttobool($fields['isedit'] ?? false),
            'suppliername' => $fields['suppliername'] ?? '',
            'contact' => $fields['contact'] ?? '',
            'pin' => $fields['pin'] ?? '',
            'address' => $fields['address'] ?? '',
            'email' => $fields['email'] ?? '',
            'contactperson' => $fields['contactperson'] ?? '',
            'balance' => $fields['openingbal'] ?? '',
            'asof' => $fields['asof'] ?? '',
            'payable' => $fields['payable'] ?? '',
        ]);
        if(!$result['success']){
            // escaped: utils.js displayAlert() inserts the message as HTML
            $this->json(422,['message' => htmlspecialchars(implode('; ',$result['errors']),ENT_QUOTES,'UTF-8'),'errors' => $result['errors']]);
        }
        $this->json(200,['success' => true,'id' => $result['id']]);
    }

    public function edit($id)
    {
        $supplier = $this->suppliermodel->GetSupplier(trim($id));
        if(!$supplier){
            flash('supplier_msg','Supplier not found for this congregation',alerterrorclass());
            redirect('suppliers');
            exit;
        }
        $opening = $supplier->opening;
        $values = [
            'title' => 'Edit Supplier',
            'id' => $supplier->ID,
            'isedit' => true,
            'suppliername' => strtoupper($supplier->supplierName),
            'contact' => $supplier->contact,
            'address' => strtoupper((string)$supplier->address),
            'contactperson' => strtoupper((string)$supplier->contactPerson),
            'email' => $supplier->email,
            'pin' => strtoupper((string)$supplier->pin),
            'balancelocked' => $supplier->openingHasPayments,
        ];
        if($opening && (float)$opening['inclusiveVat'] != 0){
            $values['openingbal'] = $opening['inclusiveVat'];
            $values['asof'] = $opening['invoiceDate'];
            // a legacy row has no stored account: the default is preselected and the user confirms it
            if(!is_null($opening['PayableAccountId'])){
                $values['payable'] = (string)$opening['PayableAccountId'];
            }
        }
        $data = $this->formData($values);
        // the stored crediting account may have been deactivated since: keep it listed so the form still shows it
        if($data['payable'] !== '' && !in_array((int)$data['payable'],array_map('intval',array_column($data['payables'],'ID')),true)){
            $row = $this->suppliermodel->getAccountRow((int)$data['payable']);
            if($row){ array_unshift($data['payables'],$row); }
        }
        $this->view('suppliers/add',$data);
        exit;
    }

    public function delete()
    {
        if($_SERVER['REQUEST_METHOD'] !== 'POST')
        {
            redirect('users/deniedaccess');
            exit;
        }
        $id = isset($_POST['id']) ? trim((string)$_POST['id']) : '';
        $result = $this->suppliermodel->Delete($id);
        if(!$result['success']){
            flash('supplier_msg',htmlspecialchars(implode(' ',$result['errors']),ENT_QUOTES,'UTF-8'),alerterrorclass());
            redirect('suppliers');
            exit;
        }
        flash('supplier_msg','Deleted successfully!');
        redirect('suppliers');
        exit;
    }
}
