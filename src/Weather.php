<?php

namespace weather;

require_once __DIR__.'/OpenWeather.php';

class Weather {
	private string $basePath = '';
	private string $configFile = '';
	private string $cacheDir = '';
	private array $configDefaults = [
		'config_version'=>1,
		'active'=>1,
		'default_location'=>'',
		'units'=>'metric',
		'cache_minutes'=>240,
		'forecast_days'=>5,
		'locations'=>[]
	];

	public function __construct(string $basePath = '') {
		$this->basePath = rtrim($basePath,'/');
		if ($this->basePath === '') $this->basePath = dirname(__DIR__);
		if (defined('PLUGINPATH') && is_dir(PLUGINPATH.'/'.basename($this->basePath))) $this->basePath = PLUGINPATH.'/'.basename($this->basePath);
		$this->configFile = $this->basePath.'/data/weather.json';
		$this->cacheDir = $this->basePath.'/cache';
	}

	public function configFile(): string {
		return $this->configFile;
	}

	public function cacheFile(string $locationId): string {
		$locationId = $this->validId($locationId) ? trim($locationId) : '';
		return $locationId === '' ? '' : $this->cacheDir.'/'.$locationId.'.json';
	}

	public function getConfig(): array {
		$config = $this->configDefaults;
		if (is_file($this->configFile)) {
			$stored = $this->decode(file_get_contents($this->configFile));
			if (is_array($stored)) $config = array_replace_recursive($config,$stored);
		}
		return $this->normalizeConfig($config);
	}

	public function saveConfig(array $config = []): bool {
		return $this->write($this->configFile,$this->normalizeConfig(array_replace_recursive($this->configDefaults,$config)));
	}

	public function serviceStatus(): array {
		$config = $this->serviceConfig();
		$status = [
			'active'=>!empty($config['active']) ? 1 : 0,
			'licensed'=>!empty($config['licensed']) ? 1 : 0,
			'userkey'=>!empty($config['userkey']) ? 1 : 0,
			'needskey'=>!empty($config['needskey']) ? 1 : 0,
			'has_key'=>trim((string) ($config['key'] ?? '')) !== '' ? 1 : 0,
			'paid'=>intval($config['paid'] ?? 0),
			'ordername'=>trim((string) ($config['ordername'] ?? 'weather'))
		];
		return $status;
	}

	public function locations(array $config = []): array {
		if (empty($config)) $config = $this->getConfig();
		$locations = [];
		foreach ($config['locations'] as $location) {
			$location = $this->normalizeLocation($location);
			if ($location['id'] === '') continue;
			$locations[$location['id']] = $location;
		}
		return $locations;
	}

	public function location(string $id = '', array $config = []): array {
		if (empty($config)) $config = $this->getConfig();
		$id = trim($id);
		if ($id === '') $id = trim((string) ($config['default_location'] ?? ''));
		$locations = $this->locations($config);
		if ($id !== '' && isset($locations[$id])) return $locations[$id];
		return reset($locations) ?: $this->blankLocation('new');
	}

	public function blankLocation(string $id = 'new'): array {
		return [
			'id'=>$id,
			'active'=>1,
			'label'=>'',
			'lat'=>'',
			'lon'=>'',
			'forecast_days'=>5,
			'last_sync'=>0,
			'last_success'=>0,
			'last_error'=>'',
			'last_code'=>0
		];
	}

	public function saveLocationFromPost(string $id, array $post = []): array {
		$config = $this->getConfig();
		$original = $this->validId($id) ? trim($id) : '';
		$saveId = $this->validId($post['location_id'] ?? '') ? trim((string) $post['location_id']) : $original;
		if ($saveId === '' || $saveId === 'new') $saveId = $this->createLocationId($post['location_label'] ?? 'location',$config);
		$entry = array_merge($this->blankLocation($saveId),[
			'id'=>$saveId,
			'active'=>!empty($post['location_active']) ? 1 : 0,
			'label'=>trim((string) ($post['location_label'] ?? '')),
			'lat'=>trim((string) ($post['location_lat'] ?? '')),
			'lon'=>trim((string) ($post['location_lon'] ?? '')),
			'forecast_days'=>max(1,min(8,intval($post['location_forecast_days'] ?? $config['forecast_days'])))
		]);
		foreach ($config['locations'] as $key => $location) {
			if (($location['id'] ?? '') !== $original && ($location['id'] ?? '') !== $saveId) continue;
			$entry = array_merge($location,$entry);
			array_splice($config['locations'],$key,1);
			break;
		}
		$config['locations'][] = $this->normalizeLocation($entry);
		if ($config['default_location'] === '' || $original === $config['default_location']) $config['default_location'] = $saveId;
		return ['result'=>$this->saveConfig($config),'id'=>$saveId];
	}

