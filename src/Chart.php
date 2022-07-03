<?php

namespace rkdev\chart;

use yii\base\Component;
use yii\base\InvalidConfigException;

class Chart extends Component
{
	/**
     * Start range date time
     * @var intenger
     */
	public $start;
	
	/**
     * end range date time
     * @var intenger
     */
	public $end;
	
	/**
     * end range
     * @var string week : month : year
     */
	public $range = 'week';
	
	/**
     * model class for working
     * @var string
     */
	public $modelName;
	
	/**
     * model attribute 
     * @var string
     */
	public $nameAttribute;
	
	/**
     * model attribute date time for range
     * @var string
     */
	public $created_at = 'created_at';
	
	 /** Init function
	 * Set day by rainge interval firsr day and last day by interval
	 */
	public function init()
	{	
		
		if($this->start === null){
			if(date('w') == 0) $this->start = date('U', strtotime(date('U'). " - 6 day"));
				else $this->start = date('U') - ((date('w') -1) * 86400);
		} else {
			if(!is_numeric($this->start)) $this->start = date('U', strtotime($this->start));
			if(date('w', $this->start) == 0) $this->start = strtotime($this->start . " - 6 day");
				else $this->start = $this->start - ((date('w', $this->start) -1) * 86400);
		}
			
		if($this->end === null){
			if(date('w', $this->start) == 0) $this->end = strtotime( $this->start . " + 6 day");
				else $this->end = $this->start + (86400 * 7);	
		} 
		
		if (!$this->modelName) {
            throw new InvalidConfigException('You must specify a class for the working model');
        }
	}
	
	/**
     * @inheritdoc
	 * Get Array Records by dates
	 * return array
     */
	private function getData()
	{
		if($this->range != 'year') $selsect = ['FROM_UNIXTIME('. $this->created_at .', "%Y-%m-%d") AS pop_date'];
			else $selsect = ['FROM_UNIXTIME('. $this->created_at .', "%Y-%m") AS pop_date'];
		
		$modelName = $this->modelName;
		
		$dates = $modelName::find()
		    ->select($selsect)
			->where( $this->created_at .' >= :datestart', [':datestart' => $this->start])
			->andWhere( $this->created_at .' <= :dateend', [':dateend' => $this->end])
			->distinct(true)
			->indexBy('pop_date')
			->asArray()
			->all();

		if($dates)	
		{		
			foreach($dates as $key => $date)
			{
				if($this->range != 'year')
				{
					$datestart = date('U', strtotime($key .' 00:00:00'));
					$datend = date('U', strtotime( $key .' 23:59:59'));
				}
				else 
				{
					$datestart = date('U', strtotime($key .'-01 00:00:00'));
					$parts = explode('-', $key);
					$countdays = cal_days_in_month(CAL_GREGORIAN, $parts[1], $parts[0]); 
					$datend = date('U', strtotime( $key .'-'. $countdays .' 23:59:59'));
				}
				
				$requwest = $modelName::find()
					->where( $this->created_at .' >= :datestart', [':datestart' => $datestart])
					->andwhere( $this->created_at .' <= :datend', [':datend' => $datend])
					->count();
					
				
				
				if($this->nameAttribute)
				{
					$rangeArray = $modelName::find()
						->select([$this->nameAttribute, 'COUNT('. $this->nameAttribute .') AS pop_cout'])
						->where( $this->created_at .' >= :datestart', [':datestart' => $datestart])
						->andwhere( $this->created_at .' <= :datend', [':datend' => $datend])
						->groupBy($this->nameAttribute)
						->asArray()
						->indexBy('pop_cout')
						->all();
				}
				
				$total[$key] = [
					'total' => $requwest,
					'total_poprequests' => ($this->nameAttribute) ? array_sum(array_keys($pop_cout)) : 0,	
				];
			}

			return $total;
		}
		$total[date('d.m.Y')] = [
			'total' => 0,
			'total_poprequests' =>0,	
		];
		
		return $total;
	}

	/**
     * @inheritdoc
	 * set Chart
	 * return array
     */
	public function getChart()
	{
		$data = $this->getData();	
		
			$keys = array_keys($data);
			
			switch ($this->range) {
				default:
				case 'week':
					$i = 0;
					foreach($data as $day => $value)
					{
						$key =  date('w', strtotime($day));
						$week[$key]['total'] = $value['total'];
						$week[$key]['total_poprequests'] = $value['total_poprequests'];
						$week[$key]['total_procent'] = ($value['total']) ? $value['total'] / 100 * $value['total_poprequests'] : 0;
					}
					while($i < 7)
					{
						if(isset($week[$i]))
						{
							$json['total']['data'][] = array($i, $week[$i]['total']);
							$json['poprequests']['data'][] = array($i, $week[$i]['total_poprequests']);
						}
						else
						{
							$json['total']['data'][] = array($i, 0);
							$json['poprequests']['data'][] = array($i, 0);
							$i++;
						}
						$i++;
					}
									
					for ($i = 0; $i < 7; $i++) {
						$date = date('Y-m-d', $keys[0] + ($i * 86400));

						$json['xaxis'][] = array(date('w', strtotime($date)), date('D', strtotime($date)));
					}
					
					break;
					
				case 'month':
					$array_date = explode('-', date('Y-m-d', $this->start));
					$days = cal_days_in_month(CAL_GREGORIAN, $array_date[1], $array_date[0]);
					
					foreach($data as $day => $value)
					{
						$key =  date('w', strtotime($day));
						$week[$key]['total'] = $value['total'];
						$week[$key]['total_poprequests'] = $value['total_poprequests'];
						$week[$key]['total_procent'] = ($value['total']) ? $value['total'] / 100 * $value['total_poprequests'] : 0;
					}
					
					$i = 0;
					while($i < $days)
					{
						if(isset($week[$i]))
						{
							$json['total']['data'][] = array($i, $week[$i]['total']);
							$json['poprequests']['data'][] = array($i, $week[$i]['total_poprequests']);
						}
						else
						{
							$json['total']['data'][] = array($i, 0);
							$json['poprequests']['data'][] = array($i, 0);
						}
						$i++;
						
					}
					
					for ($i = 1; $i <= $days; $i++) {
						$date = $array_date[0] . '-' . $array_date[1] . '-' . $i;

						$json['xaxis'][] = array(date('j', strtotime($date)), date('d', strtotime($date)));
					}
					break;
				
				case 'year':
					foreach($data as $day => $value)
					{
						$key =  date('n', strtotime($day));
						$month[$key]['total_requests'] += $value['total'];
						$month[$key]['total_poprequests'] += $value['total_poprequests'];
						$month[$key]['total_toprequests'] = ($value['total']) ? $value['total'] / 100 * $value['total_poprequests'] : 0;
					}
					$i = 0;
					while($i < 12)
					{
						if(isset($month[$i]))
						{
							$json['total']['data'][] = array($i, $month[$i]['total_requests']);
							$json['poprequests']['data'][] = array($i, $month[$i]['total_poprequests']);
						}
						else 
						{
							$json['total']['data'][] = array($i, 0);
							$json['poprequests']['data'][] = array($i, 0);
						}
						$i++;
					}

					for ($i = 1; $i <= 12; $i++) {
						$json['xaxis'][] = array($i, date('M', mktime(0, 0, 0, $i)));
					}
					break;
			}
				
		return $json;
	}

}
