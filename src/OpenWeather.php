<?php

namespace weather;

class OpenWeather {
	private string $endpoint = 'https://api.openweathermap.org/data/3.0/onecall';

	public function fetch(array $location = [], array $options = []): array {
		$key = trim((string) ($options['key'] ?? ''));
		if ($key === '') return ['result'=>false,'code'=>0,'error'=>'api_key_missing','raw'=>[],'data'=>[]];

		$query = [
			'lat'=>$this->coordinate($location['lat'] ?? null,-90,90),
			'lon'=>$this->coordinate($location['lon'] ?? null,-180,180),
			'units'=>$this->units($options['units'] ?? 'metric'),
			'lang'=>$this->language($options['language'] ?? ($_SESSION['language'] ?? 'de')),
			'exclude'=>'minutely,hourly',
			'appid'=>$key
		];
		if ($query['lat'] === null || $query['lon'] === null) return ['result'=>false,'code'=>0,'error'=>'coordinates_missing','raw'=>[],'data'=>[]];

		$url = $this->endpoint.'?'.http_build_query($query,'','&',PHP_QUERY_RFC3986);
		$response = $this->request($url);
		$raw = $response['body'] !== '' ? json_decode($response['body'],true) : [];
		if (!is_array($raw)) $raw = [];
		if ($response['error'] !== '') return ['result'=>false,'code'=>$response['code'],'error'=>$response['error'],'raw'=>$raw,'data'=>[]];
		if ($response['code'] < 200 || $response['code'] >= 300) return ['result'=>false,'code'=>$response['code'],'error'=>$this->apiError($raw,$response['code']),'raw'=>$raw,'data'=>[]];

		return ['result'=>true,'code'=>$response['code'],'error'=>'','raw'=>$raw,'data'=>$this->normalize($raw,$location,$query['units'])];
	}

	public function normalize(array $raw = [], array $location = [], string $units = 'metric'): array {
		$data = [
			'location'=>[
				'id'=>trim((string) ($location['id'] ?? '')),
				'label'=>trim((string) ($location['label'] ?? '')),
				'lat'=>$raw['lat'] ?? ($location['lat'] ?? null),
				'lon'=>$raw['lon'] ?? ($location['lon'] ?? null),
				'timezone'=>trim((string) ($raw['timezone'] ?? ($location['timezone'] ?? '')))
			],
			'units'=>$this->units($units),
			'updated_at'=>intval($_SERVER['now'] ?? time()),
			'current'=>[],
			'daily'=>[],
			'alerts'=>[]
		];

		$current = is_array($raw['current'] ?? null) ? $raw['current'] : [];
		$daily = is_array($raw['daily'] ?? null) ? $raw['daily'] : [];
		$today = is_array($daily[0] ?? null) ? $daily[0] : [];
		$data['current'] = $this->weatherRow(array_merge($today,$current),$today,true);

		foreach ($daily as $day) {
			if (!is_array($day)) continue;
			$data['daily'][] = $this->weatherRow($day,$day,false);
		}

		foreach (($raw['alerts'] ?? []) as $alert) {
			if (!is_array($alert)) continue;
			$data['alerts'][] = [
				'sender'=>trim((string) ($alert['sender_name'] ?? '')),
				'event'=>trim((string) ($alert['event'] ?? '')),
				'start'=>intval($alert['start'] ?? 0),
				'end'=>intval($alert['end'] ?? 0),
				'description'=>trim((string) ($alert['description'] ?? '')),
				'tags'=>is_array($alert['tags'] ?? null) ? array_values(array_map('strval',$alert['tags'])) : []
			];
		}

		return $data;
	}

	private function weatherRow(array $row = [], array $daily = [], bool $current = false): array {
		$weather = is_array($row['weather'][0] ?? null) ? $row['weather'][0] : [];
		$temp = $row['temp'] ?? null;
		$dailyTemp = is_array($daily['temp'] ?? null) ? $daily['temp'] : [];
		if (is_array($temp)) $temp = $temp['day'] ?? ($temp['morn'] ?? ($temp['min'] ?? 0));

		return [
			'date'=>intval($row['dt'] ?? 0),
			'sunrise'=>intval($row['sunrise'] ?? 0),
			'sunset'=>intval($row['sunset'] ?? 0),
			'temp'=>$this->number($temp),
			'temp_now'=>$this->number($current ? ($row['temp'] ?? 0) : ($dailyTemp['day'] ?? $temp)),
			'temp_min'=>$this->number($dailyTemp['min'] ?? $row['temp_min'] ?? $temp),
			'temp_max'=>$this->number($dailyTemp['max'] ?? $row['temp_max'] ?? $temp),
			'feels_like'=>$this->number(is_array($row['feels_like'] ?? null) ? ($row['feels_like']['day'] ?? 0) : ($row['feels_like'] ?? 0)),
			'pressure'=>$this->number($row['pressure'] ?? 0),
			'humidity'=>$this->number($row['humidity'] ?? 0),
			'clouds'=>$this->number($row['clouds'] ?? 0),
			'uvi'=>$this->number($row['uvi'] ?? 0,1),
			'wind_speed'=>$this->number($row['wind_speed'] ?? 0,1),
			'wind_gust'=>$this->number($row['wind_gust'] ?? 0,1),
			'wind_deg'=>$this->number($row['wind_deg'] ?? 0),
			'rain'=>$this->number(is_array($row['rain'] ?? null) ? ($row['rain']['1h'] ?? 0) : ($row['rain'] ?? 0),1),
			'snow'=>$this->number(is_array($row['snow'] ?? null) ? ($row['snow']['1h'] ?? 0) : ($row['snow'] ?? 0),1),
			'pop'=>$this->number(($row['pop'] ?? 0) * 100),
			'icon'=>preg_replace('/[^a-z0-9]/i','',trim((string) ($weather['icon'] ?? ''))),
			'condition'=>trim((string) ($weather['main'] ?? '')),
			'description'=>trim((string) ($weather['description'] ?? ''))
		];
	}

	private function request(string $url): array {
		if (function_exists('curl__request')) {
			$result = curl__request($url,[],[],defined('CURL_DEFAULT_AGENT') ? CURL_DEFAULT_AGENT : '', '', null, 'GET', 12);
			return ['code'=>intval($result['code'] ?? 0),'body'=>(string) ($result['body'] ?? ''),'error'=>(string) ($result['error'] ?? '')];
		}

		$body = @file_get_contents($url);
		return ['code'=>$body === false ? 0 : 200,'body'=>$body === false ? '' : $body,'error'=>$body === false ? 'request_failed' : ''];
	}

	private function coordinate($value, float $min, float $max): ?float {
		if (!is_numeric($value)) return null;
		$value = floatval($value);
		return ($value >= $min && $value <= $max) ? $value : null;
	}

	private function units($value): string {
		$value = trim((string) $value);
		return in_array($value,['metric','imperial','standard'],true) ? $value : 'metric';
	}

	private function language($value): string {
		$value = strtolower(preg_replace('/[^a-z_-]/i','',trim((string) $value)));
		return $value !== '' ? substr($value,0,5) : 'de';
	}

	private function apiError(array $raw = [], int $code = 0): string {
		$message = trim((string) ($raw['message'] ?? ''));
		if ($message !== '') return $message;
		return $code > 0 ? 'http_'.$code : 'request_failed';
	}

	private function number($value, int $precision = 0): float|int {
		if (!is_numeric($value)) $value = 0;
		$value = round(floatval($value),$precision);
		return $precision === 0 ? intval($value) : $value;
	}
}
