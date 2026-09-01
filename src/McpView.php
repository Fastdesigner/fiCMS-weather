<?php

namespace weather;

class McpView {
	public static function read(string $id = 'default', array $data = [], string $scope = 'user'): array {
		$weather = [
			'instance'=>new Weather(),
			'id'=>trim($id),
			'data'=>$data,
			'scope'=>$scope
		];
		$weather['config'] = $weather['instance']->getConfig();
		$weather['locations'] = $weather['instance']->locations($weather['config']);
		if ($weather['id'] === '') $weather['id'] = 'default';
		if ($weather['scope'] === 'admin' && ($weather['data']['view'] ?? '') === 'edit') return self::edit($weather['instance'],$weather['id'],$weather['config'],$weather['locations']);
		if (intval($weather['config']['active'] ?? 0) !== 1) return ['error'=>'Weather is not active.'];
		if ($weather['id'] === 'list') return self::locations($weather['locations'],(string) ($weather['config']['default_location'] ?? ''),$weather['scope']);
		if (!count($weather['locations'])) return ['error'=>'No weather locations are configured.'];
		if ($weather['id'] === 'default') $weather['id'] = trim((string) ($weather['config']['default_location'] ?? ''));
		if ($weather['id'] === '' || !isset($weather['locations'][$weather['id']])) return ['error'=>'Unknown weather location. Use get(type:"weather", id:"list") for the configured locations.'];
		if (intval($weather['locations'][$weather['id']]['active'] ?? 0) !== 1) return ['error'=>'Weather location is not active.'];
		if (array_key_exists('days',$weather['data']) && (!is_numeric($weather['data']['days']) || intval($weather['data']['days']) < 1 || intval($weather['data']['days']) > 8)) return ['error'=>'Weather days must be between 1 and 8.'];
		if (array_key_exists('units',$weather['data']) && !in_array($weather['data']['units'],['metric','imperial'],true)) return ['error'=>'Weather units must be metric or imperial.'];

		$weather['options'] = [
			'units'=>in_array($weather['config']['units'] ?? '',['metric','imperial'],true) ? $weather['config']['units'] : 'metric'
		];
		if (isset($weather['data']['days']) && is_numeric($weather['data']['days'])) $weather['options']['days'] = max(1,min(8,intval($weather['data']['days'])));
		if (isset($weather['data']['units']) && in_array($weather['data']['units'],['metric','imperial'],true)) $weather['options']['units'] = $weather['data']['units'];
		$weather['forecast'] = $weather['instance']->forecast($weather['id'],$weather['options']);
		if (empty($weather['forecast']['result'])) return ['error'=>trim((string) ($weather['forecast']['error'] ?? 'Weather forecast is unavailable.'))];
		return self::forecast($weather['forecast']);
	}

	public static function create(array $data): array {
		$weather = new Weather();
		$result = $weather->saveLocation('new',$data);
		if (empty($result['result'])) return ['error'=>self::writeError((string) ($result['error'] ?? 'location_write_failed'))];
		return ['type'=>'weather','id'=>$result['id'],'created'=>true,'default'=>$weather->getConfig()['default_location'] === $result['id']];
	}

	public static function update(string $id, array $data): array {
		$weather = new Weather();
		$result = $weather->saveLocation($id,$data);
		if (empty($result['result'])) return ['error'=>self::writeError((string) ($result['error'] ?? 'location_write_failed'))];
		return ['type'=>'weather','id'=>$result['id'],'updated'=>true,'default'=>$weather->getConfig()['default_location'] === $result['id']];
	}

	public static function delete(string $id): array {
		$weather = new Weather();
		if (!isset($weather->locations()[$id])) return ['error'=>'Weather location not found.'];
		if (!$weather->deleteLocation($id)) return ['error'=>'Weather location could not be deleted.'];
		return ['type'=>'weather','id'=>$id,'deleted'=>true];
	}

	private static function edit(Weather $weather, string $id, array $config, array $locations): array {
		if ($id === 'default') $id = trim((string) ($config['default_location'] ?? ''));
		if ($id === '' || !isset($locations[$id])) return ['error'=>'Weather location not found.'];
		return [
			'type'=>'weather',
			'id'=>$id,
			'writable'=>[
				'label'=>(string) ($locations[$id]['label'] ?? ''),
				'lat'=>(string) ($locations[$id]['lat'] ?? ''),
				'lon'=>(string) ($locations[$id]['lon'] ?? ''),
				'active'=>intval($locations[$id]['active'] ?? 0),
				'forecast_days'=>intval($locations[$id]['forecast_days'] ?? 5),
				'default'=>$id === ($config['default_location'] ?? '')
			],
			'service'=>$weather->serviceStatus()
		];
	}

	private static function writeError(string $error): string {
		return match ($error) {
			'location_not_found' => 'Weather location not found.',
			'location_id_invalid' => 'Weather location id may contain only letters, numbers, underscores and hyphens.',
			'location_exists' => 'Weather location id already exists.',
			'location_label_required' => 'Weather location label is required.',
			'location_lat_invalid' => 'Weather latitude must be a number from -90 to 90.',
			'location_lon_invalid' => 'Weather longitude must be a number from -180 to 180.',
			default => 'Weather location could not be saved.'
		};
	}

