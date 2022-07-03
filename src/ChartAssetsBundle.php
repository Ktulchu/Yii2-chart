<?php

namespace rkdev\chart;

use yii\web\AssetBundle;

class ChartAssetsBundle extends AssetBundle
{

    public $sourcePath = '@vendor/ktulchu/yii2-chart/assets/';

    public $js = [
		'flot/jquery.flot.js',
		'flot/jquery.flot.resize.min.js',
    ];
	
	public $depends = [
		'yii\web\YiiAsset',
	];
	
}