	public function deleteLocation(string $id): bool {
		$config = $this->getConfig();
		foreach ($config['locations'] as $key => $location) {
			if (($location['id'] ?? '') !== $id) continue;
			array_splice($config['locations'],$key,1);
			if ($config['default_location'] === $id) $config['default_location'] = $config['locations'][0]['id'] ?? '';
			$this->deleteCache($id);
			return $this->saveConfig($config);
		}
		return false;
	}

	public function setLocationActive(string $id, int $active): bool {
		$config = $this->getConfig();
		foreach ($config['locations'] as $key => $location) {
			if (($location['id'] ?? '') !== $id) continue;
			$config['locations'][$key]['active'] = $active === 1 ? 1 : 0;
			return $this->saveConfig($config);
		}
		return false;
	}

	public function setDefaultLocation(string $id): bool {
		$config = $this->getConfig();
		foreach ($config['locations'] as $location) {
			if (($location['id'] ?? '') !== $id) continue;
			$config['default_location'] = $id;
			return $this->saveConfig($config);
		}
		return false;
	}

	public function updateSetting(string $name, mixed $value): bool {
		$config = $this->getConfig();
		if (!array_key_exists($name,$config) || in_array($name,['locations','default_location'],true)) return false;
		$config[$name] = match ($name) {
			'active' => intval($value) === 1 ? 1 : 0,
			'cache_minutes' => max(15,min(1440,intval($value))),
			'forecast_days' => max(1,min(8,intval($value))),
			'units' => in_array($value,['metric','imperial','standard'],true) ? $value : 'metric',
			default => $value
		};
		return $this->saveConfig($config);
	}

	public function forecast(string $locationId = '', array $options = []): array {
		$config = $this->getConfig();
		if ($config['active'] !== 1) return ['result'=>false,'error'=>'plugin_inactive','location'=>[],'current'=>[],'daily'=>[],'alerts'=>[]];
		$location = $this->location($locationId,$config);
		if (($location['id'] ?? '') === '' || ($location['id'] ?? '') === 'new') return ['result'=>false,'error'=>'location_missing','location'=>[],'current'=>[],'daily'=>[],'alerts'=>[]];
		if (intval($location['active'] ?? 0) !== 1 && empty($options['force'])) return ['result'=>false,'error'=>'location_inactive','location'=>$location,'current'=>[],'daily'=>[],'alerts'=>[]];

		$cache = $this->readCache($location['id']);
		if (empty($options['force']) && $this->cacheFresh($cache,intval($config['cache_minutes']))) return $this->limitForecast($cache['data'],$location,$options,$config);

		$result = $this->refreshLocation($location['id'],$config);
		if (!empty($result['result'])) return $this->limitForecast($result['data'],$location,$options,$config);
		if (is_array($cache['data'] ?? null)) return $this->limitForecast(array_merge($cache['data'],['stale'=>1,'error'=>$result['error'] ?? '']),$location,$options,$config);
		return ['result'=>false,'error'=>$result['error'] ?? 'request_failed','location'=>$location,'current'=>[],'daily'=>[],'alerts'=>[]];
	}

