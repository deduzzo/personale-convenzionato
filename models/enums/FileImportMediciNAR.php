<?php

namespace app\models\enums;

use yii2mod\enum\helpers\BaseEnum;

class FileImportMediciNAR extends BaseEnum
{
    const COD_REGIONALE = "Cod. regionale";
    const NOMINATIVO = "Cognome e Nome";
    const COD_FISCALE = "Cod. fiscale";
    const CATEGORIA = "Categoria";
    const DATA_INIZIO_RAPPORTO = "Data inizio rapporto";
    const DATA_FINE_RAPPORTO = "Data fine rapporto";
    const ASL = "ASL";
    const AMBITO = "Ambito";
    const MASSIMALE = "Mas.";
    const STATO = "Stato";

    const CATEGORIA_MMG = "Medico di base";
    const CATEGORIA_PLS = "Pediatra di Libera Scelta";

    const MMG_ID = 1;
    const PLS_ID = 2;

    const MASSIMALE_ID = 1;
    const COD_REG_ID = 2;

    const CARATTERISTICA_COD_REG = "COD_REG";
    const CARATTERISTICA_MASSIMALE = "MASSIMALE";

    /**
     * @var string message category
     * You can set your own message category for translate the values in the $list property
     * Values in the $list property will be automatically translated in the function `listData()`
     */
    public static $messageCategory = 'app';

    /**
     * @var array
     */
    public static $list = [
        self::COD_REGIONALE => 'Cod. regionale',
        self::NOMINATIVO => 'Cognome e Nome',
        self::COD_FISCALE => 'Cod. fiscale',
        self::CATEGORIA => 'Categoria',
        self::DATA_INIZIO_RAPPORTO => 'Data inizio rapporto',
        self::DATA_FINE_RAPPORTO => 'Data fine rapporto',
        self::ASL => 'ASL',
        self::AMBITO => 'Ambito',
        self::MASSIMALE => 'Mas.',
        self::STATO => 'Stato',
        self::CATEGORIA_MMG => self::MMG_ID,
        self::CATEGORIA_PLS => self::PLS_ID,
        self::MMG_ID => self::MMG_ID,
        self::PLS_ID => self::PLS_ID,
        self::CARATTERISTICA_COD_REG => self::COD_REG_ID,
        self::CARATTERISTICA_MASSIMALE => self::MASSIMALE_ID,
    ];
}
