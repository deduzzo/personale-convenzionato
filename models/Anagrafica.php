<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "anagrafica".
 *
 * @property string $cf
 * @property string|null $nominativo
 * @property string|null $cognome
 * @property string|null $nome
 * @property string|null $data_nascita
 *
 * @property Rapporti[] $rapportis
 */
class Anagrafica extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'anagrafica';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['nominativo', 'cognome', 'nome', 'data_nascita'], 'default', 'value' => null],
            [['cf'], 'required'],
            [['data_nascita'], 'safe'],
            [['cf'], 'string', 'max' => 16],
            [['nominativo', 'cognome', 'nome'], 'string', 'max' => 100],
            [['cf'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'cf' => 'Cf',
            'nominativo' => 'Nominativo',
            'cognome' => 'Cognome',
            'nome' => 'Nome',
            'data_nascita' => 'Data Nascita',
        ];
    }

    /**
     * Gets query for [[Rapportis]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRapportis()
    {
        return $this->hasMany(Rapporti::class, ['cf' => 'cf']);
    }

}
