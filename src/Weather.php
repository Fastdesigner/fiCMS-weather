<?php

namespace weather;

require_once __DIR__.'/OpenWeather.php';

class Weather {
	private string $basePath = '';
	private string $configFile = '';
	private string $cacheDir = '';
	private array $iconCodes = ['01d','01n','02d','02n','03d','03n','04d','04n','09d','09n','10d','10n','11d','11n','13d','13n','50d','50n'];
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

	public function iconCodes(): array {
		return $this->iconCodes;
	}

	public function iconUrl(string $icon = ''): string {
		$icon = preg_replace('/[^a-z0-9]/i','',trim($icon));
		if ($icon === '') $icon = '01d';
		foreach (['custom/',''] as $folder) {
			foreach (['png','webp','svg','gif','jpg','jpeg'] as $extension) {
				if (!is_file($this->basePath.'/assets/images/weather/'.$folder.$icon.'.'.$extension)) continue;
				return (defined('PAGEPATH') ? PAGEPATH.'/' : '').'system/plugins/'.basename($this->basePath).'/assets/images/weather/'.$folder.$icon.'.'.$extension;
			}
		}
		return $icon !== '' ? 'https://openweathermap.org/img/wn/'.$icon.'@2x.png' : '';
	}

	public function preview(string $locationId): array {
		$location = $this->location($locationId);
		$cache = $this->readCache($location['id'] ?? '');
		$data = is_array($cache['data'] ?? null) ? $this->convertForecast($cache['data'],$this->visitorUnits()) : [];
		if (isset($data['current']) && is_array($data['current'])) $data['current']['units'] = $data['units'] ?? 'metric';
		return [
			'location'=>$location,
			'updated_at'=>intval($cache['updated_at'] ?? 0),
			'current'=>is_array($data['current'] ?? null) ? $data['current'] : [],
			'daily'=>is_array($data['daily'] ?? null) ? $data['daily'] : []
		];
	}

