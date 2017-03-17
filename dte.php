<?php


if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(dirname(__FILE__) . '/classes/dte_parameters.php');
require_once(dirname(__FILE__) . '/classes/dte_module.php');

class dte extends Module {


    private $defaultConfig = [
        'URL_FF' => '',
        'RUT_EMISOR' => '',
        'USER_FF' => '',
        'PASSWORD_FF' => '',
        'API_KEY' => '',
    ]; 

    public function __construct() {
        $this->name = 'DTE';
        $this->tab = 'Modulo DTE';
        $this->version = '1.0';
        $this->author = 'JIGF';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('Modulo DTE');
        $this->description = $this->l('Modulo para la generacion de una boleta en facturafacil');
    }

    public function install() {
        $sql = array();
	   
        $sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'dte_parameters` (
                  `id_parameter` int(10) unsigned NOT NULL AUTO_INCREMENT,
                  `rut` VARCHAR( 12 ) NULL,
                  `user` VARCHAR( 64 ) NULL,
                  `password` VARCHAR( 64 ) NULL,
                  `api_key` VARCHAR( 45 ) NULL,
                  `url` VARCHAR( 300 )  NULL,
                  PRIMARY KEY (`id_parameter`)
                ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8';

        $sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'dte_module` (
                  `id_dte` int(10) unsigned NOT NULL AUTO_INCREMENT,
                  `id_order` INT( 11 ) UNSIGNED NULL,
                  `id_facturafacil` INT( 11 ) UNSIGNED NULL,
                  `folio` INT( 11 ) UNSIGNED NULL,
                  `tipo_dte` VARCHAR( 3 ) NULL,
                  `errors` TEXT NULL,
                  `pdf` TEXT NULL,
                  PRIMARY KEY (`id_dte`)
                ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8';
                                
        if (!parent::install() OR 
            !$this->registerHook('EnviarDTE') OR
            !$this->registerHook('actionProductUpdate') OR
            !$this->registerHook('DisplayAdminProductsExtra') OR
            !$this->registerHook('displayFooterProduct') OR
			!$this->runSql($sql)
        ) {
            return FALSE;
        }
        
        return TRUE;
    }
    
    public function uninstall() {
        $sql = array();

	    $sql[] = 'DROP TABLE IF EXISTS `'._DB_PREFIX_.'dte_module`';
        $sql[] = 'DROP TABLE IF EXISTS `'._DB_PREFIX_.'dte_parameters`';
        if (!parent::uninstall() OR
            !$this->runSql($sql) 
        ) {
            return FALSE;
        }

        return TRUE;
    }
    
    public function runSql($sql) {
        foreach ($sql as $s) {
			if (!Db::getInstance()->Execute($s)){
				return FALSE;
			}
        }
        
        return TRUE;
    }
    
