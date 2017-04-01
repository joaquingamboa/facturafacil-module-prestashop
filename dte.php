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
                  `pdf` MEDIUMTEXT NULL,
                  `rut` VARCHAR ( 11 ) NULL,
                  `razon_social` VARCHAR ( 100 ) NULL,
                  `direccion` VARCHAR ( 70 ) NULL,
                  `giro` VARCHAR ( 40 )  NULL,
                  `comuna` VARCHAR ( 20 )  NULL,
                  PRIMARY KEY (`id_dte`)
                ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8';
                                
        if (!parent::install() OR 
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