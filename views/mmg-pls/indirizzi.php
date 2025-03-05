<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\View;

/* @var $this yii\web\View */
/* @var $rapporti app\models\Rapporti[] */

$this->title = 'Gestione Indirizzi';
$this->params['breadcrumbs'][] = $this->title;

// Register Google Maps API
$googleMapsKey = $_ENV['GOOGLE_MAPS_API_KEY'];
$this->registerJsFile("https://maps.googleapis.com/maps/api/js?key={$googleMapsKey}&libraries=places", [
    'position' => View::POS_HEAD
]);

// Register custom JS
$js = <<<JS
let map;
let marker;
let geocoder;

const baseUrl = "";

function initMap() {
    // Default to Italy
    const defaultLocation = { lat: 41.9028, lng: 12.4964 };
    
    map = new google.maps.Map(document.getElementById("map"), {
        zoom: 6,
        center: defaultLocation,
    });
    
    geocoder = new google.maps.Geocoder();
    marker = new google.maps.Marker({
        map: map,
        draggable: true,
        position: defaultLocation,
    });
    
    // Handle marker drag events
    marker.addListener("dragend", function(event) {
        updateCoordinates(event.latLng.lat(), event.latLng.lng());
    });
}

function updateCoordinates(lat, lng) {
    document.getElementById("latitude").value = lat;
    document.getElementById("longitude").value = lng;
}

function geocodeAddress() {
    const address = document.getElementById("address").value;
    
    geocoder.geocode({ address: address }, (results, status) => {
        if (status === "OK") {
            const location = results[0].geometry.location;
            map.setCenter(location);
            marker.setPosition(location);
            updateCoordinates(location.lat(), location.lng());
        } else {
            alert("Geocode non riuscito: " + status);
        }
    });
}

function loadRapporto() {
    const rapportoId = document.getElementById("rapporto-select").value;
    if (!rapportoId) return;
    
    // Fetch rapporto details via AJAX
    fetch(baseUrl + '/mmg-pls/get-rapporto-details?id=' + rapportoId)
        .then(response => response.json())
        .then(data => {
            document.getElementById("address").value = data.indirizzo;
            document.getElementById("latitude").value = data.latitude;
            document.getElementById("longitude").value = data.longitude;
            
            const position = {
                lat: parseFloat(data.latitude) || 41.9028,
                lng: parseFloat(data.longitude) || 12.4964
            };
            
            map.setCenter(position);
            marker.setPosition(position);
        });
}

function saveCoordinates() {
    const rapportoId = document.getElementById("rapporto-select").value;
    const lat = document.getElementById("latitude").value;
    const lng = document.getElementById("longitude").value;
    
    if (!rapportoId) {
        alert("Seleziona un rapporto");
        return;
    }
    
    fetch(baseUrl + '/mmg-pls/indirizzi', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': yii.getCsrfToken()
        },
        body: JSON.stringify({
            id: rapportoId,
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

// Initialize map when page loads
window.addEventListener('load', initMap);
JS;

$this->registerJs($js, View::POS_END);

// Register CSS
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
CSS;

$this->registerCss($css);
?>

<div class="indirizzi-index">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="controls-container">
        <div class="form-group">
            <label for="rapporto-select">Seleziona Rapporto:</label>
            <select id="rapporto-select" class="form-control" onchange="loadRapporto()">
                <option value="">-- Seleziona --</option>
                <?php foreach ($rapporti as $rapporto): ?>
                    <option value="<?= $rapporto->id ?>">
                        <?= Html::encode($rapporto->id . ' - ' . $rapporto->cf0->nominativo ) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="address">Indirizzo:</label>
            <div class="input-group">
                <input type="text" id="address" class="form-control" placeholder="Inserisci l'indirizzo">
                <div class="input-group-append">
                    <button class="btn btn-primary" onclick="geocodeAddress()">Cerca</button>
                </div>
            </div>
        </div>
    </div>

    <div id="map"></div>

    <div class="coordinates-container">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="latitude">Latitudine:</label>
                    <input type="text" id="latitude" class="form-control" readonly>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="longitude">Longitudine:</label>
                    <input type="text" id="longitude" class="form-control" readonly>
                </div>
            </div>
        </div>

        <div class="form-group">
            <button class="btn btn-success" onclick="saveCoordinates()">Salva Coordinate</button>
        </div>
    </div>
</div>
