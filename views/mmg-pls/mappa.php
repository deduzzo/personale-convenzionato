<?php
/* @var $this yii\web\View */
/* @var $geojsonString string */
/* @var $mediciJson string */
/* @var $colors array */

use app\models\Ambiti;
use kartik\select2\Select2;
use yii\helpers\ArrayHelper;

$this->title = 'Mappa Circoscrizioni e Medici';
?>

<div class="site-mappa">
    <h1><?= $this->title ?></h1>

    <div class="card mb-4">
        <div class="card-header">
            <h4>Filtri</h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <div class="form-group">
                        <label for="filtroAmbiti">Ambito:</label>
                        <?= Select2::widget([
                            'id' => 'filtroAmbiti',
                            'name' => 'filtroAmbiti',
                            'data' => ArrayHelper::map(Ambiti::find()->orderBy('descrizione asc')->asArray()->all(), 'id', 'descrizione'),
                            'theme' => Select2::THEME_BOOTSTRAP,
                            'options' => [
                                'multiple' => true,
                                'placeholder' => 'Seleziona ambiti...'
                            ],
                            'pluginOptions' =>
                                [ 'allowClear' => true ],
                        ]); ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="filtroTipo">Tipo Rapporto:</label>
                        <?= Select2::widget([
                            'id' => 'filtroTipo',
                            'name' => 'filtroTipo',
                            'data' => ArrayHelper::map(\app\models\RapportiTipologia::find()->asArray()->all(), 'id', 'descrizione'),
                            'theme' => Select2::THEME_BOOTSTRAP,
                            'options' => [
                                'multiple' => true,
                                'placeholder' => 'Seleziona tipo...'
                            ],
                            'pluginOptions' => [
                                'allowClear' => true,
                                'width' => '100%'
                            ]
                        ]); ?>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <button id="applicaFiltri" class="btn btn-primary">Applica Filtri</button>
                <button id="resetFiltri" class="btn btn-secondary ms-2">Reset</button>
                <button id="esportaExcel" class="btn btn-success ms-2">
                    <i class="fas fa-file-excel"></i> Esporta in Excel
                </button>
            </div>
        </div>
    </div>

    <div id="map" style="height: 800px; width: 100%; margin-bottom: 20px;"></div>

    <div id="report" class="mt-4">
        <h2>Report Medici per Circoscrizione</h2>
        <div id="reportContent"></div>
    </div>
</div>

