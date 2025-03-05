<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "caratteristiche".
 *
 * @property int $id
 * @property string $descrizione
 *
 * @property RapportiCaratteristiche[] $rapportiCaratteristiches
 */
class Caratteristiche extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'caratteristiche';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['descrizione'], 'required'],
            [['descrizione'], 'string', 'max' => 100],
            [['descrizione'], 'unique'],
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
     * Gets query for [[RapportiCaratteristiches]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRapportiCaratteristiches()
    {
        return $this->hasMany(RapportiCaratteristiche::class, ['id_caratteristica' => 'id']);
    }

}
