<?php

namespace app\controllers;

use app\models\Ambiti;
use app\models\enums\FileImportMediciNAR;
use app\models\Indirizzi;
use app\models\Rapporti;
use app\models\RapportiCaratteristiche;
use app\models\RapportiSearch;
use app\models\RapportiTipologia;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Yii;
use yii\helpers\Json;
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
                        'ambito' => isset($row[5]) ? $row[5] : null,
                        'circoscrizione' => null
                    ];
                }

                fclose($handle);
            } else {
                error_log('File CSV medici non trovato: ' . $csvFilePath);
            }

            // Converti l'array in JSON
            $mediciJson = Json::encode($medici, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } else {
            $mediciJson = [];
            $tipologie = RapportiTipologia::find()->all();
            $allMedici = Rapporti::find()->where(['id_tipologia' => FileImportMediciNAR::MMG_ID])
                ->andWhere(['fine' => null])
                ->andWhere(['in', 'id_tipologia', array_column($tipologie, 'id')])
                ->all();
            foreach ($allMedici as $rapporto) {
                /* @var $rapporto Rapporti */
                $codReg = RapportiCaratteristiche::find()->where(['id_rapporto' => $rapporto->id, 'id_caratteristica' => FileImportMediciNAR::COD_REG_ID])->one();
                $indirizzoPrimario = Indirizzi::find()->where(['id_rapporto' => $rapporto->id, 'primario' => true])->one();

                // Ottieni l'ambito del medico
                $ambitoObj = null;
                if ($rapporto->id_ambito) {
                    $ambitoObj = Ambiti::findOne($rapporto->id_ambito);
                }

                if ($indirizzoPrimario) {
                    $mediciJson[] = [
                        'id_rapporto' => $rapporto->id,
                        'cod_reg' => $codReg ? $codReg->valore : '',
                        'nome_cognome' => $rapporto->cf0->nominativo,
                        'indirizzo' => $indirizzoPrimario->indirizzo,
                        'lat' => $indirizzoPrimario->lat,
                        'lng' => $indirizzoPrimario->long,
                        'tipo' => RapportiTipologia::findOne($rapporto->id_tipologia)->descrizione,
                        'id_tipo' => $rapporto->id_tipologia,
                        'ambito' => $ambitoObj ? $ambitoObj->descrizione : null,
                        'id_ambito' => $rapporto->id_ambito,
                        'circoscrizione' => null
                    ];
                }
            }
            $mediciJson = Json::encode($mediciJson, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }


/*        // GeoJSON data for Messina's districts
        $capFile = Yii::getAlias('@app/config/data/cap_data.json');
        $capData = null;
        // Check if file exists
        if (file_exists($capFile)) {
            // Read the GeoJSON file
            $capData = file_get_contents($capFile);

            // Validate JSON format
            $capData = json_decode($capData);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Handle JSON error
                $geojsonString = json_encode([
                    'type' => 'FeatureCollection',
                    'features' => []
                ]);
                error_log('Error loading GeoJSON file: ' . json_last_error_msg());
            }
        }*/


        return $this->render('mappa', [
            'geojsonString' => $geojsonString,
            'mediciJson' => $mediciJson,
            'colors' => $colors,
            //'capData' => Json::encode($capData)
        ]);
    }


    /**
     * Displays map page with districts.
     *
     * @return string
     */
    public function actionMappaAssistiti()
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
        return $this->render('mappa-assistiti', [
            'apiToken' => "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6InJvYmVydG8uZGVkb21lbmljbyIsInNjb3BpIjpbImFzcDUtYW5hZ3JhZmljYSJdLCJhbWJpdG8iOiJhcGkiLCJsaXZlbGxvIjo5OSwiaWF0IjoxNzQxNzc0NDAzLCJleHAiOjE3NDE4NjA4MDN9.my97zSrwtOOIgJKxyPISskY-0Q8_uJTNqJ1PplT6tcM",
            'geojsonString' => $geojsonString,
            'colors' => $colors
        ]);
    }

    // In un controller API
    public function actionProxyGeoData()
    {
        $url = 'https://anagrafica.asp.robertodedomenico.it/api/v1/anagrafica/get-geo-data?codComuneResidenza=F158&onlyGeolocationPrecise=false';
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Authorization: Bearer ' . $_ENV["ASP_WS_TOKEN"]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        Yii::$app->response->statusCode = $httpCode;

        return json_decode($response, true);
    }


    /**
     * Esporta i dati dei medici filtrati in formato Excel.
     * @return \yii\web\Response
     */
    public function actionEsportaExcel()
    {
        \Yii::$app->response->format = Response::FORMAT_RAW;

        // Ottieni i dati inviati
        $data = json_decode(Yii::$app->request->getRawBody(), true);
        $medici = $data['medici'] ?? [];

        // Crea un nuovo oggetto PhpSpreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Intestazioni
        $sheet->setCellValue('A1', 'Codice Regionale');
        $sheet->setCellValue('B1', 'Nome e Cognome');
        $sheet->setCellValue('C1', 'Indirizzo');
        $sheet->setCellValue('D1', 'Circoscrizione');
        $sheet->setCellValue('E1', 'Ambito');
        $sheet->setCellValue('F1', 'Tipo');

        // Stile intestazioni
        $headerStyle = [
            'font' => [
                'bold' => true,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => 'CCCCCC',
                ],
            ],
        ];
        $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);

        // Popolamento dati
        $row = 2;
        foreach ($medici as $medico) {
            $sheet->setCellValue('A' . $row, $medico['cod_reg']);
            $sheet->setCellValue('B' . $row, $medico['nome_cognome']);
            $sheet->setCellValue('C' . $row, $medico['indirizzo']);
            $sheet->setCellValue('D' . $row, $medico['circoscrizione'] ?? 'N/D');
            $sheet->setCellValue('E' . $row, $medico['ambito'] ?? 'N/D');
            $sheet->setCellValue('F' . $row, $medico['tipo'] ?? 'N/D');
            $row++;
        }

        // Autosize delle colonne
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Crea il writer e imposta le intestazioni HTTP
        //$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xls');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->setOffice2003Compatibility(true);

        $fileName = 'medici_export_' . date('Y-m-d_H-i-s') . '.xlsx';

        // Assicurati che non ci siano output precedenti
        ob_clean();

        Yii::$app->response->headers->set('Content-Type', 'application/vnd.ms-excel');
        Yii::$app->response->headers->set('Content-Disposition', 'attachment;filename="' . $fileName . '"');
        Yii::$app->response->headers->set('Cache-Control', 'max-age=0');

        // Scrivi nel buffer di output
        $writer->save('php://output');

        return Yii::$app->response;
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
        $tipi = null;
        $senzaIndirizzo = null;
        $ambiti = null;
        $soloConAssistiti = true;
        if (Yii::$app->request->isAjax || Yii::$app->request->isPost) {
            $tipi = Yii::$app->request->post('tipoRapporto');
            $senzaIndirizzo = Yii::$app->request->post('senzaIndirizzo');
            $ambiti = Yii::$app->request->post('filtroAmbiti');
        }

        $rapporti = Rapporti::find()
            ->innerJoin('ambiti', 'rapporti.id_ambito = ambiti.id')//->where(['like', 'ambiti.descrizione', 'messina'])
            ->where(['fine' => null]);
        if ($soloConAssistiti)
            $rapporti->innerJoin('rapporti_caratteristiche', 'rapporti.id = rapporti_caratteristiche.id_rapporto')
                ->andWhere(['rapporti_caratteristiche.id_caratteristica' => FileImportMediciNAR::getLabel(FileImportMediciNAR::CARATTERISTICA_MASSIMALE)])
                ->andWhere(['>', 'CAST(rapporti_caratteristiche.valore AS UNSIGNED)', 0]);
        if ($tipi)
            $rapporti->andWhere(['in', 'id_tipologia', $tipi]);
        if ($ambiti)
            $rapporti->andWhere(['in', 'id_ambito', $ambiti]);
        $rapporti = $rapporti->all();
        if ($senzaIndirizzo) {

            $allRapportiIds = [];
            foreach ($rapporti as $rapporto) {
                $allRapportiIds[] = $rapporto->id;
            }
            // elimina tutti gli id con indirizzo valido, resteranno quelli senza indirizzo
            $indirizzi = Indirizzi::find()->where(['id_rapporto' => $allRapportiIds])->andWhere(['not', ['lat' => null]])->andWhere(['not', ['long' => null]])->all();
            foreach ($indirizzi as $indirizzo) {
                /* @var $indirizzo Indirizzi */
                $key = array_search($indirizzo->id_rapporto, $allRapportiIds);
                if ($key !== false) {
                    unset($allRapportiIds[$key]);
                }
            }
            //all rapporti ids includes in allRapportiIds
            $rapporti = Rapporti::find()->where(['id' => $allRapportiIds])->all();
        }

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


        return $this->render('indirizzi', [
            'rapporti' => $rapporti,
            'tipi' => $tipi,
            'senzaIndirizzo' => $senzaIndirizzo,
            'ambiti' => $ambiti,
            'geojsonString' => $geojsonString,
            'colors' => $colors
        ]);
    }

    public function actionSalvaIndirizzo()
    {
        if ($this->request->isPost) {
            \Yii::$app->response->format = Response::FORMAT_JSON;
            $data = json_decode($this->request->getRawBody(), true);
            $id = $data['id'];
            $lat = $data['lat'];
            $lng = $data['lng'];
            $indirizzo = $data['indirizzo'];
            $rapporto = Rapporti::findOne($id);
            $rapporto->aggiornaIndirizzoPrimario($indirizzo, $lat, $lng);
            return ['success' => true, 'errors' => null];
        }
    }

    public function actionFilterRapporti()
    {
        if (Yii::$app->request->isAjax || Yii::$app->request->isPost) {
            $tipi = Yii::$app->request->post('tipi');
            $senzaIndirizzo = Yii::$app->request->post('senza_indirizzo');

            // Recupera i rapporti in base ai filtri
            $rapporti = Rapporti::find()->where(['fine' => null]);
            if ($tipi) {
                $rapporti->andWhere(['in', 'id_tipologia', $tipi]);
            }
            if ($senzaIndirizzo) {
                $rapporti->joinWith('indirizzi')->andWhere(['indirizzi.id' => null]);
            }
            $rapporti = $rapporti->all();

            // Formatta i risultati
            $results = [];
            foreach ($rapporti as $rapporto) {
                $results[] = [
                    'id' => $rapporto->id,
                    'text' => $rapporto->id . ' - ' . $rapporto->cf0->nominativo
                ];
            }

            // Restituisci i risultati come JSON
            return $this->asJson(['success' => true, 'rapporti' => $results]);
        }

        return $this->asJson(['success' => false, 'message' => 'Richiesta non valida']);
    }

    /**
     * Gets details for a specific Rapporti model via AJAX.
     * @param int $id
     * @return array
     */
    public function actionGetRapportoDetails($id)
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;
        $indirizzo = Indirizzi::find()->where(['id_rapporto' => $id, 'primario' => true])->one();

        return [
            'indirizzo' => $indirizzo ? $indirizzo->indirizzo : null,
            'latitude' => $indirizzo ? $indirizzo->lat : null,
            'longitude' => $indirizzo ? $indirizzo->long : null,
        ];
    }

    public function actionBuildCapFile()
    {
        $capInziale = 98121;
        $capFinale = 98168;
        $baseQuery = "https://nominatim.openstreetmap.org/search.php?postalcode={{cap}}&format=jsonv2";
        $client = new Client([
            'verify' => false, // Disabilita la verifica SSL (solo per sviluppo)
            'headers' => [
                'User-Agent' => 'YourApp/1.0 (your@email.com)' // Richiesto da OSM
            ]
        ]);

        $results = [];

        for ($i = $capInziale; $i <= $capFinale; $i++) {
            $query = str_replace('{{cap}}', $i, $baseQuery);

            try {
                $response = $client->request('GET', $query);
                $data = json_decode($response->getBody(), true);
                if (count($data) >0)
                    $results[$i] = ["lat"=> $data[0]['lat'], "long" => $data[0]['lon']];

                // Log per debug
                Yii::info("CAP $i elaborato con successo", 'app');

                // Aggiungi un ritardo per rispettare i limiti di Nominatim (1 richiesta/secondo)
                sleep(1);
            } catch (\Exception $e) {
                Yii::error("Errore elaborazione CAP $i: " . $e->getMessage(), 'app');
                // Continua con il prossimo CAP
                continue;
            }
        }

        // Salva i risultati in un file
        $filePath = Yii::getAlias('@app/runtime/cap_data.json');
        file_put_contents($filePath, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $this->renderContent("<div class='alert alert-success'>File CAP creato: $filePath</div>");
    }


    private function CallAPI($method, $url, $data = false)
    {
        $curl = curl_init();

        switch ($method) {
            case "POST":
                curl_setopt($curl, CURLOPT_POST, 1);

                if ($data)
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                break;
            case "PUT":
                curl_setopt($curl, CURLOPT_PUT, 1);
                break;
            default:
                if ($data)
                    $url = sprintf("%s?%s", $url, http_build_query($data));
        }

        // Optional Authentication:
        /*            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                    curl_setopt($curl, CURLOPT_USERPWD, "username:password");*/

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($curl);

        curl_close($curl);

        return $result;
    }


    /**
     * Finds the Rapporti model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return Rapporti the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected
    function findModel($id)
    {
        if (($model = Rapporti::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
