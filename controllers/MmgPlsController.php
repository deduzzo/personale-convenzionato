<?php

namespace app\controllers;

use app\models\Indirizzi;
use app\models\Rapporti;
use app\models\RapportiSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\Response;

/**
 * MmgPlsController implements the CRUD actions for Rapporti model.
 */
class MmgPlsController extends Controller
{
    /**
     * @inheritDoc
     */
    public function behaviors()
    {
        return array_merge(
            parent::behaviors(),
            [
                'verbs' => [
                    'class' => VerbFilter::className(),
                    'actions' => [
                        'delete' => ['POST'],
                    ],
                ],
            ]
        );
    }

    /**
     * Lists all Rapporti models.
     *
     * @return string
     */
    public function actionIndex()
    {
        $searchModel = new RapportiSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Rapporti model.
     * @param int $id ID
     * @return string
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new Rapporti model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return string|\yii\web\Response
     */
    public function actionCreate()
    {
        $model = new Rapporti();

        if ($this->request->isPost) {
            if ($model->load($this->request->post()) && $model->save()) {
                return $this->redirect(['view', 'id' => $model->id]);
            }
        } else {
            $model->loadDefaultValues();
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing Rapporti model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param int $id ID
     * @return string|\yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($this->request->isPost && $model->load($this->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing Rapporti model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $id ID
     * @return \yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Manages addresses for Rapporti models.
     * @return string|array
     */
    public function actionIndirizzi()
    {
        $rapporti = Rapporti::find()->where(['fine' => null])->all();

        if ($this->request->isAjax) {
            \Yii::$app->response->format = Response::FORMAT_JSON;
            $data = json_decode($this->request->getRawBody(), true);
            $id = $data['id'];
            $lat = $data['lat'];
            $lng = $data['lng'];

            $model = $this->findModel($id);
            $model->latitude = $lat;
            $model->longitude = $lng;

            if ($model->save()) {
                return ['success' => true];
            }
            return ['success' => false, 'errors' => $model->errors];
        }

        return $this->render('indirizzi', [
            'rapporti' => $rapporti,
        ]);
    }

    /**
     * Gets details for a specific Rapporti model via AJAX.
     * @param int $id
     * @return array
     */
    public function actionGetRapportoDetails($id)
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;
        $indirizzo = Indirizzi::find()->where(['id_rapporto' => $id,'primario' => true])->one();

        return [
            'indirizzo' => $indirizzo ? $indirizzo->indirizzo : null,
            'latitude' => $indirizzo ? $indirizzo->lat : null,
            'longitude' =>$indirizzo ? $indirizzo->long : null,
        ];
    }

    /**
     * Finds the Rapporti model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return Rapporti the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Rapporti::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
