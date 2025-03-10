<?php

use app\models\Ambiti;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\View;
use kartik\select2\Select2;
use yii\helpers\ArrayHelper;

/* @var $this yii\web\View */
/* @var $rapporti app\models\Rapporti[] */
/* @var $tipi String[] */
/* @var $senzaIndirizzo bool */
/* @var $ambiti String[] */


$this->title = 'Gestione Indirizzi';
$this->params['breadcrumbs'][] = $this->title;

// Register Google Maps API
$googleMapsKey = $_ENV['GOOGLE_MAPS_API_KEY'] ?? '';
// Verifica che la chiave API sia presente
if (empty($googleMapsKey)) {
    echo '<div class="alert alert-danger">Chiave API di Google Maps non configurata. Contattare l\'amministratore.</div>';
}

// Aggiungiamo un callback esplicito per l'inizializzazione
$callbackJs = "
    function gm_authFailure() {
        console.error('Google Maps authentication failed');
        document.getElementById('map').innerHTML = '<div class=\"alert alert-danger\">Errore di autenticazione con Google Maps API. La chiave API potrebbe non essere valida.</div>';
    }
";
$this->registerJs($callbackJs, View::POS_HEAD);

// Carica lo script del loader di Google Maps
$this->registerJsFile("https://unpkg.com/@googlemaps/js-api-loader@1.16.8/dist/index.min.js", [
    'position' => View::POS_HEAD
]);

// Register custom JS
$js = <<<JS
// Variabili globali
let map;
let marker;
let geocoder;
let placesService;
let sessionToken;
let addressTimer;
let selectedPlace = null;
let selectedPlaceDetails = null;

// Flag per abilitare/disabilitare i suggerimenti dell'autocomplete
const ENABLE_AUTOCOMPLETE_SUGGESTIONS = false; // Impostato a false per disabilitare i suggerimenti

const baseUrl = "";

// Inizializzazione con @googlemaps/js-api-loader
document.addEventListener('DOMContentLoaded', function() {

    // Importante: dobbiamo creare una nuova istanza del Loader
    const loader = new google.maps.plugins.loader.Loader({
        apiKey: "{$googleMapsKey}",
        version: "weekly",
        libraries: ["places", "marker"],
        language: "it",
        region: "IT"
    });
    
    // Carica l'API di Google Maps usando il loader
    loader.load()
        .then(() => {
            console.log("Google Maps API caricata con successo");
            initMap();
        })
        .catch(err => {
            console.error("Errore nel caricamento dell'API di Google Maps:", err);
            document.getElementById("map").innerHTML = 
                '<div class="alert alert-danger">Impossibile caricare Google Maps. Verificare la connessione internet e la chiave API.</div>';
        });
});

function initMap() {
    // Default to Messina - coordinate del centro di Messina
    const defaultLocation = { lat: 38.1938, lng: 15.5540 };
    
    // Inizializza la mappa
    map = new google.maps.Map(document.getElementById("map"), {
        zoom: 12,
        center: defaultLocation,
        mapTypeControl: true,
        streetViewControl: true,
        fullscreenControl: true
    });
    
    // Inizializza il geocoder
    geocoder = new google.maps.Geocoder();
    
    // Inizializza il service per le informazioni sui luoghi
    placesService = new google.maps.places.PlacesService(map);
    
    // Inizializza il marker
    marker = new google.maps.Marker({
        map: map,
        draggable: true,
        position: defaultLocation,
        animation: google.maps.Animation.DROP
    });
    
    // Gestisci il trascinamento del marker
    marker.addListener("dragend", function(event) {
        const position = marker.getPosition();
        updateCoordinates(position.lat(), position.lng());
    });
    
    // Setup address input with autocomplete widget
    setupPlaceAutocomplete();
    
    // Inizializza Select2
    $("#rapporto-select").on("change", function() {
        loadRapporto();
    });
}

// Aggiungi questa funzione al tuo JavaScript esistente
function updateSaveButtonState() {
    console.log("ciao")
    const rapportoId = $("#rapporto-select").val();
    const lat = document.getElementById("latitude").value;
    const lng = document.getElementById("longitude").value;
    
    const saveButton = document.getElementById("save-coordinates-btn");
    
    // Disabilita il pulsante se manca il rapporto o le coordinate
    if (!rapportoId || !lat || !lng) {
        saveButton.disabled = true;
        saveButton.classList.add('disabled');
    } else {
        saveButton.disabled = false;
        saveButton.classList.remove('disabled');
    }
}

// Modifica queste funzioni esistenti per chiamare updateSaveButtonState
function updateCoordinates(lat, lng) {
    document.getElementById("latitude").value = lat;
    document.getElementById("longitude").value = lng;
    updateSaveButtonState(); // Aggiunto qui
}

