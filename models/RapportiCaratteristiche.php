<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "rapporti_caratteristiche".
 *
 * @property int $id_rapporto
 * @property int $id_caratteristica
 * @property string|null $valore
 * @property bool $valido
 *
 * @property Caratteristiche $caratteristica
 * @property Rapporti $rapporto
 */
class RapportiCaratteristiche extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'rapporti_caratteristiche';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['valore'], 'default', 'value' => null],
            [['id_rapporto', 'id_caratteristica', 'valido'], 'required'],
            [['id_rapporto', 'id_caratteristica'], 'integer'],
            [['valido'], 'boolean'],
            [['valore'], 'string', 'max' => 100],
            [['id_rapporto', 'id_caratteristica', 'valido'], 'unique', 'targetAttribute' => ['id_rapporto', 'id_caratteristica', 'valido']],
            [['id_caratteristica'], 'exist', 'skipOnError' => true, 'targetClass' => Caratteristiche::class, 'targetAttribute' => ['id_caratteristica' => 'id']],
            [['id_rapporto'], 'exist', 'skipOnError' => true, 'targetClass' => Rapporti::class, 'targetAttribute' => ['id_rapporto' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_rapporto' => 'Id Rapporto',
            'id_caratteristica' => 'Id Caratteristica',
            'valore' => 'Valore',
            'valido' => 'Valido',
        ];
    }

    /**
     * Gets query for [[Caratteristica]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCaratteristica()
    {
        return $this->hasOne(Caratteristiche::class, ['id' => 'id_caratteristica']);
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
