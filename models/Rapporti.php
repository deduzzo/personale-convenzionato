<?php

namespace app\models;

use app\models\enums\FileImportMediciNAR;
use Yii;

/**
 * This is the model class for table "rapporti".
 *
 * @property int $id
 * @property string $cf
 * @property string $inizio
 * @property string|null $fine
 * @property int $id_tipologia
 * @property int|null $id_ambito
 *
 * @property Ambiti $ambito
 * @property Caratteristiche[] $caratteristiches
 * @property Anagrafica $cf0
 * @property Indirizzi[] $indirizzis
 * @property RapportiCaratteristiche[] $rapportiCaratteristiches
 * @property RapportiTipologia $tipologia
 */
class Rapporti extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'rapporti';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['fine', 'id_ambito'], 'default', 'value' => null],
            [['cf', 'inizio', 'id_tipologia'], 'required'],
            [['inizio', 'fine'], 'safe'],
            [['id_tipologia', 'id_ambito'], 'integer'],
            [['cf'], 'string', 'max' => 16],
            [['id_ambito'], 'exist', 'skipOnError' => true, 'targetClass' => Ambiti::class, 'targetAttribute' => ['id_ambito' => 'id']],
            [['cf'], 'exist', 'skipOnError' => true, 'targetClass' => Anagrafica::class, 'targetAttribute' => ['cf' => 'cf']],
            [['id_tipologia'], 'exist', 'skipOnError' => true, 'targetClass' => RapportiTipologia::class, 'targetAttribute' => ['id_tipologia' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'cf' => 'Cf',
            'inizio' => 'Inizio',
            'fine' => 'Fine',
            'id_tipologia' => 'Id Tipologia',
            'id_ambito' => 'Id Ambito',
        ];
    }

    /**
     * Gets query for [[Ambito]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getAmbito()
    {
        return $this->hasOne(Ambiti::class, ['id' => 'id_ambito']);
    }

    /**
     * Gets query for [[Caratteristiches]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCaratteristiches()
    {
        return $this->hasMany(Caratteristiche::class, ['id_rapporto_applicabile' => 'id']);
    }

    /**
     * Gets query for [[Cf0]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCf0()
    {
        return $this->hasOne(Anagrafica::class, ['cf' => 'cf']);
    }

    /**
     * Gets query for [[Indirizzis]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getIndirizzis()
    {
        return $this->hasMany(Indirizzi::class, ['id_rapporto' => 'id']);
    }

    /**
     * Gets query for [[RapportiCaratteristiches]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRapportiCaratteristiches()
    {
        return $this->hasMany(RapportiCaratteristiche::class, ['id_rapporto' => 'id_ambito']);
    }

    /**
     * Gets query for [[Tipologia]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTipologia()
    {
        return $this->hasOne(RapportiTipologia::class, ['id' => 'id_tipologia']);
    }

    // aggiornaIndirizzoPrimario($indirizzo, $lat, $lng);
    public function aggiornaIndirizzoPrimario($nuovoIndirizzo, $lat, $lng)
    {
        $indirizzo = Indirizzi::find()->where(['id_rapporto' => $this->id, 'primario' => true])->one();
        if (!$indirizzo) {
            $indirizzo = new Indirizzi();
            $indirizzo->id_rapporto = $this->id;
            $indirizzo->primario = true;
            $indirizzo->attivo=true;
        }
        $indirizzo->indirizzo = $nuovoIndirizzo;
        $indirizzo->lat = $lat;
        $indirizzo->long = $lng;
        $indirizzo->save();
    }

    public function getCodRegionaleSeEsiste() {
        $car = RapportiCaratteristiche::find()->where([
            'id_rapporto' => $this->id,
            'id_caratteristica' => FileImportMediciNAR::COD_REG_ID
        ])->one();
        if ($car) {
            return $car->valore;
        }
        else return null;
    }
}
