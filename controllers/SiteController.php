<?php

namespace app\controllers;

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

    /**
     * Displays map page with districts.
     *
     * @return string
     */
    public function actionMappa()
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

    // Leggi il file CSV dei medici
        $csvFilePath = Yii::getAlias('@app/config/data/dati.csv');
        $medici = [];

        if (file_exists($csvFilePath)) {
            $handle = fopen($csvFilePath, 'r');

            // Salta l'intestazione
            $headers = fgetcsv($handle, 0, '|');

            // Leggi tutte le righe
            while (($row = fgetcsv($handle, 0, '|')) !== false) {
                // Ignora righe vuote
                if (count($row) < 6) continue;

                // Controlla se l'ambito contiene un numero (circoscrizione)
                $circoscrizione = preg_match('/^\d+$/', $row[5]) ? $row[5] : '';

                // Se non c'è l'informazione sulla circoscrizione, prova a estrarla dall'indirizzo
                // o assegna Nord/Sud ad una circoscrizione
                if (empty($circoscrizione)) {
                    if ($row[5] == 'Nord') {
                        $circoscrizione = '1'; // Esempio: Nord = Circoscrizione 1
                    } elseif ($row[5] == 'Sud') {
                        $circoscrizione = '2'; // Esempio: Sud = Circoscrizione 2
                    }
                }

                $medici[] = [
                    'cod_reg' => $row[0],
                    'nome_cognome' => $row[1],
                    'indirizzo' => $row[2],
                    'recapiti' => $row[3],
                    'tipo' => $row[4],
                    'circoscrizione' => $circoscrizione
                ];
            }

            fclose($handle);
        } else {
            error_log('File CSV medici non trovato: ' . $csvFilePath);
        }

        // Converti l'array in JSON
        $mediciJson = Json::encode($medici, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        return $this->render('mappa', [
            'geojsonString' => $geojsonString,
            'mediciJson' => $mediciJson,
            'colors' => $colors
        ]);
    }
}