	public function saveIconMedia(string $name, mixed $value): array {
		$icon = substr($name,5);
		if (!in_array($icon,$this->iconCodes,true)) return ['result'=>false];
		$file = $this->mediaValueFile($value);
		if ($file === '') return ['result'=>$this->deleteCustomIcon($icon),'deleted'=>1];
		$extension = strtolower(pathinfo($file,PATHINFO_EXTENSION));
		if (!in_array($extension,['png','webp','svg','gif','jpg','jpeg'],true)) return ['result'=>false];
		$this->deleteCustomIcon($icon);
		$content = file_get_contents($file);
		return ['result'=>is_string($content) && $this->write($this->basePath.'/assets/images/weather/custom/'.$icon.'.'.$extension,$content),'icon'=>$icon];
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
		return $this->write($this->configFile,$this->normalizeConfig(array_replace_recursive($this->configDefaults,$config)),true);
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
		$saveId = $original;
		if ($saveId === '' || $saveId === 'new') $saveId = $this->createLocationId($post['location_label'] ?? 'location',$config);
		$entry = array_merge($this->blankLocation($saveId),[
			'id'=>$saveId,
			'active'=>!empty($post['location_active']) ? 1 : 0,
			'label'=>trim((string) ($post['location_label'] ?? '')),
			'lat'=>trim((string) ($post['location_lat'] ?? '')),
			'lon'=>trim((string) ($post['location_lon'] ?? ''))
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
		if ($name === 'cache_hours') {
			$config['cache_minutes'] = max(1,min(24,intval($value))) * 60;
			return $this->saveConfig($config);
		}
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
		$result = $provider->fetch($location,['key'=>trim((string) ($service['key'] ?? '')),'units'=>'metric','language'=>$_SESSION['language'] ?? ($GLOBALS['user']['language'] ?? 'de')]);
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
		$result = $provider->fetch($location,['key'=>$key,'units'=>'metric','language'=>$_SESSION['language'] ?? ($GLOBALS['user']['language'] ?? 'de')]);
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
		$days = max(1,min(8,intval($options['days'] ?? $config['forecast_days'])));
		$data = $this->convertForecast($data,in_array(($options['units'] ?? ''),['metric','imperial'],true) ? $options['units'] : $this->visitorUnits());
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
		$this->deleteFile($file,false);
	}

	private function deleteCustomIcon(string $icon): bool {
		$result = true;
		foreach (glob($this->basePath.'/assets/images/weather/custom/'.$icon.'.{png,webp,svg,gif,jpg,jpeg}',GLOB_BRACE) ?: [] as $file) if (!$this->deleteFile($file,false)) $result = false;
		return $result;
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

	private function mediaValueFile(mixed $value): string {
		if (function_exists('images__get_relevant_json')) {
			$media = images__get_relevant_json($value,false,'or');
			if (is_array($media)) {
				foreach (['or','src'] as $key) {
					$file = $this->publicPathToFile($media[$key] ?? '');
					if ($file !== '') return $file;
				}
			}
		}
		$data = function_exists('helper__json_convert') ? helper__json_convert($value) : $this->decode(is_string($value) ? $value : '');
		if (!is_array($data)) return '';
		$key = array_key_first($data);
		if ($key === null || empty($data[$key]['media'][0]['id']) || !function_exists('images__parse_image')) return '';
		$media = images__parse_image($data[$key]['media'][0]['id']);
		if (!is_array($media)) return '';
		foreach (['or','src'] as $key) {
			$file = $this->publicPathToFile($media[$key] ?? '');
			if ($file !== '') return $file;
		}
		return '';
	}

	private function publicPathToFile(mixed $path): string {
		if (!is_string($path) || trim($path) === '') return '';
		$file = trim(str_replace('\\','/',$path));
		if (defined('PAGEPATH') && PAGEPATH !== '') {
			$pagePath = trim(str_replace('\\','/',PAGEPATH),'/');
			if ($pagePath !== '' && str_starts_with(ltrim($file,'/'),$pagePath.'/')) $file = substr(ltrim($file,'/'),strlen($pagePath) + 1);
		}
		if (preg_match('/^https?:\\/\\/[^\\/]+\\/(.+)$/i',$file,$match)) $file = $match[1];
		$file = ltrim($file,'/');
		return is_file($file) ? $file : '';
	}

	private function visitorUnits(): string {
		foreach ([$_SESSION['weather_units'] ?? null,$_COOKIE['weather_units'] ?? null,$GLOBALS['user']['weather_units'] ?? null] as $unit) if (in_array($unit,['metric','imperial'],true)) return $unit;
		foreach (['HTTP_CF_IPCOUNTRY','GEOIP_COUNTRY_CODE','HTTP_X_COUNTRY_CODE'] as $key) {
			$country = strtoupper(trim((string) ($_SERVER[$key] ?? '')));
			if (in_array($country,['US','LR','MM'],true)) return 'imperial';
		}
		$language = strtolower(str_replace('_','-',trim((string) ($_SESSION['language'] ?? ($GLOBALS['user']['language'] ?? '')))));
		return $language === 'en-us' ? 'imperial' : 'metric';
	}

	private function convertForecast(array $data, string $target): array {
		$source = in_array(($data['units'] ?? 'metric'),['metric','imperial','standard'],true) ? $data['units'] : 'metric';
		$target = in_array($target,['metric','imperial'],true) ? $target : 'metric';
		if ($source === $target) return $data;
		if (isset($data['current']) && is_array($data['current'])) $data['current'] = $this->convertRow($data['current'],$source,$target);
		if (isset($data['daily']) && is_array($data['daily'])) foreach ($data['daily'] as $key => $row) if (is_array($row)) $data['daily'][$key] = $this->convertRow($row,$source,$target);
		$data['units'] = $target;
		return $data;
	}

	private function convertRow(array $row, string $source, string $target): array {
		foreach (['temp','temp_now','temp_min','temp_max','feels_like'] as $key) if (isset($row[$key]) && is_numeric($row[$key])) $row[$key] = $this->convertTemperature(floatval($row[$key]),$source,$target);
		foreach (['wind_speed','wind_gust'] as $key) if (isset($row[$key]) && is_numeric($row[$key])) $row[$key] = $this->convertWind(floatval($row[$key]),$source,$target);
		return $row;
	}

	private function convertTemperature(float $value, string $source, string $target): int {
		if ($source === 'imperial') $value = ($value - 32) * 5 / 9;
		elseif ($source === 'standard') $value -= 273.15;
		if ($target === 'imperial') $value = $value * 9 / 5 + 32;
		return intval(round($value));
	}

	private function convertWind(float $value, string $source, string $target): float {
		if ($source === 'imperial') $value /= 2.2369362921;
		if ($target === 'imperial') $value *= 2.2369362921;
		return round($value,1);
	}

	private function deleteFile(string $file, bool $checkIfEmpty = true): bool {
		if ($file === '' || !is_file($file)) return false;
		$relative = $this->relativePath($file);
		if ($relative !== '' && function_exists('helper__files_delete')) return helper__files_delete($relative,$checkIfEmpty);
		return @unlink($file);
	}

	private function relativePath(string $file): string {
		$file = str_replace('\\','/',$file);
		$root = str_replace('\\','/',getcwd() ?: '');
		if ($root !== '' && str_starts_with($file,$root.'/')) return substr($file,strlen($root) + 1);
		return $file !== '' && $file[0] !== '/' && !preg_match('/^[a-zA-Z]:[\/\\\\]/',$file) ? $file : '';
	}

	private function write(string $file, array|string $data, bool $secure = false): bool {
		if ($file === '') return false;
		$relative = $this->relativePath($file);
		if ($relative !== '' && function_exists('helper__files_write')) return helper__files_write($relative,$data,true,$secure);
		if (!is_dir(dirname($file))) mkdir(dirname($file),0775,true);
		if (is_array($data)) $data = json_encode($data,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
		return file_put_contents($file,$data) !== false;
	}
}
