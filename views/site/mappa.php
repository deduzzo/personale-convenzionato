<?php
/* @var $this yii\web\View */
/* @var $geojsonString string */
/* @var $mediciJson string */
/* @var $colors array */

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
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="filtroAmbiti">Ambito:</label>
                        <select id="filtroAmbiti" class="form-select" multiple>
                            <!-- Options will be populated dynamically -->
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="filtroTipo">Tipo Rapporto:</label>
                        <select id="filtroTipo" class="form-select">
                            <option value="">Tutti</option>
                            <!-- Options will be populated dynamically -->
                        </select>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <button id="applicaFiltri" class="btn btn-primary">Applica Filtri</button>
                <button id="resetFiltri" class="btn btn-secondary ms-2">Reset</button>
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
<script src="https://maps.googleapis.com/maps/api/js?key=<?= $_ENV['GOOGLE_MAPS_API_KEY'] ?>&libraries=geometry"></script>

<script>
    // Variabili globali
    let map;
    let geocoder;
    let markers = [];
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

        // Crea la mappa
        map = new google.maps.Map(document.getElementById("map"), {
            zoom: 12,
            center: messina,
            mapTypeId: 'terrain'
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
        allMedici = mediciData; // Salva tutti i medici per applicare i filtri dopo
        processMedici(mediciData);
        populateFilters(mediciData);

        // Aggiungi listener ai pulsanti di filtro
        document.getElementById('applicaFiltri').addEventListener('click', applicaFiltri);
        document.getElementById('resetFiltri').addEventListener('click', resetFiltri);
    }

    // Popola i filtri con i valori disponibili
    function populateFilters(medici) {
        const ambitiSelect = document.getElementById('filtroAmbiti');
        const tipiSelect = document.getElementById('filtroTipo');

        // Raccogli tutti i valori unici di ambito e tipo
        medici.forEach(medico => {
            if (medico.ambito) {
                ambiti.add(medico.ambito);
            }
            if (medico.tipo) {
                tipiRapporto.add(medico.tipo);
            }
        });

        // Popola il multiselect degli ambiti
        ambiti.forEach(ambito => {
            const option = document.createElement('option');
            option.value = ambito;
            option.textContent = ambito;
            ambitiSelect.appendChild(option);
        });

        // Popola il select dei tipi
        tipiRapporto.forEach(tipo => {
            const option = document.createElement('option');
            option.value = tipo;
            option.textContent = tipo;
            tipiSelect.appendChild(option);
        });
    }

    // Applica i filtri selezionati
    function applicaFiltri() {
        clearMarkers();
        resetMediciPerCircoscrizione();

        const ambitiSelezionati = Array.from(document.getElementById('filtroAmbiti').selectedOptions).map(opt => opt.value);
        const tipoSelezionato = document.getElementById('filtroTipo').value;

        const mediciFiltrati = allMedici.filter(medico => {
            let matchAmbito = true;
            let matchTipo = true;

            if (ambitiSelezionati.length > 0) {
                matchAmbito = medico.ambito && ambitiSelezionati.includes(medico.ambito);
            }

            if (tipoSelezionato) {
                matchTipo = medico.tipo === tipoSelezionato;
            }

            return matchAmbito && matchTipo;
        });

        processMedici(mediciFiltrati);
    }

    // Reset dei filtri
    function resetFiltri() {
        document.getElementById('filtroAmbiti').selectedIndex = -1;
        document.getElementById('filtroTipo').selectedIndex = 0;

        clearMarkers();
        resetMediciPerCircoscrizione();
        processMedici(allMedici);
    }

    // Pulisci tutti i marker dalla mappa
    function clearMarkers() {
        markers.forEach(marker => marker.setMap(null));
        markers = [];
    }

    // Reset del conteggio medici per circoscrizione
    function resetMediciPerCircoscrizione() {
        for (let circ in mediciPerCircoscrizione) {
            mediciPerCircoscrizione[circ] = [];
        }
    }

    // Funzione per processare i medici in modo asincrono
    async function processMedici(medici) {
        for (const medico of medici) {
            if (!medico.lat && !medico.lng) {
                await geocodeAddress(medico);
                // Piccola pausa per evitare di sovraccaricare l'API
                await new Promise(resolve => setTimeout(resolve, 100));
            }
            else
                addPinMedico(medico);
        }
        updateReport();
    }

    function addPinMedico(medico) {
        {
            // create location from lat and lng
            let location = new google.maps.LatLng(medico.lat, medico.lng);
            const marker = new google.maps.Marker({
                map: map,
                position: location,
                title: `[${medico.cod_reg}] ${medico.nome_cognome} [${medico.circoscrizione}]`
            });

            // Aggiungi info window
            const infowindow = new google.maps.InfoWindow({
                content: `<div style="padding: 10px;">
                        <strong>[${medico.cod_reg}] ${medico.nome_cognome}</strong><br>
                        ${medico.indirizzo}<br>
                        Circoscrizione: ${medico.circoscrizione}
                        ${medico.ambito ? '<br>Ambito: ' + medico.ambito : ''}
                        <br>Tipo: ${medico.tipo || 'N/D'}
                    </div>`
            });

            marker.addListener('click', () => {
                infowindow.open(map, marker);
            });

            markers.push(marker);

            // Determina la circoscrizione in base alle coordinate
            const getCircoscrizioneByCoordinates = (lat, lng) => {
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

            // Aggiorna il conteggio per circoscrizione
            const circ = getCircoscrizioneByCoordinates(medico.lat, medico.lng);
            if (!mediciPerCircoscrizione[circ]) {
                mediciPerCircoscrizione[circ] = [];
            }
            mediciPerCircoscrizione[circ].push(medico);
        }
    }

    // Funzione per geocodificare un singolo indirizzo
    function geocodeAddress(medico) {
        return new Promise((resolve) => {
            geocoder.geocode({address: medico.indirizzo}, (results, status) => {
                if (status === 'OK') {
                    // post location to action to save in db
                    $.post('<?= Yii::$app->urlManager->createUrl(['site/save-location']) ?>', {
                        id_rapporto: medico.id_rapporto,
                        cod_reg: medico.cod_reg,
                        lat: results[0].geometry.location.lat(),
                        lng: results[0].geometry.location.lng()
                    });
                    medico.lat = results[0].geometry.location.lat();
                    medico.lng = results[0].geometry.location.lng();
                    addPinMedico(medico)
                }
                resolve();
            });
        });
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
                                ${mediciPerCircoscrizione[circ].map(medico =>
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

    // Inizializza la mappa quando il documento è pronto
    document.addEventListener('DOMContentLoaded', function () {
        initializeMap();
    });
</script>

<!-- Aggiungi supporto per multiselect -->
<style>
    #filtroAmbiti option {
        padding: 5px;
    }
    #filtroAmbiti {
        height: auto;
        min-height: 80px;
    }
</style>
