<?php

namespace rkdev\chart;

use rkdev\chart\Chart;
/**
 * This is just an example.
 */
class Chartwidget extends \yii\base\Widget
{
    public $options;
	
	public function run()
    {
		
		$model = new Chart($this->options);
		
		$json = json_encode($model->getChart());
		
		$chart = <<< JS
		  function Aply() {		
			  $.ajax({
				  type: 'get',
				  url: '/',
				  data: $('#filter-form').serialize(),
				  type: 'POST',
				  dataType: 'json',
				  success: function(json) {
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
				  },
					error: function(xhr, ajaxOptions, thrownError) {
				   alert(thrownError);
				}
			  });
		  };
		  
		  $('select, input').on('change', function(){ 
			Aply();
		  });
		  Aply();
		JS;
		$this->registerJs($chart, yii\web\View::POS_READY);

		return '<div id="chart"></div>';
    }
}
