<?php

namespace app\assets;

use yii\web\AssetBundle;
use yii\web\JqueryAsset;
use yii\web\YiiAsset;

class MainAsset extends AssetBundle
{
	public $sourcePath = __DIR__ . '/';
	public $baseUrl = '@web';

	public $js = [
		'static/js/components/dark.js',
        'extensions/perfect-scrollbar/perfect-scrollbar.min.js',
        'compiled/js/app.js',
        'extensions/apexcharts/apexcharts.min.js',
        'static/js/pages/dashboard.js',
	];

	public $css = [
        'compiled/css/app.css',
        'compiled/css/app-dark.css',
        'compiled/css/iconly.css',
	];

	public $publishOptions = [];

	public $depends = [
		YiiAsset::class,
		JqueryAsset::class,
	];
}
