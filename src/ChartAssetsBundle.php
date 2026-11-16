<?php

namespace rkdev\chart;

use yii\web\AssetBundle;

class ChartAssetsBundle extends AssetBundle
{
    // Pinned UMD build: works without bundlers. Override this bundle to self-host Chart.js.
    public $js = [
        'https://cdn.jsdelivr.net/npm/chart.js@4.6.1/dist/chart.umd.min.js',
    ];

    public $depends = [
        'yii\web\YiiAsset',
    ];
}