<!-- Carica l'API di Google Maps prima dello script -->
<script src="https://maps.googleapis.com/maps/api/js?key=<?= $_ENV['GOOGLE_MAPS_API_KEY'] ?>&v=weekly&libraries=geometry,marker" defer></script>
<script src="https://unpkg.com/@googlemaps/markerclusterer@2.5.2/dist/index.min.js"></script>
<script>
    // Variabili globali
    let map;
    let geocoder;
    let markers = [];
    let markerCluster;
    let polygons = [];
    let allMedici = [];
    let tipiRapporto = new Set();
    let ambiti = new Set();
    let mediciPerCircoscrizione = {
        '1': [],
        '2': [],
        '3': [],
        '4': [],
        '5': [],
        '6': [],
        'ALTRO': []
    };

    // Funzione di inizializzazione della mappa
    function initializeMap() {
        // Coordinate approssimative di Messina
        const messina = {lat: 38.19394, lng: 15.55256};

        // Crea la mappa con opzioni per migliorare le performance
        map = new google.maps.Map(document.getElementById("map"), {
            zoom: 12,
            center: messina,
            mapTypeId: 'terrain',
            maxZoom: 18,
            minZoom: 9,
            gestureHandling: 'greedy',
            zoomControl: true,
            mapTypeControl: true,
            scaleControl: true,
            streetViewControl: true,
            rotateControl: false,
            fullscreenControl: true
        });

        // Inizializza il geocoder
        geocoder = new google.maps.Geocoder();

        // Converti il GeoJSON in oggetto JavaScript
        const geojsonData = <?= isset($geojsonString) ? $geojsonString : '{"type": "FeatureCollection", "features": []}' ?>;
        // Array di colori predefiniti se non forniti dal server
        const defaultColors = ['#FF0000', '#00FF00', '#0000FF', '#FFA500', '#800080', '#008080'];
        const colors = <?= isset($colors) ? json_encode($colors) : 'defaultColors' ?>;

        // Per ogni feature nel GeoJSON
        geojsonData.features.forEach((feature, index) => {
            // Crea il poligono della circoscrizione
            const paths = feature.geometry.coordinates[0][0].map(coord => ({
                lat: coord[1],
                lng: coord[0]
            }));

            // Crea il poligono con il colore corrispondente
            const polygon = new google.maps.Polygon({
                paths: paths,
                strokeColor: colors[0],
                strokeOpacity: 0.8,
                strokeWeight: 2,
                fillColor: colors[index % colors.length],
                fillOpacity: 0.35,
                map: map
            });

            polygons.push(polygon);

            // Calcola il centro del poligono per posizionare l'etichetta
            const bounds = new google.maps.LatLngBounds();
            paths.forEach(path => bounds.extend(path));
            const center = bounds.getCenter();

            // Crea l'etichetta con il nome della circoscrizione
            new google.maps.Marker({
                position: center,
                map: map,
                label: {
                    text: feature.properties.LAYER,
                    color: '#000000',
                    fontSize: '16px',
                    fontWeight: 'bold'
                },
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 0
                }
            });

            // Eventi per il poligono
            polygon.addListener('mouseover', () => {
                polygon.setOptions({
                    fillOpacity: 0.6,
                    strokeWeight: 3
                });
            });

            polygon.addListener('mouseout', () => {
                polygon.setOptions({
                    fillOpacity: 0.35,
                    strokeWeight: 2
                });
            });
        });

        // Geocodifica gli indirizzi dei medici
        const mediciData = <?= $mediciJson ?>;
        allMedici = mediciData;
        processMedici(mediciData);

        // Aggiungi listener ai pulsanti di filtro
        document.getElementById('applicaFiltri').addEventListener('click', applicaFiltri);
        document.getElementById('resetFiltri').addEventListener('click', resetFiltri);
        document.getElementById('esportaExcel').addEventListener('click', esportaExcel);
