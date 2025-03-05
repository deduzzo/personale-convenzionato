<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\Rapporti $model */

$this->title = 'Create Rapporti';
$this->params['breadcrumbs'][] = ['label' => 'Rapportis', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="rapporti-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
