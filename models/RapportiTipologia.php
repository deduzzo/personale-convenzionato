<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "rapporti_tipologia".
 *
 * @property int $id
 * @property string|null $descrizione
 *
 * @property Ambiti[] $ambitis
 * @property Rapporti[] $rapportis
 */
class RapportiTipologia extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'rapporti_tipologia';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['descrizione'], 'default', 'value' => null],
            [['descrizione'], 'string'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'descrizione' => 'Descrizione',
        ];
    }

    /**
     * Gets query for [[Ambitis]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getAmbitis()
    {
        return $this->hasMany(Ambiti::class, ['id_tipologia_applicabile' => 'id']);
    }

    /**
     * Gets query for [[Rapportis]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRapportis()
    {
        return $this->hasMany(Rapporti::class, ['id_tipologia' => 'id']);
    }

}
