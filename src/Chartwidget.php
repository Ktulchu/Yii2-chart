<?php

namespace rkdev\chart;

use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\web\View;

/**
 * Renders a bar chart with Flot.
 */
class Chartwidget extends Widget
{
    public $options = [];

    public function run()
    {
        $view = $this->getView();
        ChartAssetsBundle::register($view);

        $model = new Chart($this->options);
        $json = Json::htmlEncode($model->getChart());
        $id = $this->getId() . '-chart';
        $jsId = Json::htmlEncode($id);
        $html = Html::tag('div', '', ['id' => $id, 'style' => 'height: 260px;']);

        $chart = <<<JS
(function () {
    var data = $json;
    if (!data.total) {
        return;
    }
    var options = {
        shadowSize: 0,
        colors: ['#9FD5F1', '#1065D2'],
        bars: {show: true, fill: true, lineWidth: 1},
        grid: {backgroundColor: '#FFFFFF', hoverable: true},
        points: {show: false},
        xaxis: {show: true, ticks: data.xaxis}
    };
    $.plot(document.getElementById($jsId), [data.total, data.poprequests], options);
})();
JS;

        $view->registerJs($chart, View::POS_READY, $id);

        return $html;
    }
}
