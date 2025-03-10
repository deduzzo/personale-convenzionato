<?php

namespace app\controllers;

use app\helpers\ExcelHelper;
use app\models\Ambiti;
use app\models\Anagrafica;
use app\models\enums\FileImportMediciNAR;
use app\models\Indirizzi;
use app\models\Rapporti;
use app\models\RapportiCaratteristiche;
use app\models\RapportiTipologia;
use Carbon\Carbon;
use yii\helpers\Json;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\ContactForm;

class SiteController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        return $this->render('index');
    }

    public function actionUpload()
    {
        $errors = [];
        $path = Yii::getAlias('@app/config/data/2025-03-04.xlsx');
        $data = ExcelHelper::getExcelData($path);
        foreach ($data as $row) {
            $medico = Anagrafica::find()->where(['cf' => $row[FileImportMediciNAR::COD_FISCALE]])->one();
            if (!$medico) {
                $medico = new Anagrafica();
                $medico->cf = $row[FileImportMediciNAR::COD_FISCALE];
                $medico->nominativo = $row[FileImportMediciNAR::NOMINATIVO];
                $medico->save();
                if ($medico->hasErrors()) {
                    array_merge($medico->getErrors(), $errors);
                }
            }
            $da = Carbon::createFromImmutable($row[FileImportMediciNAR::DATA_INIZIO_RAPPORTO])->format('Y-m-d');
            $rapporto = Rapporti::find()->where([
                'cf' => $medico->cf,
                'inizio' => $da,
                'id_tipologia' => FileImportMediciNAR::getLabel($row[FileImportMediciNAR::CATEGORIA])
            ])->one();
            if (!$rapporto) {
                $rapporto = new Rapporti();
                $rapporto->cf = $medico->cf;
                $rapporto->inizio = $da;
                $rapporto->id_tipologia = FileImportMediciNAR::getLabel($row[FileImportMediciNAR::CATEGORIA]);
                $fine = $row[FileImportMediciNAR::DATA_FINE_RAPPORTO] ?
                    Carbon::createFromImmutable($row[FileImportMediciNAR::DATA_FINE_RAPPORTO])->format('Y-m-d') : null;
                $rapporto->fine = $fine;
                $ambito = Ambiti::find()->where(['descrizione' => $row[FileImportMediciNAR::AMBITO]])->one();
                if (!$ambito && $row[FileImportMediciNAR::AMBITO] !== "" && $row[FileImportMediciNAR::AMBITO] !== null) {
                    $ambito = new Ambiti();
                    $ambito->descrizione = $row[FileImportMediciNAR::AMBITO];
                    $ambito->id_tipologia_applicabile = $rapporto->id_tipologia;
                    $ambito->save();
                    if ($ambito->hasErrors()) {
                        array_merge($ambito->getErrors(), $errors);
                    }
                }
                $rapporto->id_ambito = $ambito ? $ambito->id : null;
                $rapporto->save();
                if ($rapporto->hasErrors()) {
                    array_merge($rapporto->getErrors(), $errors);
                }
            }
            RapportiCaratteristiche::deleteAll(['id_rapporto' => $rapporto->id]);
            $massimale = $row[FileImportMediciNAR::MASSIMALE];
            if ($massimale) {
                $caratteristica = new RapportiCaratteristiche();
                $caratteristica->id_rapporto = $rapporto->id;
                $caratteristica->id_caratteristica = FileImportMediciNAR::getLabel(FileImportMediciNAR::CARATTERISTICA_MASSIMALE);
                $caratteristica->valore = strval($massimale);
                $caratteristica->valido = true;
                $caratteristica->save();
                if ($caratteristica->hasErrors()) {
                    array_merge($caratteristica->getErrors(), $errors);
                }
            }
            $codReg = $row[FileImportMediciNAR::COD_REGIONALE];
            if ($codReg) {
                $caratteristica = new RapportiCaratteristiche();
                $caratteristica->id_rapporto = $rapporto->id;
                $caratteristica->id_caratteristica = FileImportMediciNAR::getLabel(FileImportMediciNAR::CARATTERISTICA_COD_REG);
                $caratteristica->valore = strval($codReg);
                $caratteristica->valido = true;
                $caratteristica->save();
                if ($caratteristica->hasErrors()) {
                    array_merge($caratteristica->getErrors(), $errors);
                }
            }

        }
    }

    /**
     * Login action.
     *
     * @return Response|string
     */
    public function actionLogin()
    {
        $this->layout = 'auth';
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        }

        $model->password = '';
        return $this->render('login', [
            'model' => $model,
        ]);
    }

    /**
     * Logout action.
     *
     * @return Response
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    /**
     * Displays contact page.
     *
     * @return Response|string
     */
    public function actionContact()
    {
        $model = new ContactForm();
        if ($model->load(Yii::$app->request->post()) && $model->contact(Yii::$app->params['adminEmail'])) {
            Yii::$app->session->setFlash('contactFormSubmitted');

            return $this->refresh();
        }
        return $this->render('contact', [
            'model' => $model,
        ]);
    }

    /**
     * Displays about page.
     *
     * @return string
     */
    public function actionAbout()
    {
        return $this->render('about');
    }

    public function actionSaveLocation() {
        if (Yii::$app->request->isAjax || Yii::$app->request->isPost) {
            $errors = [];
            $data = Yii::$app->request->post();

            $indirizzo = Indirizzi::find()->where(['id_rapporto' => $data['id_rapporto']])->one();
            if ($indirizzo) {
                $indirizzo->lat = $data['lat'];
                $indirizzo->long = $data['lng'];
                $indirizzo->save();
                if ($indirizzo->hasErrors()) {
                    array_merge($indirizzo->getErrors(), $errors);
                }
            }
        }
        if (!empty($errors)) {
            return $this->asJson(['success' => false, 'errors' => $errors]);
        }
    }


}
