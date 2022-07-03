<?php

namespace rkdev\chart;

use rkdev\chart\Chart;
use yii\web\View;
use rkdev\chart\ChartAssetsBundle;
use yii\bootstrap4\ActiveForm;
/**
 * This is just an example.
 */
class Chartwidget extends \yii\base\Widget
{
    public $options;
			
	public function run()
    {
		
		$view = $this->getView();
		
		ChartAssetsBundle::register($view);
		
		$model = new Chart($this->options);
					
		$json = json_encode($model->getChart());
						
		$chart = <<< JS
			var json = $json;
			if (typeof json['total'] == 'undefined') { return false; }	  
			  var option = {	
				shadowSize: 0,
				colors: ['#9FD5F1', '#1065D2'],
				bars: { 
					show: true,
					fill: true,
					lineWidth: 1
				},
				grid: {
					backgroundColor: '#FFFFFF',
					hoverable: true
				},
				points: {
					show: false
				},
				xaxis: {
					show: true,
					ticks: json['xaxis']
				}
			}
			$.plot('#chart-request', [json['total'], json['poprequests']], option);
		JS;

		$this->getView()->registerJs($chart);
		
		$html = '<div id="chart" style="height: 260px; border: 1px solid;"></div>';
		$form = ActiveForm::begin(['id' => 'filter-form']);
		
		ActiveForm::end();

		return $html;
    }
}
