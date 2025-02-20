<?php


use app\assets\MainAsset;
use app\models\LoginForm;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var string $content
 * @var LoginForm $model
 * @var ActiveForm $form
 */

$themeMazer = MainAsset::register($this);
?>

<div class="row h-100">
    <div class="col-lg-5 col-12">
        <div id="auth-left">
            <div class="auth-logo">
                <a href="<?= Url::current() ?>">
                    <img src="<?= "{$themeMazer->baseUrl}/static/images/logo/aspm4p-logo.png" ?>" alt="Logo" />
                </a>
            </div>
            <h1 class="auth-title">Accesso</h1>
            <p class="auth-subtitle mb-5">Fai login per accedere all'applicazione</p>

            <?php $form = ActiveForm::begin() ?>
            <div class="form-group position-relative has-icon-left mb-4">
                <div class="form-control-icon">
                    <i class="bi bi-person"></i>
                </div>
                <?= $form->field($model, 'username')->textInput(['class' => "form-control form-control-xl", 'placeholder' => "Username"])->label(false) ?>
            </div>
            <div class="form-group position-relative has-icon-left mb-4">
                <div class="form-control-icon">
                    <i class="bi bi-shield-lock"></i>
                </div>
                <?= $form->field($model, 'password')->passwordInput(['class' => "form-control form-control-xl", 'placeholder' => "Password"])->label(false) ?>
            </div>
            <div class="form-check form-check-lg d-flex align-items-end">
                <?= $form->field($model, 'rememberMe')->checkbox(['class' => "form-check-input me-2", 'value' => 1, 'uncheck' => null])->label("Ricordami") ?>
            </div>
            <?= Html::submitButton('Accedi', ['class' => 'btn btn-primary btn-block btn-lg shadow-lg mt-5']) ?>
            <?php ActiveForm::end() ?>
            <div class="text-center mt-5 text-lg fs-4">
                <!--<p class="text-gray-600">
                    Don't have an account?
                    <a href="<?php /*= Url::toRoute(['signup']) */?>" class="font-bold">
                        Registrati
                    </a>.
                </p>-->
                <p>
                    <a class="font-bold" href="<?= Url::toRoute(['forgot-password']) ?>">
                        Password Dimenticata
                    </a>
                </p>
            </div>
        </div>
    </div>
    <div class="col-lg-7 d-none d-lg-block">
        <div id="auth-right">

        </div>
    </div>
</div>
