<?php

namespace ficms {
	final class Client { public static string $token='weather-card-fixture-secret'; public function getCredentials(): array { return ['token'=>self::$token]; } }
	final class Http {
		public static int $calls = 0;
		public static function request(string $url, array $options): array { self::$calls++; return ['code'=>503,'body'=>'','error'=>'provider_unavailable']; }
	}
}
namespace widget {
	final class Registry { public static function layoutFile(string $name): string { return ''; } }
}
namespace mail {
	final class Mailer { public static function attachmentCid(string $path): string { return 'img-'.substr(hash('sha256',basename($path)),0,32).'@ficms'; } }
}
namespace {
	$weather_cards = ['plugin'=>dirname(__DIR__,2),'core'=>getenv('FICMS_CORE_PATH') ?: dirname(__DIR__,3).'/fiCMS','cwd'=>getcwd(),'directory'=>sys_get_temp_dir().'/ficms-weather-cards-'.bin2hex(random_bytes(6))];
	spl_autoload_register(static function(string $class) use ($weather_cards): void {
		foreach (['ficms\\'=>$weather_cards['core'].'/include/classes/fiCMS/src/','mcp\\'=>$weather_cards['core'].'/include/classes/mcp/src/','weather\\'=>$weather_cards['plugin'].'/src/'] as $prefix => $directory) {
			if (!str_starts_with($class,$prefix)) continue;
			$file = $directory.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
			if (is_file($file)) require $file;
		}
	});
	function weatherCheck(bool $condition, string $message): void { if (!$condition) throw new \RuntimeException($message); }
	function language__get(string $language, string $key): string { return json_decode(file_get_contents($GLOBALS['weather_cards']['plugin'].'/localization/'.$language.'/weather.json'),true)[$key]; }
	function mysqlFetchAssoc(string $query): array|false { preg_match('/WHERE `id` = ([0-9]+)/',$query,$match); return in_array((int) ($match[1] ?? 0),$GLOBALS['weather_card_chats'],true) ? ['id'=>(int) $match[1]] : false; }
	function weatherGet(\mcp\Context $context): array { $site = ['onsite'=>true]; return include $GLOBALS['weather_cards']['plugin'].'/mcp/get/weather.php'; }
	define('PLUGINPATH','plugins');
	define('SYSTEMPATH','system');
	define('PAGEPATH','https://weather.example.test');
	mkdir($weather_cards['directory'],0700);
	chdir($weather_cards['directory']);
	$_SERVER['now'] = 1794999600;
	$_SESSION = ['language'=>'de'];
	$GLOBALS['site'] = ['api_weather_key'=>'fixture-key'];
	$GLOBALS['tables']['chat'] = 'chat';
	$GLOBALS['weather_card_chats'] = [41,42];
	try {
		mkdir('plugins/fiCMS-weather/assets/images/weather',0700,true);
		foreach (['01d','10d'] as $icon) copy($weather_cards['plugin'].'/assets/images/weather/'.$icon.'.png','plugins/fiCMS-weather/assets/images/weather/'.$icon.'.png');
		$weather_cards['weather'] = new \weather\Weather();
		weatherCheck($weather_cards['weather']->saveConfig(['default_location'=>'hotel','locations'=>[['id'=>'hotel','active'=>1,'label'=>'Hotel <script>','lat'=>'51','lon'=>'6']]]),'Fixture config save failed');
		$weather_cards['forecast'] = (new \weather\OpenWeather())->normalize([
			'timezone'=>'Europe/Berlin',
			'current'=>['dt'=>$_SERVER['now'],'temp'=>12,'wind_speed'=>3,'weather'=>[['main'=>'Clear','description'=>'Clear <script>','icon'=>'01d']]],
			'daily'=>[['dt'=>$_SERVER['now'],'temp'=>['day'=>14,'min'=>8,'max'=>15],'pop'=>0.4,'wind_speed'=>4,'weather'=>[['main'=>'Rain','description'=>'Rain','icon'=>'10d']]]],
			'alerts'=>[['sender_name'=>'Weather service','event'=>'Storm alert','start'=>$_SERVER['now'],'end'=>$_SERVER['now']+3600,'description'=>'Stay indoors <script>','tags'=>['wind']]]
		],['id'=>'hotel','label'=>'Hotel <script>','lat'=>51,'lon'=>6]);
		\ficms\Files::writeJsonAtomic($weather_cards['weather']->cacheFile('hotel'),['updated_at'=>$_SERVER['now'],'data'=>$weather_cards['forecast']]);
		$weather_cards['context'] = new \mcp\Context();
		$weather_cards['context']->setActor(['kind'=>'system'],'user');
		$weather_cards['context']->task_id = 'chat-41';
		$weather_cards['context']->task = ['id'=>'chat-41','name'=>'chat','instruction_vars'=>['chat_session_id'=>41]];
		$weather_cards['context']->args = ['id'=>'default','data'=>['days'=>1,'units'=>'imperial']];
		weatherCheck(\weather\Card::available($weather_cards['context']) && \ficms\Http::$calls === 0,'Availability calls provider or hides usable weather');
		$weather_cards['result'] = weatherGet($weather_cards['context']);
		weatherCheck(isset($weather_cards['result']['card']['reference']) && \ficms\Http::$calls === 0,'Fresh cached forecast made a provider call or lacks reference');
		$weather_cards['canonical'] = $weather_cards['result'];
		unset($weather_cards['canonical']['card']);
		$weather_cards['node'] = \weather\Card::parse('Weather',$weather_cards['result']['card']['reference'],'en',$weather_cards['context']);
		weatherCheck($weather_cards['node']['forecast'] === $weather_cards['canonical'],'Snapshot differs from canonical get result');
        $weather_cards['bad_json']=$weather_cards['canonical'];$weather_cards['bad_json']['location']['label']="\xFF";
        weatherCheck(\weather\Card::prepare($weather_cards['bad_json'],$weather_cards['context']) === $weather_cards['bad_json'],'Snapshot JSON failure lost the readable forecast');
        $weather_cards['key']=new \ReflectionProperty(\ficms\RichResponse\Snapshots::class,'key');
        \ficms\Client::$token='';$weather_cards['key']->setValue(null,null);
        weatherCheck(!isset(weatherGet($weather_cards['context'])['card']),'Missing snapshot secret broke get(weather) or issued an unusable card');
        weatherCheck(\weather\Card::parse('Weather',$weather_cards['result']['card']['reference'],'en') === [],'Snapshot read failure escaped into transcript rendering');
        \ficms\Client::$token='weather-card-fixture-secret';$weather_cards['key']->setValue(null,null);

		weatherCheck($weather_cards['node']['forecast']['current']['temperature'] === 54 && $weather_cards['node']['forecast']['measurements']['temperature'] === '°F','Imperial forecast lost units or converted twice');
		weatherCheck($weather_cards['node']['forecast']['current']['icon'] === '01d' && $weather_cards['node']['forecast']['location']['timezone'] === 'Europe/Berlin','Canonical timezone or local icon code lost');
		$weather_cards['other'] = clone $weather_cards['context'];
		$weather_cards['other']->task_id = 'chat-42';
		$weather_cards['other']->task = ['id'=>'chat-42','name'=>'chat','instruction_vars'=>['chat_session_id'=>42]];
		weatherCheck(\weather\Card::parse('Foreign',$weather_cards['result']['card']['reference'],'en',$weather_cards['other']) === [],'Foreign conversation accepted weather reference');
		weatherCheck(\weather\Card::parse('Tampered',$weather_cards['result']['card']['reference'].'x') === [],'Tampered reference accepted');
		$weather_cards['normal'] = new \mcp\Context();
		weatherCheck(!isset(weatherGet($weather_cards['normal'])['card']),'Nonchat read issued a card');
		$site['onsite'] = true;
		$service = [];
		include $weather_cards['plugin'].'/widgets/weather/widget.php';
		foreach ([false,true] as $zero) {
			$weather_cards['raw'] = ['timezone'=>'Europe/Berlin','current'=>['dt'=>$_SERVER['now'],'weather'=>[['icon'=>'01d','description'=>'Kühle Brise']]],'daily'=>[['dt'=>$_SERVER['now'],'weather'=>[['icon'=>'10d','description'=>'Kühle Brise']]]]];
			if ($zero) {
				$weather_cards['raw']['current'] += ['temp'=>0,'feels_like'=>0,'humidity'=>0,'wind_speed'=>0,'pop'=>0];
				$weather_cards['raw']['daily'][0] += ['temp'=>['day'=>0,'min'=>0,'max'=>0],'pop'=>0];
			}
			$weather_cards['normalized'] = (new \weather\OpenWeather())->normalize($weather_cards['raw'],['id'=>'hotel','label'=>'Kühle Küste','lat'=>51,'lon'=>6]);
			foreach (['temp_now','temp_min','temp_max','feels_like','humidity','wind_speed','pop'] as $key) weatherCheck($zero ? in_array($weather_cards['normalized']['current'][$key],[0,0.0],true) : $weather_cards['normalized']['current'][$key] === null,'Provider missing/zero distinction lost: '.$key);
			weatherCheck($weather_cards['normalized']['current']['rain'] === 0.0 && $weather_cards['normalized']['current']['snow'] === 0.0,'Documented absence of rain/snow no longer means no occurrence');
			$weather_cards['widget'] = weather__widget_row($weather_cards['normalized']['current'],['show_description'=>1,'show_wind'=>1,'show_humidity'=>1,'show_rain_probability'=>1],['temp'=>'°C','wind'=>'m/s'],true,$weather_cards['weather']);
			weatherCheck($weather_cards['widget']['temp_now_label'] === ($zero ? '0°C' : 'Nicht verfügbar') && $weather_cards['widget']['pop_label'] === ($zero ? '0%' : 'Nicht verfügbar'),'Widget invented numeric values or hid zero');
			weatherCheck($weather_cards['widget']['render_wind'] === ($zero ? 1 : 0) && $weather_cards['widget']['render_humidity'] === ($zero ? 1 : 0),'Widget lost optional measurement presence');
			\ficms\Files::writeJsonAtomic($weather_cards['weather']->cacheFile('hotel'),['updated_at'=>$_SERVER['now'],'data'=>$weather_cards['normalized']]);
			foreach (['metric','imperial'] as $units) {
				$weather_cards['numeric_context'] = clone $weather_cards['context'];
				$weather_cards['numeric_context']->args['data']['units'] = $units;
				$weather_cards['numeric_result'] = weatherGet($weather_cards['numeric_context']);
				$weather_cards['numeric_node'] = \weather\Card::parse('Kühle Küste',$weather_cards['numeric_result']['card']['reference'],'en',$weather_cards['numeric_context']);
				weatherCheck($weather_cards['numeric_result']['current']['temperature'] === ($zero ? ($units === 'imperial' ? 32 : 0) : null),'MCP unit conversion invented a temperature');
				weatherCheck($weather_cards['numeric_result']['current']['feels_like'] === ($zero ? ($units === 'imperial' ? 32 : 0) : null) && $weather_cards['numeric_result']['current']['humidity'] === ($zero ? 0 : null),'MCP lost absent measurements or actual zero');
				foreach ([false,true] as $email) {
					$weather_cards['numeric_html'] = \weather\Card::html($weather_cards['numeric_node'],'en',$email);
					weatherCheck(str_contains($weather_cards['numeric_html'],'Kühle') && mb_check_encoding($weather_cards['numeric_html'],'UTF-8'),'Weather mail/chat corrupts UTF-8');
					weatherCheck($zero ? !str_contains($weather_cards['numeric_html'],'Unavailable') : str_contains($weather_cards['numeric_html'],'Unavailable'),'Required unavailable value lost or zero mislabeled');
					if (!$email) weatherCheck(str_contains($weather_cards['numeric_html'],'chat-card__range') === $zero,'Missing bounds drew a misleading range or zero range vanished');
					weatherCheck(str_contains($weather_cards['numeric_html'],'Humidity') === $zero && str_contains($weather_cards['numeric_html'],'Wind') === $zero,'Missing optional facts shown or actual zero hidden');
				}
				$weather_cards['numeric_plain'] = \weather\Card::plain($weather_cards['numeric_node']);
				weatherCheck(mb_check_encoding($weather_cards['numeric_plain'],'UTF-8') && str_contains($weather_cards['numeric_plain'],'Kühle') && (str_contains($weather_cards['numeric_plain'],'Unavailable') !== $zero),'Plain missing/zero/UTF-8 regression');
			}
		}
		$weather_cards['raw']['current'] = ['dt'=>$_SERVER['now']];
		$weather_cards['raw']['daily'][0] += ['feels_like'=>['day'=>14],'humidity'=>55,'wind_speed'=>4];
		$weather_cards['normalized'] = (new \weather\OpenWeather())->normalize($weather_cards['raw'],['id'=>'hotel','label'=>'Hotel','lat'=>51,'lon'=>6]);
		foreach (['temp_now','feels_like','humidity','wind_speed'] as $key) weatherCheck($weather_cards['normalized']['current'][$key] === null,'Daily data impersonates missing current reading: '.$key);
		weatherCheck($weather_cards['normalized']['current']['temp_min'] === 0 && $weather_cards['normalized']['current']['pop'] === 0,'Daily bounds or contextual probability lost');
		$weather_cards['raw']['current'] = ['dt'=>$_SERVER['now'],'temp'=>0];
		unset($weather_cards['raw']['daily'][0]['temp']['max']);
		$weather_cards['normalized'] = (new \weather\OpenWeather())->normalize($weather_cards['raw'],['id'=>'hotel','label'=>'Hotel','lat'=>51,'lon'=>6]);
		\ficms\Files::writeJsonAtomic($weather_cards['weather']->cacheFile('hotel'),['updated_at'=>$_SERVER['now'],'data'=>$weather_cards['normalized']]);
		$weather_cards['partial'] = weatherGet($weather_cards['context']);
		$weather_cards['partial_node'] = \weather\Card::parse('Weather',$weather_cards['partial']['card']['reference'],'en',$weather_cards['context']);
		weatherCheck($weather_cards['partial']['daily'][0]['temperature_min'] === 32 && $weather_cards['partial']['daily'][0]['temperature_max'] === null && !str_contains(\weather\Card::html($weather_cards['partial_node'],'en'),'chat-card__range'),'Partial bounds invented a bound or drew a range bar');
		$weather_cards['forecast']['current']['temp_now'] = 99;
		\ficms\Files::writeJsonAtomic($weather_cards['weather']->cacheFile('hotel'),['updated_at'=>1,'data'=>$weather_cards['forecast']]);
		$weather_cards['stale'] = weatherGet($weather_cards['context']);
		weatherCheck(\ficms\Http::$calls === 1 && $weather_cards['stale']['stale'] === true && $weather_cards['stale']['warning'] === 'provider_unavailable','Stale fallback or provider call count changed');
		$weather_cards['stale_node'] = \weather\Card::parse('Weather',$weather_cards['stale']['card']['reference'],'en',$weather_cards['context']);
		weatherCheck($weather_cards['stale_node']['forecast']['stale'] === true && $weather_cards['stale_node']['forecast']['warning'] === 'provider_unavailable','Stale snapshot lost warning');
		weatherCheck(\weather\Card::parse('History',$weather_cards['result']['card']['reference'])['forecast'] === $weather_cards['canonical'],'Cache refresh mutated historical snapshot');
		foreach ([$weather_cards['node'],$weather_cards['stale_node']] as $node) {
			foreach ([false,true] as $email) {
				$weather_cards['html'] = \weather\Card::html($node,'en',$email);
				weatherCheck(!str_contains($weather_cards['html'],'<script>') && !str_contains($weather_cards['html'],'openweathermap.org'),'Weather render injects content or fetches provider icons');
				weatherCheck(str_contains($weather_cards['html'],'Storm alert') && str_contains($weather_cards['html'],'Europe/Berlin') && str_contains($weather_cards['html'],'°F'),'Card drops alerts, timezone or units');
				weatherCheck(str_contains($weather_cards['html'],$email ? 'cid:' : 'plugins/fiCMS-weather/assets/'),'Card does not use local icons for channel');
			}
			weatherCheck(count(\weather\Card::attachments($node)) === 2,'Local weather icon attachments missing or duplicated');
			weatherCheck(str_contains(\weather\Card::plain($node),'Storm alert'),'Plain card lost alerts');
		}
		weatherCheck(\ficms\Http::$calls === 1,'Parsing or delivery called weather provider');
		$weather_cards['unsafe'] = $weather_cards['node'];
		$weather_cards['unsafe']['forecast']['current']['icon'] = '../../private';
		weatherCheck(count(\weather\Card::attachments($weather_cards['unsafe'])) === 1,'Untrusted icon name resolved a file');
		mkdir('plugins/fiCMS-weather/assets/images/weather/custom',0700);
		symlink('../01d.png','plugins/fiCMS-weather/assets/images/weather/custom/01d.png');
		weatherCheck(count(\weather\Card::attachments($weather_cards['node'])) === 1,'Symlinked custom icon was attached');
		unlink('plugins/fiCMS-weather/assets/images/weather/custom/01d.png');
		$weather_cards['id'] = str_repeat('a',32);
		\ficms\Files::writeJsonAtomic(\mcp\Simulation::root().'/'.$weather_cards['id'].'/state.json',['expires'=>time()+1800],true);
		$weather_cards['simulation'] = clone $weather_cards['context'];
		$weather_cards['simulation']->task_id = 'simulation-'.$weather_cards['id'];
		$weather_cards['simulation']->task = ['id'=>$weather_cards['simulation']->task_id,'name'=>'chat'];
		$_SERVER['mcp_simulation_context'] = $weather_cards['simulation'];
		$weather_cards['simulated'] = \weather\Card::prepare($weather_cards['canonical'],$weather_cards['simulation']);
		weatherCheck(\weather\Card::parse('Weather',$weather_cards['simulated']['card']['reference'],'en',$weather_cards['simulation'])['forecast'] === $weather_cards['canonical'],'Simulation changes canonical forecast');
		weatherCheck(!str_contains(json_encode($weather_cards['simulated']),'simulation'),'Simulation implementation leaked in model-visible weather result');
		\mcp\Simulation::end($weather_cards['id']);
		unset($_SERVER['mcp_simulation_context']);
		weatherCheck(\weather\Card::parse('Expired',$weather_cards['simulated']['card']['reference']) === [],'Ended simulation retained weather snapshot');
		$GLOBALS['site']['api_weather_key'] = '';
		weatherCheck(!\weather\Card::available() && \ficms\Http::$calls === 1,'Unavailable service advertised weather cards or called provider');
		weatherCheck(str_contains(\weather\Card::html($weather_cards['node'],'en',true),'Storm alert'),'Inactive service removed historical weather facts');
	} finally {
		\ficms\Files::removeDirectory('plugins',true,true);
		\ficms\Files::removeDirectory('system',true,true);
		chdir($weather_cards['cwd']);
		rmdir($weather_cards['directory']);
	}
	echo "PASS Weather canonical snapshots, context binding, units, stale alerts, immutable history and local delivery\n";
}
