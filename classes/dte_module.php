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
            'pdf' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName'),
        ),
    );
	
}

