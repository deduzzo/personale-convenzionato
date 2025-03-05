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

    public function actionMmgPlsIndirizzi() {

    }

    /**
     * Displays map page with districts.
     *
     * @return string
     */
    public function actionMmgPlsMappa()
    {
        // Define colors for districts
        $colors = [
            '#FF0000',  // Red for district 1
            '#00FF00',  // Green for district 2
            '#0000FF',  // Blue for district 3
            '#FFA500',  // Orange for district 4
            '#800080',  // Purple for district 5
            '#008080'   // Teal for district 6
        ];

        // GeoJSON data for Messina's districts
        $geojsonFilePath = Yii::getAlias('@app/config/data/Circoscrizioni 2021.geojson');

        // Check if file exists
        if (file_exists($geojsonFilePath)) {
            // Read the GeoJSON file
            $geojsonString = file_get_contents($geojsonFilePath);

            // Validate JSON format
            $geojsonData = json_decode($geojsonString);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Handle JSON error
                $geojsonString = json_encode([
                    'type' => 'FeatureCollection',
                    'features' => []
                ]);
                error_log('Error loading GeoJSON file: ' . json_last_error_msg());
            }
        } else {
            // Fallback to empty GeoJSON if file doesn't exist
            $geojsonString = json_encode([
                'type' => 'FeatureCollection',
                'features' => []
            ]);
            error_log('GeoJSON file not found: ' . $geojsonFilePath);
        }

        $build = false;
        if ($build) {
            // Leggi il file CSV dei medici
            $csvFilePath = Yii::getAlias('@app/config/data/dati.csv');
            $medici = [];

            Indirizzi::deleteAll();

            if (file_exists($csvFilePath)) {
                $handle = fopen($csvFilePath, 'r');

                // Salta l'intestazione
                $headers = fgetcsv($handle, 0, '|');

                // Leggi tutte le righe
                while (($row = fgetcsv($handle, 0, '|')) !== false) {
                    // Ignora righe vuote
                    if (count($row) < 6) continue;

                    $rapporto = Rapporti::find()->innerJoin('rapporti_caratteristiche', 'rapporti.id = rapporti_caratteristiche.id_rapporto')
                        ->where(['rapporti_caratteristiche.id_caratteristica' => FileImportMediciNAR::getLabel(FileImportMediciNAR::CARATTERISTICA_COD_REG), 'rapporti_caratteristiche.valore' => $row[0]])->all();
                    if (!$rapporto) {
                        error_log('Rapporto non trovato con codice regionale: ' . $row[0]);
                        continue;
                    } else if (count($rapporto) > 0) {
                        if (count($rapporto) > 1) {
                            error_log('Trovati più rapporti con lo stesso codice regionale: ' . $row[0]);
                        }
                        $indirizzo = new Indirizzi();
                        $indirizzo->id_rapporto = $rapporto[0]->id;
                        $indirizzo->indirizzo = $row[2];

                        $indirizzo->save();
                    }

                    $medici[] = [
                        'id_rapporto' => $rapporto[0]->id,
                        'cod_reg' => $row[0],
                        'nome_cognome' => $row[1],
                        'indirizzo' => $row[2],
                        'recapiti' => $row[3],
                        'tipo' => $row[4],
                        'circoscrizione' => null
                    ];
                }

                fclose($handle);
            } else {
                error_log('File CSV medici non trovato: ' . $csvFilePath);
            }

            // Converti l'array in JSON
            $mediciJson = Json::encode($medici, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        else {
            $mediciJson = [];
            $allMedici = Rapporti::find()->where(['id_tipologia' => FileImportMediciNAR::MMG_ID])
            ->andWhere(['fine' => null])->all();
            foreach ($allMedici as $rapporto) {
                /* @var $rapporto Rapporti */
                $codReg = RapportiCaratteristiche::find()->where(['id_rapporto' => $rapporto->id, 'id_caratteristica' => FileImportMediciNAR::COD_REG_ID])->one();
                $indirizzoPrimario = Indirizzi::find()->where(['id_rapporto' => $rapporto->id, 'primario' => true])->one();
                if ($indirizzoPrimario)
                    $mediciJson[] = [
                        'id_rapporto' => $rapporto->id,
                        'cod_reg' => $codReg->valore,
                        'nome_cognome' => $rapporto->cf0->nominativo,
                        'indirizzo' => $indirizzoPrimario->indirizzo,
                        'lat' => $indirizzoPrimario->lat,
                        'lng' => $indirizzoPrimario->long,
                        'tipo' => FileImportMediciNAR::getLabel($rapporto->id_tipologia),
                        'circoscrizione' => null
                    ];
            }
            $mediciJson = Json::encode($mediciJson, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        return $this->render('mappa', [
            'geojsonString' => $geojsonString,
            'mediciJson' => $mediciJson,
            'colors' => $colors
        ]);
    }
}
