<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\Rapporti $model */

$this->title = 'Update Rapporti: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Rapportis', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="rapporti-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
