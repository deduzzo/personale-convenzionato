<?php
/* @var $this yii\web\View */
/* @var $geojsonString string */
/* @var $colors array */

use kartik\select2\Select2;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

$this->title = 'Mappa Circoscrizioni e Assistiti';

// CSS aggiuntivo per i loader e gli step di progresso
$css = <<<CSS
.loader-container {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 1000;
    background-color: rgba(255, 255, 255, 0.8);
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    text-align: center;
    display: none;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #3498db;
    border-radius: 50%;
    animation: spin 2s linear infinite;
    margin: 0 auto 10px auto;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.map-container {
    position: relative;
    height: 700px;
    width: 100%;
    margin-bottom: 20px;
}

.gm-style-iw {
    z-index: 1001 !important;
}

.steps-container {
    margin-bottom: 20px;
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 5px;
}

.step {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.step-checkbox {
    margin-right: 10px;
    font-size: 18px;
    color: #28a745;
}

.step-text {
    font-size: 16px;
}

.step-status {
    margin-left: auto;
    font-size: 14px;
    color: #6c757d;
}

.progress {
    height: 10px;
    margin-top: 5px;
    margin-bottom: 15px;
}

.btn-load-data {
    margin-bottom: 15px;
}

.stats-badge {
    margin-left: 10px;
    font-size: 14px;
}
CSS;

$this->registerCss($css);
?>

<div class="site-mappa">
    <h1><?= $this->title ?></h1>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="mb-0">Informazioni</h3>
            <?= Html::button('<i class="fas fa-download"></i> Carica Assistiti', [
                'class' => 'btn btn-primary btn-load-data',
                'id' => 'load-data-btn'
            ]) ?>
        </div>
        <div class="card-body">
            <p>Questa mappa visualizza le circoscrizioni di Messina. Fare clic sul pulsante "Carica Assistiti" per visualizzare i dati completi.</p>

            <!-- Container degli step di progresso -->
            <div class="steps-container" id="progress-steps">
                <h4>Stato operazioni</h4>
                <div class="step">
                    <span class="step-checkbox" id="step1-check">○</span>
                    <span class="step-text">Caricamento dati dal server</span>
                    <span class="step-status" id="step1-status">In attesa</span>
                </div>
                <div class="progress">
                    <div class="progress-bar" id="step1-progress" role="progressbar" style="width: 0%"></div>
                </div>

                <div class="step">
                    <span class="step-checkbox" id="step2-check">○</span>
                    <span class="step-text">Popolamento punti sulla mappa</span>
                    <span class="step-status" id="step2-status">In attesa</span>
                </div>
                <div class="progress">
                    <div class="progress-bar" id="step2-progress" role="progressbar" style="width: 0%"></div>
                </div>

                <div class="step">
                    <span class="step-checkbox" id="step3-check">○</span>
                    <span class="step-text">Geolocalizzazione e calcolo totali</span>
                    <span class="step-status" id="step3-status">In attesa</span>
                </div>
                <div class="progress">
                    <div class="progress-bar" id="step3-progress" role="progressbar" style="width: 0%"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="map-container">
        <!-- Loader per il caricamento dei dati -->
        <div id="data-loader" class="loader-container">
            <div class="spinner"></div>
            <p id="loader-message">Caricamento dati dal server...</p>
        </div>

        <div id="map" style="height: 100%; width: 100%;"></div>
        <!-- Aggiungi sotto alla mappa -->
        <div id="background-process-indicator" style="display:none;" class="alert alert-info text-center">
            <i class="fas fa-cogs fa-spin me-2"></i>
            <span id="background-status">Elaborazione dati in background...</span>
            <div class="progress mt-2" style="height: 5px;">
                <div id="background-progress" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
            </div>
        </div>
    </div>

    <div id="report" class="mt-4">
        <h2>Assistiti per Circoscrizione</h2>
        <div id="reportContent">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Clicca su "Carica Assistiti" per visualizzare i dati.
            </div>
        </div>
    </div>
</div>

<!-- Script necessari -->
<script src="https://maps.googleapis.com/maps/api/js?key=<?= $_ENV['GOOGLE_MAPS_API_KEY'] ?>&v=weekly&libraries=geometry,marker" defer></script>
<script src="https://unpkg.com/supercluster@7.1.5/dist/supercluster.min.js"></script>

<script>
    // Variabili globali
    let map;
    let geocoder;
    let markers = [];
    let supercluster;
    let visibleMarkers = [];
    let polygons = [];
    let rawData = [];
    let assistitiPerCircoscrizione = {
        '1': [],
        '2': [],
        '3': [],
        '4': [],
        '5': [],
        '6': [],
        'ALTRO': []
    };
    let geojsonPoints = [];
    let isDataLoaded = false;

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
            fullscreenControl: true,
            mapId: 'assistiti_map'
        });

        // Inizializza il geocoder
        geocoder = new google.maps.Geocoder();

        // Converti il GeoJSON in oggetto JavaScript e disegna i quartieri
        const geojsonData = <?= isset($geojsonString) ? $geojsonString : '{"type": "FeatureCollection", "features": []}' ?>;

        // Array di colori predefiniti se non forniti dal server
        const defaultColors = ['#FF0000', '#00FF00', '#0000FF', '#FFA500', '#800080', '#008080'];
        const colors = <?= isset($colors) ? json_encode($colors) : 'defaultColors' ?>;

        // Disegna i quartieri
        drawDistricts(geojsonData.features, colors || defaultColors);

        // Aggiungi handler per il pulsante di caricamento dati
        document.getElementById('load-data-btn').addEventListener('click', function() {
            if (!isDataLoaded) {
                startLoadingProcess();
            } else {
                alert('I dati sono già stati caricati!');
            }
        });
    }

    // Funzione per disegnare i quartieri (circoscrizioni)
    function drawDistricts(features, colors) {
        for (let i = 0; i < features.length; i++) {
            const feature = features[i];

            // Crea il poligono della circoscrizione
            const paths = feature.geometry.coordinates[0][0].map(coord => ({
                lat: coord[1],
                lng: coord[0]
            }));

            // Crea il poligono con il colore corrispondente
            const polygon = new google.maps.Polygon({
                paths: paths,
                strokeColor: colors[i % colors.length],
                strokeOpacity: 0.8,
                strokeWeight: 2,
                fillColor: colors[i % colors.length],
                fillOpacity: 0.15,
                map: map
            });

            polygons.push(polygon);

            // Calcola il centro del poligono per posizionare l'etichetta
            const bounds = new google.maps.LatLngBounds();
            paths.forEach(path => bounds.extend(path));
            const center = bounds.getCenter();

            // Crea l'etichetta con il nome della circoscrizione
            const labelContent = document.createElement("div");
            labelContent.className = "circoscrizione-label";
            labelContent.textContent = feature.properties.LAYER;
            labelContent.style.color = "#000000";
            labelContent.style.fontSize = "16px";
            labelContent.style.fontWeight = "bold";
            labelContent.style.backgroundColor = "white";
            labelContent.style.padding = "2px 5px";
            labelContent.style.borderRadius = "3px";

            new google.maps.marker.AdvancedMarkerElement({
                position: center,
                content: labelContent,
                map: map,
                zIndex: 1
            });

            // Eventi per il poligono
            polygon.addListener('mouseover', () => {
                polygon.setOptions({ fillOpacity: 0.5 });
            });

            polygon.addListener('mouseout', () => {
                polygon.setOptions({ fillOpacity: 0.15 });
            });
        }
    }

    // Funzione che avvia il processo di caricamento
    function startLoadingProcess() {
        // Disabilita il pulsante durante l'esecuzione
        const loadButton = document.getElementById('load-data-btn');
        loadButton.disabled = true;
        loadButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Caricamento...';

        // Inizializza gli step
        updateStepStatus('step1', 'In corso...', 10);

        // Step 1: Caricamento dati dal server
        fetchDataFromApi()
            .then(data => {
                // Completamento Step 1
                updateStepStatus('step1', 'Completato', 100, true);
                rawData = data;

                // Avvio Step 2: Popolamento punti
                updateStepStatus('step2', 'In corso...', 10);
                return populateMapPoints(data);
            })
            .then(() => {
                // Completamento Step 2
                updateStepStatus('step2', 'Completato', 100, true);

                // Avvio Step 3: Geolocalizzazione e calcolo
                updateStepStatus('step3', 'In corso...', 10);
                return geolocatePointsAndUpdateReport();
            })
            .then(() => {
                // Completamento Step 3
                updateStepStatus('step3', 'Completato', 100, true);

                // Riattiva il pulsante
                loadButton.disabled = false;
                loadButton.innerHTML = '<i class="fas fa-check"></i> Dati Caricati';

                // Imposta il flag che i dati sono stati caricati
                isDataLoaded = true;
            })
            .catch(error => {
                console.error("Errore durante il caricamento:", error);
                alert("Si è verificato un errore durante il caricamento: " + error.message);

                // Riattiva il pulsante in caso di errore
                loadButton.disabled = false;
                loadButton.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Riprova';
            });
    }

    // Step 1: Fetch dei dati dall'API
    async function fetchDataFromApi() {
        document.getElementById('data-loader').style.display = 'block';
        document.getElementById('loader-message').textContent = 'Caricamento dati dal server...';

        try {
            const response = await fetch('<?= Yii::$app->urlManager->createUrl(['mmg-pls/proxy-geo-data']) ?>', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`Errore nella risposta API: ${response.status}`);
            }

            const result = await response.json();
            console.log("Dati caricati:", result.data.length);

            document.getElementById('data-loader').style.display = 'none';
            return result.data;
        } catch (error) {
            document.getElementById('data-loader').style.display = 'none';
            throw error;
        }
    }

    // Step 2: Popolare i punti sulla mappa
    async function populateMapPoints(assistiti) {
        document.getElementById('data-loader').style.display = 'block';
        document.getElementById('loader-message').textContent = 'Popolamento punti sulla mappa...';

        return new Promise((resolve) => {
            setTimeout(() => {
                try {
                    // Converte i dati in formato GeoJSON per Supercluster
                    geojsonPoints = assistiti.map(assistito => {
                        if (!assistito.lat || !assistito.long) return null;

                        return {
                            type: 'Feature',
                            properties: {
                                id: assistito.id,
                                cf: assistito.cf,
                                indirizzo: assistito.geolocPrecise
                                // Non assegnare ancora la circoscrizione
                            },
                            geometry: {
                                type: 'Point',
                                coordinates: [parseFloat(assistito.long), parseFloat(assistito.lat)]
                            }
                        };
                    }).filter(point => point !== null);

                    // Inizializza supercluster con parametri ottimizzati
                    supercluster = new Supercluster({
                        radius: 60,
                        maxZoom: 16,
                        minPoints: 3,
                        log: false
                    });

                    supercluster.load(geojsonPoints);

                    // Aggiungi l'evento per aggiornare i cluster quando la mappa si sposta
                    map.addListener('idle', renderClusters);

                    // Renderizza i cluster iniziali
                    renderClusters();

                    document.getElementById('data-loader').style.display = 'none';
                    resolve();
                } catch (error) {
                    document.getElementById('data-loader').style.display = 'none';
                    console.error("Errore durante il popolamento punti:", error);
                    throw error;
                }
            }, 100); // piccolo timeout per permettere l'aggiornamento dell'UI
        });
    }

    // Step 3: Geolocalizzare i punti e aggiornare il report con elaborazione a batch
    async function geolocatePointsAndUpdateReport() {
        document.getElementById('data-loader').style.display = 'block';
        document.getElementById('loader-message').textContent = 'Geolocalizzazione punti e calcolo totali...';

        return new Promise((resolve) => {
            // Reset del conteggio assistiti per circoscrizione
            for (let circ in assistitiPerCircoscrizione) {
                assistitiPerCircoscrizione[circ] = [];
            }

            const totalPoints = rawData.length;
            let processed = 0;
            const batchSize = 1000; // Dimensione di ogni batch

            // Funzione che elabora un batch di punti
            function processBatch(startIndex) {
                // Calcola l'indice finale del batch corrente
                const endIndex = Math.min(startIndex + batchSize, totalPoints);

                // Elabora questo batch di punti
                for (let i = startIndex; i < endIndex; i++) {
                    const assistito = rawData[i];
                    if (assistito.lat && assistito.long) {
                        const circ = getCircoscrizioneByCoordinates(
                            parseFloat(assistito.lat),
                            parseFloat(assistito.long)
                        );

                        if (!assistitiPerCircoscrizione[circ]) {
                            assistitiPerCircoscrizione[circ] = [];
                        }
                        assistitiPerCircoscrizione[circ].push(assistito);
                    }
                }

                // Aggiorna il conteggio dei punti elaborati
                processed = endIndex;

                // Aggiorna la barra di progresso
                const progress = Math.floor((processed / totalPoints) * 100);
                updateStepStatus('step3', `In corso... ${processed}/${totalPoints}`, progress);

                // Aggiorna il report con i dati parziali
                if (processed % (batchSize * 5) === 0 || processed === totalPoints) {
                    updateReport();
                }

                // Modifica nella funzione processBatch
                if (processed >= totalPoints) {
                    document.getElementById('data-loader').style.display = 'none';
                    document.getElementById('background-process-indicator').style.display = 'none';
                    updateStepStatus('step3', `Completato (${totalPoints} punti)`, 100, true);
                    updateReport();
                    resolve();
                } else {
                    // Mostra l'indicatore di background
                    document.getElementById('background-process-indicator').style.display = 'block';
                    document.getElementById('background-progress').style.width = `${progress}%`;
                    document.getElementById('background-status').textContent =
                        `Elaborazione in background: ${processed}/${totalPoints} punti (${progress}%)`;

                    // Programmazione del prossimo batch con un timeout
                    setTimeout(() => {
                        processBatch(endIndex);
                    }, 0);
                }
            }

            // Avvia il primo batch
            processBatch(0);

            // Aggiorna l'interfaccia per indicare che il processo continua in background
            setTimeout(() => {
                document.getElementById('data-loader').style.display = 'none';
                document.getElementById('loader-message').textContent = 'Elaborazione in background...';
            }, 500);
        });
    }

    // Funzione per renderizzare i cluster
    function renderClusters() {
        // Rimuovi i marker esistenti
        visibleMarkers.forEach(marker => marker.map = null);
        visibleMarkers = [];

        if (!supercluster) return;

        // Ottieni i confini visibili sulla mappa
        const bounds = map.getBounds();
        if (!bounds) return;

        const bbox = [
            bounds.getSouthWest().lng(),
            bounds.getSouthWest().lat(),
            bounds.getNorthEast().lng(),
            bounds.getNorthEast().lat()
        ];

        const zoom = Math.floor(map.getZoom());
        const clusters = supercluster.getClusters(bbox, zoom);

        clusters.forEach(cluster => {
            const [longitude, latitude] = cluster.geometry.coordinates;
            const position = {lat: latitude, lng: longitude};

            if (cluster.properties.cluster) {
                // È un cluster
                const pointCount = cluster.properties.point_count;
                const clusterSize = Math.min(50, 10 + Math.log10(pointCount) * 20);

                const clusterContent = document.createElement("div");
                clusterContent.textContent = pointCount.toString();
                clusterContent.style.color = "#ffffff";
                clusterContent.style.fontSize = "14px";
                clusterContent.style.fontWeight = "bold";
                clusterContent.style.height = clusterSize + "px";
                clusterContent.style.width = clusterSize + "px";
                clusterContent.style.lineHeight = clusterSize + "px";
                clusterContent.style.textAlign = "center";
                clusterContent.style.background = "#1c73d4";
                clusterContent.style.borderRadius = "50%";
                clusterContent.style.border = "2px solid #ffffff";

                const marker = new google.maps.marker.AdvancedMarkerElement({
                    position,
                    content: clusterContent,
                    map: map,
                    zIndex: 2
                });

                // Aggiungi click event listener
                marker.addEventListener('gmp-click', () => {
                    const expansionZoom = Math.min(
                        supercluster.getClusterExpansionZoom(cluster.properties.cluster_id),
                        20
                    );
                    map.setCenter(position);
                    map.setZoom(expansionZoom);
                });

                visibleMarkers.push(marker);
            } else {
                // È un punto singolo
                const pinElement = new google.maps.marker.PinElement({
                    glyph: "•",
                    scale: 0.8,
                    background: "#FF0000"
                });

                const marker = new google.maps.marker.AdvancedMarkerElement({
                    position,
                    content: pinElement.element,
                    map: map,
                    title: cluster.properties.cf,
                    zIndex: 3
                });

                // Definisci la InfoWindow prima di aggiungerla al marker
                const contentString = `<div style="padding: 10px; min-width: 200px;">
                    <strong>[${cluster.properties.id}] ${cluster.properties.cf}</strong><br>
                    ${cluster.properties.indirizzo || 'Indirizzo non disponibile'}<br>
                </div>`;

                const infowindow = new google.maps.InfoWindow({
                    content: contentString,
                    ariaLabel: cluster.properties.cf,
                    zIndex: 10
                });

                // Correggi l'apertura della InfoWindow
                marker.addEventListener('gmp-click', () => {
                    // Chiudi tutte le finestre info aperte prima di aprirne una nuova
                    visibleMarkers.forEach(m => {
                        if (m.infoWindow) {
                            m.infoWindow.close();
                        }
                    });

                    infowindow.open({
                        anchor: marker,
                        map: map
                    });

                    // Salva riferimento alla finestra info aperta
                    marker.infoWindow = infowindow;
                });

                visibleMarkers.push(marker);
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

    // Funzione per aggiornare il report con i conteggi per circoscrizione
    function updateReport() {
        const reportDiv = document.getElementById('reportContent');
        let html = '<div class="row">';

        for (let circ in assistitiPerCircoscrizione) {
            if (assistitiPerCircoscrizione[circ].length > 0) {
                html += `
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Circoscrizione ${circ}</h5>
                        </div>
                        <div class="card-body">
                            <p>Totale assistiti: ${assistitiPerCircoscrizione[circ].length}</p>
                        </div>
                    </div>
                </div>`;
            }
        }

        html += '</div>';
        html += `<div class="alert alert-info mt-3">
            Totale punti caricati: ${geojsonPoints.length} - Punti visibili sulla mappa attualmente: ${visibleMarkers.length}
        </div>`;

        reportDiv.innerHTML = html;
    }

    // Funzione per aggiornare lo stato di uno step
    function updateStepStatus(stepId, statusText, progress, completed = false) {
        document.getElementById(`${stepId}-status`).textContent = statusText;
        document.getElementById(`${stepId}-progress`).style.width = `${progress}%`;

        if (completed) {
            document.getElementById(`${stepId}-check`).innerHTML = '✓';
            document.getElementById(`${stepId}-check`).style.color = '#28a745';
            document.getElementById(`${stepId}-progress`).classList.add('bg-success');
        }
    }

    // Inizializza la mappa al caricamento della pagina
    document.addEventListener('DOMContentLoaded', function() {
        initializeMap();

        // Aggiungi un listener per l'evento resize per aggiornare i cluster
        window.addEventListener('resize', function() {
            if (map) {
                google.maps.event.trigger(map, 'resize');
            }
        });
    });
</script>
