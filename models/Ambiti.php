<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "ambiti".
 *
 * @property int $id
 * @property string|null $descrizione
 * @property int $id_tipologia_applicabile
 *
 * @property Rapporti[] $rapportis
 * @property RapportiTipologia $tipologiaApplicabile
 */
class Ambiti extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'ambiti';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['descrizione'], 'default', 'value' => null],
            [['id_tipologia_applicabile'], 'required'],
            [['id_tipologia_applicabile'], 'integer'],
            [['descrizione'], 'string', 'max' => 100],
            [['id_tipologia_applicabile'], 'exist', 'skipOnError' => true, 'targetClass' => RapportiTipologia::class, 'targetAttribute' => ['id_tipologia_applicabile' => 'id']],
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
            'id_tipologia_applicabile' => 'Id Tipologia Applicabile',
        ];
    }

    /**
     * Gets query for [[Rapportis]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRapportis()
    {
        return $this->hasMany(Rapporti::class, ['id_ambito' => 'id']);
    }

    /**
     * Gets query for [[TipologiaApplicabile]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTipologiaApplicabile()
    {
        return $this->hasOne(RapportiTipologia::class, ['id' => 'id_tipologia_applicabile']);
    }

}