function loadRapporto() {
    const rapportoId = $("#rapporto-select").val();
    if (!rapportoId) {
        updateSaveButtonState(); // Aggiunto qui
        return;
    }

    // Fetch rapporto details via AJAX
    fetch(baseUrl + '/mmg-pls/get-rapporto-details?id=' + rapportoId)
        .then(response => response.json())
        .then(data => {
            document.getElementById("address").value = data.indirizzo;
            document.getElementById("latitude").value = data.latitude;
            document.getElementById("longitude").value = data.longitude;

            const position = {
                lat: parseFloat(data.latitude) || 38.1938,
                lng: parseFloat(data.longitude) || 15.5540
            };

            map.setCenter(position);
            marker.setPosition(position);
            
            updateSaveButtonState(); // Aggiunto qui
        });
}

// Aggiungi una chiamata a updateSaveButtonState nell'inizializzazione
document.addEventListener('DOMContentLoaded', function() {
    // ... (codice esistente)
    
    // Inizializzazione del pulsante dopo il caricamento della pagina
    window.setTimeout(function() {
        updateSaveButtonState();
    }, 1000); // Attendi un secondo per essere sicuri che tutto sia caricato
});
    
function setupPlaceAutocomplete() {
    // Retrieve the input element
    const input = document.getElementById("address");
    
    // Se i suggerimenti sono disabilitati, configura solo l'input senza autocomplete
    if (!ENABLE_AUTOCOMPLETE_SUGGESTIONS) {
        // Disabilita l'autocomplete del browser
        input.setAttribute("autocomplete", "off");
        input.placeholder = "Inserisci l'indirizzo";
        
        // Nascondi qualsiasi container di autocomplete che potrebbe apparire
        const style = document.createElement('style');
        style.textContent = '.pac-container { display: none !important; }';
        document.head.appendChild(style);
        
        return; // Esci dalla funzione senza creare l'oggetto Autocomplete
    }
    
    // Se arriviamo qui, i suggerimenti sono abilitati
    // Create the autocomplete object
    const autocomplete = new google.maps.places.Autocomplete(input, {
        fields: ["formatted_address", "geometry", "name", "address_components"],
        strictBounds: false,
        types: ["address"],
        componentRestrictions: { country: "it" }
    });
    
    // Set autocomplete bounds to current map viewport
    autocomplete.bindTo("bounds", map);
    
    // Configure autocomplete widget
    input.placeholder = "Inizia a digitare un indirizzo (min 5 caratteri)";
    
    // Handle place selection
    autocomplete.addListener("place_changed", () => {
        // Get the selected place details
        const place = autocomplete.getPlace();
        
        // Clear the marker animation if it's still running
        marker.setAnimation(null);
        
        // Verify we got a valid place with geometry
        if (!place.geometry || !place.geometry.location) {
            // User entered the name of a place that was not suggested
            input.placeholder = "Inserisci un indirizzo valido";
            return;
        }
        
        // Store the selected place for future reference
        selectedPlace = place;
        
        // Update the address field with the formatted address
        input.value = place.formatted_address || '';
        
        // If the place has a geometry, update map and marker
        const location = place.geometry.location;
        
        // Update map center and zoom based on the place's viewport or location
        if (place.geometry.viewport) {
            map.fitBounds(place.geometry.viewport);
        } else {
            map.setCenter(location);
            map.setZoom(17);
        }
        
        // Set the marker position to the selected place
        marker.setPosition(location);
        marker.setAnimation(google.maps.Animation.DROP);
        
        // Update the coordinate inputs
        updateCoordinates(location.lat(), location.lng());
    });
    
    // Ensure minimum character count for better performance
    input.addEventListener('input', function() {
        // Only enable autocomplete when input has at least 5 characters
        if (this.value.length < 5) {
            autocomplete.set('types', []); // Disable suggestions
        } else {
            autocomplete.set('types', ['address']); // Enable suggestions
        }
    });
}

