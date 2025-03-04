<?php
/* @var $this yii\web\View */
/* @var $geojsonString string */
/* @var $mediciJson string */
/* @var $colors array */

$this->title = 'Mappa Circoscrizioni e Medici';
?>

<div class="site-mappa">
    <h1><?= $this->title ?></h1>
    
    <div id="map" style="height: 800px; width: 100%; margin-bottom: 20px;"></div>

    <div id="report" class="mt-4">
        <h2>Report Medici per Circoscrizione</h2>
        <div id="reportContent"></div>
    </div>
</div>

<!-- Carica l'API di Google Maps prima dello script -->
<script src="https://maps.googleapis.com/maps/api/js?key=<?= Yii::$app->params['googleMapsApiKey'] ?>"></script>

<script>
// Variabili globali
let map;
let geocoder;
let markers = [];
let polygons = [];
let mediciPerCircoscrizione = {
    '1': [],
    '2': [],
    '3': [],
    '4': [],
    '5': [],
    '6': []
};

// Funzione di inizializzazione della mappa
function initializeMap() {
    // Coordinate approssimative di Messina
    const messina = { lat: 38.19394, lng: 15.55256 };
    
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
    processMedici(mediciData);
}

// Funzione per processare i medici in modo asincrono
async function processMedici(medici) {
    for (const medico of medici) {
        await geocodeAddress(medico);
        // Piccola pausa per evitare di sovraccaricare l'API
        await new Promise(resolve => setTimeout(resolve, 100));
    }
}

// Funzione per geocodificare un singolo indirizzo
function geocodeAddress(medico) {
    return new Promise((resolve) => {
        geocoder.geocode({ address: medico.indirizzo }, (results, status) => {
            if (status === 'OK') {
                const marker = new google.maps.Marker({
                    map: map,
                    position: results[0].geometry.location,
                    title: `[${medico.cod_reg}] ${medico.nome_cognome} [${medico.circoscrizione}]`
                });

                // Aggiungi info window
                const infowindow = new google.maps.InfoWindow({
                    content: `<div style="padding: 10px;">
                        <strong>[${medico.cod_reg}] ${medico.nome_cognome}</strong><br>
                        ${medico.indirizzo}<br>
                        Circoscrizione: ${medico.circoscrizione}
                    </div>`
                });

                marker.addListener('click', () => {
                    infowindow.open(map, marker);
                });

                markers.push(marker);

                // Aggiorna il conteggio per circoscrizione
                if (medico.circoscrizione) {
                    const circ = medico.circoscrizione.toString();
                    if (!mediciPerCircoscrizione[circ]) {
                        mediciPerCircoscrizione[circ] = [];
                    }
                    mediciPerCircoscrizione[circ].push(medico);
                    updateReport();
                }
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
                                    `<li>[${medico.cod_reg}] ${medico.nome_cognome}</li>`
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
document.addEventListener('DOMContentLoaded', function() {
    initializeMap();
});
</script>

<!-- Aggiungi Bootstrap per il layout del report -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">