<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "indirizzi".
 *
 * @property int $id
 * @property int $id_rapporto
 * @property string $indirizzo
 * @property string|null $cap
 * @property string|null $citta
 * @property string|null $lat
 * @property string|null $long
 * @property bool $primario
 * @property bool $attivo
 *
 * @property Rapporti $rapporto
 */
class Indirizzi extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'indirizzi';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['cap', 'citta', 'lat', 'long'], 'default', 'value' => null],
            [['attivo'], 'default', 'value' => 1],
            [['id_rapporto', 'indirizzo'], 'required'],
            [['id_rapporto'], 'integer'],
            [['indirizzo'], 'string'],
            [['primario', 'attivo'], 'boolean'],
            [['cap'], 'string', 'max' => 5],
            [['citta', 'lat', 'long'], 'string', 'max' => 100],
            [['id_rapporto'], 'exist', 'skipOnError' => true, 'targetClass' => Rapporti::class, 'targetAttribute' => ['id_rapporto' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_rapporto' => 'Id Rapporto',
            'indirizzo' => 'Indirizzo',
            'cap' => 'Cap',
            'citta' => 'Citta',
            'lat' => 'Lat',
            'long' => 'Long',
            'primario' => 'Primario',
            'attivo' => 'Attivo',
        ];
    }

    /**
     * Gets query for [[Rapporto]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRapporto()
    {
        return $this->hasOne(Rapporti::class, ['id' => 'id_rapporto']);
    }

}
