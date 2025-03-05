<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\Rapporti $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="rapporti-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'cf')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'inizio')->textInput() ?>

    <?= $form->field($model, 'fine')->textInput() ?>

    <?= $form->field($model, 'id_tipologia')->textInput() ?>

    <?= $form->field($model, 'id_ambito')->textInput() ?>

    <div class="form-group">
        <?= Html::submitButton('Save', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