	public function forecastCustom(array $location = [], array $options = []): array {
		$config = $this->getConfig();
		if ($config['active'] !== 1) return ['result'=>false,'error'=>'plugin_inactive','location'=>[],'current'=>[],'daily'=>[],'alerts'=>[]];
		$location = $this->normalizeLocation(array_merge([
			'id'=>'custom-'.str_replace(['.','-'],['_','m'],trim((string) ($location['lat'] ?? ''))).'-'.str_replace(['.','-'],['_','m'],trim((string) ($location['lon'] ?? ''))),
			'active'=>1,
			'label'=>trim((string) ($location['label'] ?? ''))
		],$location));
		$cache = $this->readCache($location['id']);
		if (empty($options['force']) && $this->cacheFresh($cache,intval($config['cache_minutes']))) return $this->limitForecast($cache['data'],$location,$options,$config);

		$status = $this->serviceStatus();
		if ($status['active'] !== 1 || $status['has_key'] !== 1) {
			if (is_array($cache['data'] ?? null)) return $this->limitForecast(array_merge($cache['data'],['stale'=>1,'error'=>'service_inactive']),$location,$options,$config);
			return ['result'=>false,'error'=>'service_inactive','location'=>$location,'current'=>[],'daily'=>[],'alerts'=>[]];
		}

		$service = $this->serviceConfig();
		$provider = new OpenWeather();
		$result = $provider->fetch($location,['key'=>trim((string) ($service['key'] ?? '')),'units'=>$config['units'],'language'=>$_SESSION['language'] ?? ($GLOBALS['user']['language'] ?? 'de')]);
		if (!empty($result['result']) && is_array($result['data'] ?? null)) {
			$this->write($this->cacheFile($location['id']),['updated_at'=>intval($_SERVER['now'] ?? time()),'code'=>intval($result['code'] ?? 0),'data'=>$result['data']]);
			return $this->limitForecast($result['data'],$location,$options,$config);
		}
		if (is_array($cache['data'] ?? null)) return $this->limitForecast(array_merge($cache['data'],['stale'=>1,'error'=>$result['error'] ?? '']),$location,$options,$config);
		return ['result'=>false,'error'=>$result['error'] ?? 'request_failed','location'=>$location,'current'=>[],'daily'=>[],'alerts'=>[]];
	}

	public function refreshLocation(string $locationId, array $config = []): array {
		if (empty($config)) $config = $this->getConfig();
		$location = $this->location($locationId,$config);
		$status = $this->serviceStatus();
		if ($status['active'] !== 1 || $status['has_key'] !== 1) return $this->finishLocationSync($location,['result'=>false,'code'=>0,'error'=>'service_inactive','data'=>[]]);
		$service = $this->serviceConfig();
		$key = trim((string) ($service['key'] ?? ''));
		$provider = new OpenWeather();
		$result = $provider->fetch($location,['key'=>$key,'units'=>$config['units'],'language'=>$_SESSION['language'] ?? ($GLOBALS['user']['language'] ?? 'de')]);
		return $this->finishLocationSync($location,$result);
	}

	public function cron(): array {
		$config = $this->getConfig();
		$result = ['result'=>true,'items'=>[]];
		foreach ($this->locations($config) as $location) {
			if (intval($location['active']) !== 1) continue;
			if (!$this->locationDue($location,$config)) continue;
			$result['items'][$location['id']] = $this->refreshLocation($location['id'],$config);
		}
		return $result;
	}

	private function finishLocationSync(array $location, array $result): array {
		$config = $this->getConfig();
		foreach ($config['locations'] as $key => $entry) {
			if (($entry['id'] ?? '') !== ($location['id'] ?? '')) continue;
			$config['locations'][$key]['last_sync'] = intval($_SERVER['now'] ?? time());
			$config['locations'][$key]['last_code'] = intval($result['code'] ?? 0);
			$config['locations'][$key]['last_error'] = trim((string) ($result['error'] ?? ''));
			if (!empty($result['result'])) $config['locations'][$key]['last_success'] = intval($_SERVER['now'] ?? time());
			break;
		}
		$this->saveConfig($config);
		if (!empty($result['result']) && is_array($result['data'] ?? null)) $this->write($this->cacheFile($location['id']),['updated_at'=>intval($_SERVER['now'] ?? time()),'code'=>intval($result['code'] ?? 0),'data'=>$result['data']]);
		return $result;
	}

	private function limitForecast(array $data, array $location, array $options, array $config): array {
		$days = max(1,min(8,intval($options['days'] ?? ($location['forecast_days'] ?: $config['forecast_days']))));
		$data['result'] = true;
		$data['location'] = array_merge($location,is_array($data['location'] ?? null) ? $data['location'] : []);
		$data['daily'] = array_slice(is_array($data['daily'] ?? null) ? $data['daily'] : [],0,$days);
		return $data;
	}

	private function readCache(string $locationId): array {
		$file = $this->cacheFile($locationId);
		if ($file === '' || !is_file($file)) return [];
		$data = $this->decode(file_get_contents($file));
		return is_array($data) ? $data : [];
	}

