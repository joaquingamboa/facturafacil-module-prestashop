<?php

class dte_module extends ObjectModel
{

	/** @var string Name */
	public $id_dte;

	/** @var string Name */
	public $id_order;
		
	/** @var string */
	public $id_facturafacil;
	
	/** @var string */
	public $folio;

    /** @var string */
    public $tipo_dte;
    
    /** @var string */
    public $errors;   

    /** @var string */
    public $pdf;          
	
    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'dte_module',
        'primary' => 'id_dte',
        'multilang' => FALSE,
        'fields' => array(
            'id_dte' => array('type' => self::TYPE_INT, 'validate' => 'isInt', 'required' => FALSE),
            'id_order' => array('type' => self::TYPE_INT, 'validate' => 'isInt', 'required' => TRUE),
            'id_facturafacil' => array('type' => self::TYPE_INT, 'validate' => 'isInt'),
            'folio' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName'),
            'tipo_dte' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName'),
            'errors' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName'),
            'pdf' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
        ),
    );

    public static function loadByIdOrder($id_order){
        $result = Db::getInstance()->getRow('
            SELECT *
            FROM `'._DB_PREFIX_.'dte_module` dte
            WHERE dte.`id_order` = '.(int)$id_order
        );

        return new dte_module($result['id_dte']);
    }

    public function TipoDTEString(){
        if($this->tipo_dte == 33){
            return "FACTURA ELECTRONICA";
        }

        if($this->tipo_dte == 34){
            return "FACTURA EXENTA ELECTRONICA";
        }

        if($this->tipo_dte == 39){
            return "BOLETA ELECTRONICA";
        }
    }
	
}

