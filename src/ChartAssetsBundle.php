<?php

namespace rkdev\chart;

use yii\web\AssetBundle;

class ChartAssetsBundle extends AssetBundle
{

    public $sourcePath = '@vendor/rkdev/yii2-chart/assets';

    public $js = [
		'flot/jquery.flot.js',
		'flot/jquery.flot.resize.min.js',
    ];
}