    public function hookEnviarDTE($params) {
        $id_order = Tools::getValue('id_order');
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
        $documento_tributario->RUTEmisor = "76250838-9";
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
        $referencia->TpoDocRef = "PNV";
        $referencia->FolioRef = $id_order;
        $referencia->FchRef = date('y-m-d');
        $referencia->RazonRef = "NOTA DE PRESTASHOP";
        array_push($referencias_array, $referencia);        
 
        $documento_tributario->ObservacionPDF = "ORDEN DE REFERENCIA " . $order->reference;
        $documento_tributario->RutRecep = "18161794-2"; 
        $documento_tributario->RznSocRecep = $customer->firstname . " " . $customer->lastname;
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
        $signature = hash_hmac("SHA256", $datos, '7785261fb22856a302a93d0c3c6315d1');
        $url = "http://dev.adichilespa.cl/facturafacil/api/dte";
        $session = curl_init($url);  

        // Tell cURL to return the request data
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($session, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($session, CURLOPT_POSTFIELDS, $datos);
        // Set the HTTP request authentication headers
                $headers = array(
                    "X_USERNAME: " . base64_encode("demo"),
                    "X_PASSWORD: " . base64_encode("demo1234"),
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

    }
    
    public function hookDisplayAdminProductsExtra($params) {
        $id_product = Tools::getValue('id_product');
        $sampleObj = Belvg_Sample::loadByIdProduct($id_product);
        if(!empty($sampleObj) && isset($sampleObj->id)){
            $this->context->smarty->assign(array(
                'belvg_textarea' => $sampleObj->textarea,
            ));
        }
        
        return $this->display(__FILE__, 'views/admin/sample.tpl');
    }

    public function hookActionProductUpdate($params) {
        $id_product = Tools::getValue('id_product');
        $sampleObj = Belvg_Sample::loadByIdProduct($id_product);
        $sampleObj->textarea = Tools::getValue('belvg_sample');
        $sampleObj->id_product = $id_product;
        
        if(!empty($sampleObj) && isset($sampleObj->id)){
            $sampleObj->update();
        } else {
            $sampleObj->add();
        }
    }
    
    public function hookDisplayFooterProduct($params) {
        $id_product = Tools::getValue('id_product');
        $sampleObj = Belvg_Sample::loadByIdProduct($id_product);
        if(!empty($sampleObj) && isset($sampleObj->id)){
            $this->context->smarty->assign(array(
                'belvg_textarea' => $sampleObj->textarea,
            ));
        }
        
        return $this->display(__FILE__, 'views/frontend/sample.tpl');
    }

    public function getContent()
    {
        $output = null;
        if (Tools::isSubmit('submit'.$this->name)) {
            // verificar que se hayan pasado los datos mínimos
            $error = false;
            $config = [];
            foreach ($this->defaultConfig as $key => $value) {
                $valor = Tools::getValue($key);
                if (!$valor || empty($valor)) {
                    $output .= $this->displayError($this->l('Debe especificar '.$key));
                    $error = true;
                    break;
                } else {
                    $config[$key] = $valor;
                }
            }
            if ($config['RUT_EMISOR']) {
                $rut = dte_parameters::valida_rut($config['RUT_EMISOR']);
                if (!$rut) {
                    $output .= $this->displayError($this->l('RUT del contribuyente es incorrecto'));
                    $error = true;
                } else {
                    $config['RUT_EMISOR'] = $config['RUT_EMISOR'];
                }
            }
            if (!$error) {
                foreach ($config as $key => $value) {
                    Configuration::updateValue($key, $value);
                }
                $output .= $this->displayConfirmation($this->l('Configuración actualizada'));
            }
        }
        return $output.$this->displayForm();
    }

    private function displayForm()
    {
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        // campos del formulario
        $fields_form[0]['form'] = [
            'legend' => [
                'title' => $this->l('Configuración básica'),
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('URL FACTURAFACIL'),
                    'name' => 'URL_FF',
                    'size' => 100,
                    'required' => true,
                    'length' => 10,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('RUT CONTRIBUYENTE'),
                    'name' => 'RUT_EMISOR',
                    'size' => 100,
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('USUARIO FACTURAFACIL'),
                    'name' => 'USER_FF',
                    'size' => 100,
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('CONTRASENA FACTURAFACIL'),
                    'name' => 'PASSWORD_FF',
                    'size' => 100,
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('API KEY'),
                    'name' => 'API_KEY',
                    'size' => 100,
                    'required' => true,
                ]                

            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'button'
            ]
        ];
        $helper = new HelperForm();
        // módulo, token e índice actual
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        // idioma
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;
        // título y barra de herramientas
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = [
            'save' => [
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                '&token='.Tools::getAdminTokenLite('AdminModules'),
            ],
            'back' => [
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            ],
        ];
        // asignar valores para el formulario y entregar vista
        foreach ($this->defaultConfig as $key => $value) {
            $helper->fields_value[$key] = Configuration::get($key);
        }

        return $helper->generateForm($fields_form);
    }
    
}