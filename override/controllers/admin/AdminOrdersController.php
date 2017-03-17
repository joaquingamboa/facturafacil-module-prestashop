<?php
    
class AdminOrdersController extends AdminOrdersControllerCore{

    public function __construct()    {

        $this->addRowAction('delete');

        parent::__construct();

    }
    
	public function postProcess(){
        parent::postProcess();
        
        if (Tools::isSubmit('submitDTE') && Tools::getValue('id_order') > 0) {
                $order = new Order(Tools::getValue('id_order'));
                $customer = new Customer($order->id_customer);
                $order_status = new OrderState((int)$order->current_state, (int)$order->id_lang);
     
                $data = $this->fillOrderConfirmationData($order->id);
                Tools::redirectAdmin(self::$currentIndex.'&id_order='.$order->id.'&vieworder&conf=10&token='.$this->token);
        }

    }

    public static function _getFormatedAddress(Address $the_address, $line_sep, $fields_style = array())
    {
        return AddressFormat::generateAddress($the_address, array('avoid' => array()), $line_sep, ' ', $fields_style);
    }
 
    public function fillOrderConfirmationData($id_order) {
        $order = new Order($id_order);
        $customer = new Customer($order->id_customer);
        $invoice = new Address($order->id_address_invoice);
        $delivery = new Address($order->id_address_delivery);
        $delivery_state = $delivery->id_state ? new State($delivery->id_state) : false;
        $invoice_state = $invoice->id_state ? new State($invoice->id_state) : false;
        $carrier = new Carrier($order->id_carrier);
        $currency = new Currency($order->id_currency);
 
        // Construct order detail table for the email
        $products_list = '';
        $virtual_product = false;

        $documento_tributario = new stdClass();
        $documento_tributario->TipoDTE = 39;
        $documento_tributario->RUTEmisor = Configuration::get("RUT_EMISOR");
        $documento_tributario->TasaIVA = 19;
        $detalles_array = Array();
        $referencias_array = array();
        $cant_cart_item = 1;

        foreach ($order->getCartProducts() as $product) {

            $price = Product::getPriceStatic((int)$product['id_product'], false, ($product['id_product_attribute'] ? (int)$product['id_product_attribute'] : null), 6, null, false, true, $product['cart_quantity'], false, (int)$order->id_customer, (int)$order->id_cart, (int)$order->{Configuration::get('PS_TAX_ADDRESS_TYPE')});
            $price_wt = Product::getPriceStatic((int)$product['id_product'], true, ($product['id_product_attribute'] ? (int)$product['id_product_attribute'] : null), 2, null, false, true, $product['cart_quantity'], false, (int)$order->id_customer, (int)$order->id_cart, (int)$order->{Configuration::get('PS_TAX_ADDRESS_TYPE')});
            $detalle = new stdClass();
            $detalle->TpoCodigo = "INTERNO";
            $detalle->VlrCodigo = $product['reference'];
            $detalle->NroLinDet = $cant_cart_item;
            $detalle->PrcItem = Product::getTaxCalculationMethod() == PS_TAX_EXC ?  Tools::ps_round($price, 2) : $price_wt;
            $detalle->NmbItem = substr($product['product_name'], 0, 40);
            $detalle->QtyItem = $product['cart_quantity'];
            $detalle->DscItem = $product['product_name'].(isset($product['attributes']) ? ' - '.$product['attributes'] : '');
            $detalle->MontoItem = $product['cart_quantity'] * (Product::getTaxCalculationMethod() == PS_TAX_EXC ? Tools::ps_round($price, 2) : $price_wt);        
            array_push($detalles_array, $detalle);

            // Check if is not a virutal product for the displaying of shipping
            if (!$product['is_virtual'])
                $virtual_product &= false;
        }

        $referencia = new stdClass();
        $referencia->NroLinRef = 1;
        $referencia->TpoDocRef = "NVP";
        $referencia->FolioRef = $id_order;
        $referencia->FchRef = date('Y-m-d');
        $referencia->RazonRef = "NOTA DE PRESTASHOP " . $id_order;
        array_push($referencias_array, $referencia);        
 
        $documento_tributario->ObservacionPDF = "ORDEN DE REFERENCIA " . $order->reference;
        $documento_tributario->RutRecep = "66666666-6"; 
        $documento_tributario->RznSocRecep = "CLIENTE DE OCASIÓN";
        //$customer->firstname . " " . $customer->lastname;
        $documento_tributario->CmnaRecep = "NO INFORMADO";
        $documento_tributario->CiudadRecep = "NO INFORMADO";
        $documento_tributario->DirRecep = "NO INFORMADO";
        $documento_tributario->GiroRecep = "NO INFORMADO";
        $documento_tributario->IVA = round(($order->total_products_wt - $order->total_products) + ($order->total_shipping_tax_incl - $order->total_shipping_tax_excl));
        $documento_tributario->MntNeto = round($order->total_products);
        $documento_tributario->MntTotal = round($order->total_paid);

        $json = array(
          'impresoraTermica'=>0, //1 si se necesita para impresora termica de 8 CM
          'DocumentosTributarios'=>  json_decode(json_encode($documento_tributario)),
          'DetalleDocumentostributarios' => json_decode(json_encode($detalles_array)),
        );


        if(isset($referencias_array)){ $json['ReferenciasDt'] = json_decode(json_encode($referencias_array)); }

        $datos = http_build_query($json);
        $signature = hash_hmac("SHA256", $datos, Configuration::get("API_KEY"));
        $url = Configuration::get("URL_FF")."/api/dte";
        $session = curl_init($url);  

        // Tell cURL to return the request data
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($session, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($session, CURLOPT_POSTFIELDS, $datos);
        // Set the HTTP request authentication headers
                $headers = array(
                    "X_USERNAME: " . base64_encode(Configuration::get("USER_FF")),
                    "X_PASSWORD: " . base64_encode(Configuration::get("PASSWORD_FF")),
                    'X_SIGNATURE: '. "$signature",
                    "Content-Type: application/x-www-form-urlencoded", 
                );

        curl_setopt($session, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($session);        
        curl_close($session);
        $documento_rest = json_decode($response); 

        if(isset($documento_rest->errors) && $documento_rest->errors != ""){
            var_dump($documento_rest->errors);
            exit();
        }


        if(isset($documento_rest->Folio)){
            require_once(_PS_MODULE_DIR_. '/dte/classes/dte_module.php');
            $dte_module = new dte_module();
            $dte_module->id_order = $id_order;
            $dte_module->id_facturafacil = $documento_rest->id;
            $dte_module->folio = $documento_rest->Folio;
            $dte_module->tipo_dte = $documento_tributario->TipoDTE;
            $dte_module->errors = $documento_rest->errors;   
            $dte_module->pdf = $documento_rest->PDF;   
            $dte_module->add();
        }
    }

    public function renderView()
    {

        parent::renderView();

        $tpl_file = _PS_MODULE_DIR_.'/dte/views/template/admin/orders/view.tpl';

        $tpl = $this->context->smarty->createTemplate($tpl_file, $this->context->smarty);

        $tpl->assign($this->tpl_view_vars);

        return $tpl->fetch();
    }    
}