	private function cacheFresh(array $cache, int $minutes): bool {
		$updated = intval($cache['updated_at'] ?? 0);
		return $updated > 0 && is_array($cache['data'] ?? null) && $updated >= intval($_SERVER['now'] ?? time()) - max(15,$minutes) * 60;
	}

	private function locationDue(array $location, array $config): bool {
		return intval($location['last_sync'] ?? 0) <= intval($_SERVER['now'] ?? time()) - max(15,intval($config['cache_minutes'])) * 60;
	}

	private function deleteCache(string $locationId): void {
		$file = $this->cacheFile($locationId);
		if ($file !== '' && is_file($file)) {
			if (function_exists('helper__files_delete')) helper__files_delete($file,false);
			else @unlink($file);
		}
	}

	private function serviceConfig(): array {
		if (!isset($_SERVER['TaskManager']) && class_exists('\ai\TaskManager')) $_SERVER['TaskManager'] = new \ai\TaskManager();
		if (isset($_SERVER['TaskManager']) && method_exists($_SERVER['TaskManager'],'getServiceConfig')) return $_SERVER['TaskManager']->getServiceConfig('weather');
		$key = trim((string) ($GLOBALS['site']['api_weather_key'] ?? ''));
		return ['active'=>$key !== '','licensed'=>$key !== '','needskey'=>true,'userkey'=>true,'key'=>$key,'ordername'=>'weather'];
	}

	private function normalizeConfig(array $config): array {
		$config['active'] = intval($config['active'] ?? 1) === 1 ? 1 : 0;
		$config['units'] = in_array($config['units'] ?? 'metric',['metric','imperial','standard'],true) ? $config['units'] : 'metric';
		$config['cache_minutes'] = max(15,min(1440,intval($config['cache_minutes'] ?? 240)));
		$config['forecast_days'] = max(1,min(8,intval($config['forecast_days'] ?? 5)));
		$config['locations'] = is_array($config['locations'] ?? null) ? array_values($config['locations']) : [];
		foreach ($config['locations'] as $key => $location) $config['locations'][$key] = $this->normalizeLocation($location);
		$config['locations'] = array_values(array_filter($config['locations'],fn($location) => ($location['id'] ?? '') !== ''));
		$config['default_location'] = $this->validId($config['default_location'] ?? '') ? trim((string) $config['default_location']) : '';
		if ($config['default_location'] === '' && isset($config['locations'][0]['id'])) $config['default_location'] = $config['locations'][0]['id'];
		return $config;
	}

	private function normalizeLocation(array $location): array {
		$location = array_merge($this->blankLocation(''),$location);
		$location['id'] = $this->validId($location['id'] ?? '') ? trim((string) $location['id']) : '';
		$location['active'] = intval($location['active'] ?? 1) === 1 ? 1 : 0;
		$location['label'] = trim((string) ($location['label'] ?? ''));
		$location['lat'] = trim((string) ($location['lat'] ?? ''));
		$location['lon'] = trim((string) ($location['lon'] ?? ''));
		$location['forecast_days'] = max(1,min(8,intval($location['forecast_days'] ?? 5)));
		foreach (['last_sync','last_success','last_code'] as $key) $location[$key] = max(0,intval($location[$key] ?? 0));
		$location['last_error'] = trim((string) ($location['last_error'] ?? ''));
		return $location;
	}

	private function createLocationId(string $label, array $config): string {
		$base = strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$this->ascii($label))));
		if ($base === '') $base = 'location';
		$ids = array_column($config['locations'],'id');
		$id = $base;
		$count = 2;
		while (in_array($id,$ids,true)) $id = $base.'-'.$count++;
		return $id;
	}

	private function ascii(string $value): string {
		$converted = function_exists('iconv') ? iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value) : false;
		return is_string($converted) && $converted !== '' ? $converted : $value;
	}

	private function validId($id): bool {
		return is_string($id) && preg_match('/^[a-z0-9][a-z0-9_-]*$/i',$id);
	}

	private function decode($value): mixed {
		if (!is_string($value)) return $value;
		$data = json_decode($value,true);
		return json_last_error() === JSON_ERROR_NONE ? $data : null;
	}

	private function write(string $file, array $data): bool {
		if ($file === '') return false;
		if (function_exists('helper__files_write')) return helper__files_write($file,$data,true,true);
		if (!is_dir(dirname($file))) mkdir(dirname($file),0775,true);
		return file_put_contents($file,json_encode($data,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) !== false;
	}
}