	private static function locations(array $locations, string $default, string $scope): array {
		$weather = ['type'=>'weather','id'=>'list','locations'=>[]];
		foreach ($locations as $location) {
			if ($scope !== 'admin' && intval($location['active'] ?? 0) !== 1) continue;
			$weather['location'] = [
				'id'=>(string) ($location['id'] ?? ''),
				'label'=>(string) ($location['label'] ?? ''),
				'default'=>(string) ($location['id'] ?? '') === $default
			];
			if ($scope === 'admin') $weather['location']['active'] = intval($location['active'] ?? 0);
			$weather['locations'][] = $weather['location'];
		}
		return $weather;
	}

	private static function forecast(array $forecast): array {
		$weather = [
			'type'=>'weather',
			'id'=>(string) ($forecast['location']['id'] ?? ''),
			'location'=>[
				'label'=>(string) ($forecast['location']['label'] ?? ''),
				'timezone'=>(string) ($forecast['location']['timezone'] ?? '')
			],
			'units'=>(string) ($forecast['units'] ?? 'metric'),
			'updated_at'=>self::datetime(intval($forecast['updated_at'] ?? 0),(string) ($forecast['location']['timezone'] ?? '')),
			'current'=>self::row((array) ($forecast['current'] ?? []),(string) ($forecast['location']['timezone'] ?? ''),true),
			'daily'=>[],
			'alerts'=>[]
		];
		$weather['measurements'] = ($weather['units'] === 'imperial')
			? ['temperature'=>'°F','wind_speed'=>'mph','precipitation'=>'mm','pressure'=>'hPa','probability'=>'%']
			: ['temperature'=>'°C','wind_speed'=>'m/s','precipitation'=>'mm','pressure'=>'hPa','probability'=>'%'];
		foreach ((array) ($forecast['daily'] ?? []) as $weather['entry']) if (is_array($weather['entry'])) $weather['daily'][] = self::row($weather['entry'],$weather['location']['timezone']);
		foreach ((array) ($forecast['alerts'] ?? []) as $weather['entry']) {
			if (!is_array($weather['entry'])) continue;
			$weather['alerts'][] = [
				'event'=>(string) ($weather['entry']['event'] ?? ''),
				'sender'=>(string) ($weather['entry']['sender'] ?? ''),
				'start'=>self::datetime(intval($weather['entry']['start'] ?? 0),$weather['location']['timezone']),
				'end'=>self::datetime(intval($weather['entry']['end'] ?? 0),$weather['location']['timezone']),
				'description'=>(string) ($weather['entry']['description'] ?? ''),
				'tags'=>(array) ($weather['entry']['tags'] ?? [])
			];
		}
		if (!empty($forecast['stale'])) $weather['stale'] = true;
		if (!empty($forecast['error'])) $weather['warning'] = (string) $forecast['error'];
		return $weather;
	}

	private static function row(array $row, string $timezone, bool $current = false): array {
		$weather = [
			'timestamp'=>intval($row['date'] ?? 0),
			'date'=>self::datetime(intval($row['date'] ?? 0),$timezone,$current ? 'Y-m-d H:i:s' : 'Y-m-d'),
			'condition'=>(string) ($row['condition'] ?? ''),
			'description'=>(string) ($row['description'] ?? ''),
			'temperature'=>self::number($row['temp_now'] ?? $row['temp'] ?? 0),
			'temperature_min'=>self::number($row['temp_min'] ?? 0),
			'temperature_max'=>self::number($row['temp_max'] ?? 0),
			'feels_like'=>self::number($row['feels_like'] ?? 0),
			'precipitation_probability'=>self::number($row['pop'] ?? 0),
			'rain'=>self::number($row['rain'] ?? 0),
			'snow'=>self::number($row['snow'] ?? 0),
			'humidity'=>self::number($row['humidity'] ?? 0),
			'pressure'=>self::number($row['pressure'] ?? 0),
			'clouds'=>self::number($row['clouds'] ?? 0),
			'wind_speed'=>self::number($row['wind_speed'] ?? 0),
			'wind_gust'=>self::number($row['wind_gust'] ?? 0),
			'wind_direction'=>self::number($row['wind_deg'] ?? 0),
			'uvi'=>self::number($row['uvi'] ?? 0)
		];
		if (!$current) {
			$weather['sunrise'] = self::datetime(intval($row['sunrise'] ?? 0),$timezone);
			$weather['sunset'] = self::datetime(intval($row['sunset'] ?? 0),$timezone);
		}
		return $weather;
	}

	private static function datetime(int $timestamp, string $timezone, string $format = DATE_ATOM): string {
		if ($timestamp <= 0) return '';
		$weather = new \DateTimeImmutable('@'.$timestamp);
		if ($timezone !== '') $weather = $weather->setTimezone(new \DateTimeZone($timezone));
		return $weather->format($format);
	}

	private static function number(mixed $value): int|float {
		if (!is_numeric($value)) return 0;
		$weather = floatval($value);
		return floor($weather) === $weather ? intval($weather) : $weather;
	}
}