/*        setTimeout(() => {
            updateCapCircoscrizioniArray(capData);
        }, 4000);*/
    }

    // Funzione per processare i medici in modo asincrono
    async function processMedici(medici) {
        // Array temporaneo per raccogliere tutti i marker
        const newMarkers = [];

        // Reset del conteggio medici per circoscrizione
        resetMediciPerCircoscrizione();

        for (const medico of medici) {
            if (!medico.lat && !medico.lng) {
                await geocodeAddress(medico);
                // Piccola pausa per evitare di sovraccaricare l'API
                await new Promise(resolve => setTimeout(resolve, 100));
            } else {
                const marker = createMarkerForMedico(medico);
                if (marker) newMarkers.push(marker);
            }
        }

        // Aggiungi tutti i marker al cluster in una sola operazione
        markers = newMarkers;
        addMarkersToCluster();
        updateReport();

    }

    function updateCapCircoscrizioniArray(capData) {
        for (let cap in capData) {
            const circ = getCircoscrizioneByCoordinates(capData[cap].lat, capData[cap].long);
            capData[cap].circoscrizione = circ;
        }
/*        // Crea un file JSON con i dati CAP aggiornati e scaricalo
                const jsonString = JSON.stringify(capData, null, 2);
                const blob = new Blob([jsonString], { type: 'application/json' });
                const url = URL.createObjectURL(blob);

                // Crea un link per scaricare il file
                const downloadLink = document.createElement('a');
                downloadLink.href = url;
                downloadLink.download = 'cap_circoscrizioni.json';
                downloadLink.textContent = 'Download CAP-Circoscrizioni JSON';
                downloadLink.className = 'btn btn-info mt-2';

                // Aggiungi il link accanto agli altri pulsanti di filtro
                document.querySelector('.card-body .mt-3').appendChild(downloadLink);*/

    }

    // Funzione per creare un marker per un medico
    function createMarkerForMedico(medico) {
        if (!medico.lat || !medico.lng) return null;

        const location = new google.maps.LatLng(medico.lat, medico.lng);
        const circ = getCircoscrizioneByCoordinates(medico.lat, medico.lng);

        const marker = new google.maps.Marker({
            position: location,
            title: `[${medico.cod_reg}] ${medico.nome_cognome} [${circ}]`
        });

        const infowindow = new google.maps.InfoWindow({
            content: `<div style="padding: 10px;">
                    <strong>[${medico.cod_reg}] ${medico.nome_cognome}</strong><br>
                    ${medico.indirizzo}<br>
                    Circoscrizione: ${circ}
                    ${medico.ambito ? '<br>Ambito: ' + medico.ambito : ''}
                    <br>Tipo: ${medico.tipo || 'N/D'}
                </div>`
        });

        marker.addListener('mouseover', () => {
            infowindow.open(map, marker);
        });

        marker.addListener('mouseout', () => {
            infowindow.close();
        });

        // Aggiorna il conteggio per circoscrizione
        if (!mediciPerCircoscrizione[circ]) {
            mediciPerCircoscrizione[circ] = [];
        }
        mediciPerCircoscrizione[circ].push(medico);

        return marker;
    }

    // Funzione per aggiungere i marker al cluster
    function addMarkersToCluster() {
        // Se esisteva già un cluster, puliscilo
        if (markerCluster) {
            markerCluster.clearMarkers();
        }

        // Crea un nuovo cluster con i marker
        markerCluster = new markerClusterer.MarkerClusterer({
            map,
            markers,
            algorithm: new markerClusterer.GridAlgorithm({
                maxDistance: 80,
                gridSize: 60
            }),
            renderer: {
                render: ({ count, position }) => {
                    return new google.maps.Marker({
                        position,
                        label: { text: String(count), color: "#ffffff" },
                        icon: {
                            path: google.maps.SymbolPath.CIRCLE,
                            scale: Math.max(12, count / 4 + 8),
                            fillColor: "#1c73d4",
                            fillOpacity: 0.9,
                            strokeWeight: 2,
                            strokeColor: "#ffffff"
                        },
                        zIndex: Number(google.maps.Marker.MAX_ZINDEX) + count
                    });
                }
            }
        });
    }

    // Funzione per determinare la circoscrizione in base alle coordinate
    function getCircoscrizioneByCoordinates(lat, lng) {
        const point = new google.maps.LatLng(lat, lng);

        for (let i = 0; i < polygons.length; i++) {
            if (google.maps.geometry.poly.containsLocation(point, polygons[i])) {
                // I poligoni sono nello stesso ordine delle circoscrizioni (1-6)
                return (i + 1).toString();
            }
        }

        // Se non è in nessuna circoscrizione
        return 'ALTRO';
    }

    // Funzione per geocodificare un singolo indirizzo
    function geocodeAddress(medico) {
        return new Promise((resolve) => {
            geocoder.geocode({address: medico.indirizzo}, (results, status) => {
                if (status === 'OK') {
                    // post location to action to save in db
                    $.post('<?= Yii::$app->urlManager->createUrl(['mmg-pls/save-location']) ?>', {
                        id_rapporto: medico.id_rapporto,
                        cod_reg: medico.cod_reg,
                        lat: results[0].geometry.location.lat(),
                        lng: results[0].geometry.location.lng()
                    });
                    medico.lat = results[0].geometry.location.lat();
                    medico.lng = results[0].geometry.location.lng();

                    const marker = createMarkerForMedico(medico);
                    if (marker) {
                        markers.push(marker);
                        // Aggiorna il cluster dopo aver aggiunto il nuovo marker
                        if (markerCluster) {
                            markerCluster.addMarker(marker);
                        }
                    }
                }
                resolve();
            });
        });
    }

    // Funzione per esportare i dati in Excel
    function esportaExcel() {
        // Raccogli i dati dai filtri applicati
        const ambitiSelezionati = $('#filtroAmbiti').val() || [];
        const tipoSelezionato = $('#filtroTipo').val() || [];

        // Prepara i dati per l'esportazione
        let mediciDaEsportare = [];
        for (let circ in mediciPerCircoscrizione) {
            if (mediciPerCircoscrizione[circ].length > 0) {
                mediciPerCircoscrizione[circ].forEach(medico => {
                    medico.circoscrizione = circ;
                    mediciDaEsportare.push(medico);
                });
            }
        }

        // Invia i dati al server per generare l'Excel
        fetch('<?= Yii::$app->urlManager->createUrl(['mmg-pls/esporta-excel']) ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content')
            },
            body: JSON.stringify({
                medici: mediciDaEsportare,
                filtri: {
                    ambiti: ambitiSelezionati,
                    tipi: tipoSelezionato
                }
            })
        })
            .then(response => response.blob())
            .then(blob => {
                // Crea un link per il download
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = 'medici_esportazione.xlsx';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
            })
            .catch(error => console.error('Errore durante l\'esportazione:', error));
    }

    // Applica i filtri selezionati
    function applicaFiltri() {
        clearMarkers();
        resetMediciPerCircoscrizione();

        const ambitiSelezionati = $('#filtroAmbiti').val() || [];
        const tipoSelezionato = $('#filtroTipo').val() || [];

        const mediciFiltrati = allMedici.filter(medico => {
            let matchAmbito = true;
            let matchTipo = true;

            if (ambitiSelezionati.length > 0) {
                matchAmbito = medico.id_ambito && ambitiSelezionati.includes(medico.id_ambito.toString());
            }

            if (tipoSelezionato && tipoSelezionato.length > 0) {
                matchTipo = medico.id_tipo && tipoSelezionato.includes(medico.id_tipo.toString());
            }

            return matchAmbito && matchTipo;
        });

        processMedici(mediciFiltrati);
    }

    // Reset dei filtri
    function resetFiltri() {
        $('#filtroAmbiti').val(null).trigger('change');
        $('#filtroTipo').val(null).trigger('change');

        clearMarkers();
        resetMediciPerCircoscrizione();
        processMedici(allMedici);
    }

    // Pulisci tutti i marker dalla mappa
    function clearMarkers() {
        if (markerCluster) {
            markerCluster.clearMarkers();
        }
        markers = [];
    }

    // Reset del conteggio medici per circoscrizione
    function resetMediciPerCircoscrizione() {
        for (let circ in mediciPerCircoscrizione) {
            mediciPerCircoscrizione[circ] = [];
        }
    }

    // Funzione per aggiornare il report
    function updateReport() {
        const reportDiv = document.getElementById('reportContent');
        let html = '<div class="row">';

        for (let circ in mediciPerCircoscrizione) {
            if (mediciPerCircoscrizione[circ].length > 0) {
                html += `
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Circoscrizione ${circ}</h3>
                        </div>
                        <div class="card-body">
                            <p>Totale medici: ${mediciPerCircoscrizione[circ].length}</p>
                            <ul>
${mediciPerCircoscrizione[circ].sort((a, b) => a.nome_cognome.localeCompare(b.nome_cognome)).map(medico =>
                    `<li>[${medico.cod_reg}] ${medico.nome_cognome} - ${medico.tipo || 'N/D'}</li>`
                ).join('')}
                            </ul>
                        </div>
                    </div>
                </div>`;
            }
        }

        html += '</div>';
        reportDiv.innerHTML = html;
    }

    // Inizializza la mappa al caricamento della pagina
    document.addEventListener('DOMContentLoaded', function() {
        initializeMap();
    });
</script>