// Inserisce il pin nella posizione dell'indirizzo corrente
function insertPin() {
    const address = document.getElementById("address").value;
    if (!address || address.length < 5) {
        alert("Inserisci un indirizzo valido di almeno 5 caratteri");
        return;
    }
    
    // Se abbiamo già un luogo selezionato, usiamo quello
    if (selectedPlace && selectedPlace.geometry && selectedPlace.geometry.location) {
        const location = selectedPlace.geometry.location;
        map.setCenter(location);
        map.setZoom(17);
        marker.setPosition(location);
        marker.setAnimation(google.maps.Animation.DROP); // Animazione per il pin
        updateCoordinates(location.lat(), location.lng());
        return;
    }
    
    // Altrimenti, geocodifichiamo l'indirizzo
    try {
        // Mostra un indicatore di caricamento
        const addressInput = document.getElementById("address");
        addressInput.classList.add('loading');
        
        // Configurazione per la geocodifica
        const request = {
            address: address,
            region: 'it',
            componentRestrictions: { country: 'it' }
        };
        
        geocoder.geocode(request, (results, status) => {
            // Rimuovi l'indicatore di caricamento
            addressInput.classList.remove('loading');
            
            if (status === "OK" && results && results.length > 0) {
                const result = results[0];
                const location = result.geometry.location;
                
                // Memorizza il luogo selezionato
                selectedPlace = result;
                
                // Aggiorna la mappa e il marker
                map.setCenter(location);
                
                // Imposta lo zoom in base alla precisione del risultato
                const locationType = result.geometry.location_type;
                if (locationType === 'ROOFTOP' || locationType === 'RANGE_INTERPOLATED') {
                    map.setZoom(17);
                } else if (locationType === 'GEOMETRIC_CENTER') {
                    map.setZoom(15);
                } else {
                    map.setZoom(14);
                }
                
                // Aggiungi il marker con animazione
                marker.setPosition(location);
                marker.setAnimation(google.maps.Animation.DROP);
                
                // Aggiorna le coordinate nei campi
                updateCoordinates(location.lat(), location.lng());
                
                // Aggiorna l'indirizzo con il formato completo
                addressInput.value = result.formatted_address;
                updateSaveButtonState(); // Aggiunto qui
            } else {
                // Nessun risultato trovato
                alert("Indirizzo non trovato. Prova ad essere più specifico.");
                console.log("Nessun risultato trovato per: " + address);
            }
        });
    } catch (e) {
        console.error("Errore durante l'inserimento del pin:", e);
        alert("Si è verificato un errore. Riprova più tardi.");
    }
}


function loadRapporto() {
    const rapportoId = $("#rapporto-select").val();
    if (!rapportoId) return;
    
    // Fetch rapporto details via AJAX
    fetch(baseUrl + '/mmg-pls/get-rapporto-details?id=' + rapportoId)
        .then(response => response.json())
        .then(data => {
            document.getElementById("address").value = data.indirizzo;
            document.getElementById("latitude").value = data.latitude;
            document.getElementById("longitude").value = data.longitude;
            
            const position = {
                lat: parseFloat(data.latitude) || 38.1938,
                lng: parseFloat(data.longitude) || 15.5540
            };
            
            map.setCenter(position);
            marker.setPosition(position);
        });
}

function saveCoordinates() {
    const rapportoId = $("#rapporto-select").val();
    const lat = document.getElementById("latitude").value;
    const lng = document.getElementById("longitude").value;
    const indirizzo = document.getElementById("address").value;
    
    if (!rapportoId) {
        alert("Seleziona un rapporto");
        return;
    }
    
    fetch(baseUrl + '/mmg-pls/salva-indirizzo', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': yii.getCsrfToken()
        },
        body: JSON.stringify({
            id: rapportoId,
            indirizzo: indirizzo,
            lat: lat,
            lng: lng
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert("Coordinate salvate con successo");
        } else {
            alert("Errore durante il salvataggio");
        }
    });
}

// Non è più necessario inizializzare qui perché initMap è usato come callback
// nella chiamata all'API di Google Maps

JS;

$this->registerJs($js, View::POS_END);

// Modifica il layout e gli stili
$css = <<<CSS
#map {
    height: 400px;
    width: 100%;
    margin: 20px 0;
}

.controls-container {
    margin: 20px 0;
}

.coordinates-container {
    margin: 20px 0;
}

/* Nascondi il container dell'autocomplete quando i suggerimenti sono disabilitati */
.pac-container {
    display: none !important;
}

.address-input-wrapper {
    position: relative;
}

/* Stile per l'indicatore di caricamento */
input.loading {
    background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 38 38"><defs><linearGradient id="a" x1="8.042%" y1="0%" x2="65.682%" y2="23.865%"><stop stop-color="%23007bff" stop-opacity="0" offset="0%"/><stop stop-color="%23007bff" stop-opacity=".631" offset="63.146%"/><stop stop-color="%23007bff" offset="100%"/></linearGradient></defs><g fill="none" fill-rule="evenodd"><g transform="translate(1 1)"><path d="M36 18c0-9.94-8.06-18-18-18" id="Oval-2" stroke="url(%23a)" stroke-width="2"><animateTransform attributeName="transform" type="rotate" from="0 18 18" to="360 18 18" dur="0.9s" repeatCount="indefinite" /></path><circle fill="%23fff" cx="36" cy="18" r="1"><animateTransform attributeName="transform" type="rotate" from="0 18 18" to="360 18 18" dur="0.9s" repeatCount="indefinite" /></circle></g></g></svg>');
    background-repeat: no-repeat;
    background-position: 98% center;
    background-size: 20px 20px;
}

