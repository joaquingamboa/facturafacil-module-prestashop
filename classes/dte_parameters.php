<?php

class dte_parameters extends ObjectModel
{
	/** @var string Name */
	public $id_parameter;

    /** @var string */
    public $rut;

	/** @var string */
	public $user;
	
	/** @var string */
	public $password;

    /** @var string */
    public $api_key;
    
    /** @var string */
    public $url;      
	
    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'dte_parameters',
        'primary' => 'id_parameter',
        'multilang' => FALSE,
        'fields' => array(
            'id_parameter' => array('type' => self::TYPE_INT, 'validate' => 'isInt', 'required' => TRUE),
            'rut' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'user' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'password' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'api_key' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'url' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
        ),
    );
	
    public static function loadById($id_parameter){
        $result = Db::getInstance()->getRow('
            SELECT *
            FROM `'._DB_PREFIX_.'dte_parameters` parameter
            WHERE parameter.`id_parameter` = '.(int)$id_parameter
        );
        
        return new dte_parameters($result['id_parameter']);
    }

    /**
     * Comprueba si el rut ingresado es valido
     * @param string $rut RUT
     * @return boolean
     */
    public static function valida_rut($rut)
    {
        if (!preg_match("/^[0-9.]+[-]?+[0-9kK]{1}/", $rut)) {
            return false;
        }
        $rut = preg_replace('/[\.\-]/i', '', $rut);
        $dv = substr($rut, -1);
        $numero = substr($rut, 0, strlen($rut) - 1);
        $i = 2;
        $suma = 0;
        foreach (array_reverse(str_split($numero)) as $v) {
            if ($i == 8)
                $i = 2;
            $suma += $v * $i;
            ++$i;
        }
        $dvr = 11 - ($suma % 11);
        if ($dvr == 11)
            $dvr = 0;
        if ($dvr == 10)
            $dvr = 'K';
        if ($dvr == strtoupper($dv))
            return true;
        else
            return false;
    }

}

