<?php

namespace Ktulchu\Yii2Chart;

use yii\base\InvalidConfigException;
use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\web\View;

/**
 * Usage: Chartwidget::widget(['options' => ['modelName' => Model::class, ...]]).
 */
class Chartwidget extends Widget
{
    public $options = [];

    /** @var string bar, line, pie or doughnut. */
    public $type = 'bar';

    /** @var int Height in CSS pixels. */
    public $height = 260;

    public function run()
    {
        if (!in_array($this->type, ['bar', 'line', 'pie', 'doughnut'], true)) {
            throw new InvalidConfigException('Unsupported chart type');
        }

        $chart = new Chart($this->options);
        $data = Json::htmlEncode($chart->getChart());
        $type = Json::htmlEncode($this->type);
        $id = $this->getId() . '-chart';
        $jsId = Json::htmlEncode($id);

        ChartAssetsBundle::register($this->getView());

        $html = Html::tag('div', Html::tag('canvas', '', ['id' => $id]), [
            'style' => 'position:relative;height:' . max(1, (int) $this->height) . 'px',
        ]);

        $js = <<<JS
(function () {
    var canvas = document.getElementById($jsId);
    if (!canvas || typeof window.Chart === 'undefined') {
        return;
    }
    var data = $data;
    var type = $type;
    var colors = ['#2979c7', '#e78a30', '#32a58a', '#9a74c6', '#dd6677', '#8b9d44'];
    data.datasets.forEach(function (set, i) {
        set.borderColor = colors[i % colors.length];
        set.backgroundColor = (type === 'pie' || type === 'doughnut')
            ? data.labels.map(function (_, j) { return colors[j % colors.length]; })
            : colors[i % colors.length];
        if (type === 'line') {
            set.tension = 0.2;
            set.fill = false;
        }
    });
    var previous = window.Chart.getChart(canvas);
    if (previous) {
        previous.destroy();
    }
    new window.Chart(canvas, {
        type: type,
        data: data,
        options: {responsive: true, maintainAspectRatio: false}
    });
})();
JS;

        $this->getView()->registerJs($js, View::POS_READY, $id);
        return $html;
    }
}