/* Stile per il pulsante disabilitato */
button.disabled {
    opacity: 0.65;
    cursor: not-allowed;
}
CSS;

$this->registerCss($css);
?>

<div class="indirizzi-index">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php if (empty($googleMapsKey)): ?>
        <div class="alert alert-warning">
            <strong>Attenzione:</strong> La chiave API di Google Maps non è configurata. Alcune funzionalità potrebbero
            non funzionare correttamente.
        </div>
    <?php endif; ?>

    <div class="controls-container">
        <?= Html::beginForm() ?>
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label for="filtroAmbiti">Ambito:</label>
                    <?= Select2::widget([
                        'name' => 'filtroAmbiti',
                        'value' => $ambiti,
                        'data' => ArrayHelper::map(Ambiti::find()->orderBy('descrizione asc')->asArray()->all(), 'id', 'descrizione'),
                        'theme' => Select2::THEME_BOOTSTRAP,
                        'options' => [
                            'multiple' => true,
                            'placeholder' => 'Seleziona ambiti...',
                        ],
                        'pluginOptions' => [
                            'allowClear' => true,
                            'width' => '100%',
                            'minimumResultsForSearch' => 1,
                            'minimumInputLength' => 0
                        ]
                    ]); ?>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="tipo-rapporto">Filtra per tipo rapporto:</label>
                    <?= Select2::widget([
                        'id' => 'tipo-rapporto',
                        'name' => 'tipoRapporto',
                        'value' => $tipi,
                        'data' => ArrayHelper::map(\app\models\RapportiTipologia::find()->all(), 'id', 'descrizione'),
                        'theme' => Select2::THEME_BOOTSTRAP,
                        'options' => [
                            'placeholder' => '-- filtra per tipo rapporto --',
                            'multiple' => true,
                        ],
                        'pluginOptions' => [
                            'allowClear' => true,
                            'width' => '100%',
                        ],
                    ]); ?>
                </div>
            </div>
            <div class="col-md-3 d-flex align-items-center">
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="senza-indirizzo" name="senzaIndirizzo" <?= $senzaIndirizzo ? 'checked' : '' ?>>
                        <label class="form-check-label" for="solo-senza-indirizzo">
                            Mostra solo medici senza indirizzo
                        </label>
                    </div>
                </div>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <div class="form-group">
                    <button id="filter-btn" class="btn btn-primary w-100" type="submit">
                        <i class="fas fa-filter"></i> Filtra
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?= Html::endForm() ?>
    <div class="row">
        <div class="col-md-4">
            <div class="form-group">
                <label for="rapporto-select">Seleziona Rapporto:</label>
                <?= Select2::widget([
                    'id' => 'rapporto-select',
                    'name' => 'rapporto-select',
                    'data' => ArrayHelper::map($rapporti, 'id', function ($model) {
                        $codReg = $model->getCodRegionaleSeEsiste();
                        return $model->id . ' - ' . $model->cf0->nominativo . ($codReg ? (" [".$codReg."]") : '[no.cod.reg.]');
                    }),
                    'options' => [
                        'placeholder' => '-- Seleziona un rapporto --',
                    ],
                    'pluginOptions' => [
                        'allowClear' => true,
                        'width' => '100%',
                    ],
                ]); ?>
            </div>
        </div>
        <div class="col-md-8">
            <div class="form-group">
                <label for="address">Indirizzo:</label>
                <div class="address-input-wrapper">
                    <div class="input-group">
                        <input type="text" id="address" class="form-control" placeholder="Inserisci l'indirizzo"
                               autocomplete="off">
                        <div class="input-group-append">
                            <button class="btn btn-primary" onclick="insertPin()">Inserisci</button>
                        </div>
                    </div>
                </div>
                <small class="form-text text-muted">Inserisci l'indirizzo completo e usa il pulsante "Inserisci" per
                    posizionare il pin sulla mappa.</small>
            </div>
        </div>
    </div>

    <div id="map"></div>

    <div class="coordinates-container">
        <div class="row">
            <div class="col-md-5">
                <div class="form-group">
                    <label for="latitude">Latitudine:</label>
                    <input type="text" id="latitude" class="form-control" readonly>
                </div>
            </div>
            <div class="col-md-5">
                <div class="form-group">
                    <label for="longitude">Longitudine:</label>
                    <input type="text" id="longitude" class="form-control" readonly>
                </div>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <div class="form-group">
                    <button id="save-coordinates-btn" class="btn btn-success" onclick="saveCoordinates()">Salva
                        Coordinate
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
