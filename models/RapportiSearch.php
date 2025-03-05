<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use app\models\Rapporti;

/**
 * RapportiSearch represents the model behind the search form of `app\models\Rapporti`.
 */
class RapportiSearch extends Rapporti
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'id_tipologia', 'id_ambito'], 'integer'],
            [['cf', 'inizio', 'fine'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     * @param string|null $formName Form name to be used into `->load()` method.
     *
     * @return ActiveDataProvider
     */
    public function search($params, $formName = null)
    {
        $query = Rapporti::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params, $formName);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'id' => $this->id,
            'inizio' => $this->inizio,
            'fine' => $this->fine,
            'id_tipologia' => $this->id_tipologia,
            'id_ambito' => $this->id_ambito,
        ]);

        $query->andFilterWhere(['like', 'cf', $this->cf]);

        return $dataProvider;
    }
}
