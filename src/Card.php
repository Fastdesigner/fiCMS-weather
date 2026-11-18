<?php

namespace weather;

final class Card {
	public static function available(?\mcp\Context $context = null): bool {
		$weather = new Weather();
		$config = $weather->getConfig();
		if (intval($config['active']) !== 1) return false;
		$status = $weather->serviceStatus();
		if ($status['active'] !== 1 || $status['has_key'] !== 1) return false;
		foreach ($weather->locations($config) as $location) if (intval($location['active']) === 1) return true;
		return false;
	}

	public static function prepare(array $forecast, \mcp\Context $context): array {
		if (($context->task['name'] ?? '') !== 'chat' || ($context->task['id'] ?? '') !== $context->task_id || !in_array($context->kind(),['system','master'],true) || !isset($forecast['current'],$forecast['daily']) || isset($forecast['writable'])) return $forecast;
		$forecast['card'] = [
			'reference'=>\ficms\RichResponse\Snapshots::issue('weather',$forecast,$context),
			'usage'=>'Use [Short title](weather:reference) to show this exact forecast. Keep stale status, warnings and alerts.'
		];
		return $forecast;
	}

	public static function parse(string $title, string $reference, string $language = 'de', ?\mcp\Context $context = null): array {
		try {
			$forecast = \ficms\RichResponse\Snapshots::read('weather',$reference,$context);
		} catch (\DomainException) {
			return [];
		}
		return ['type'=>'weather','title'=>$title,'reference'=>$reference,'forecast'=>$forecast,'language'=>$language];
	}

	public static function html(array $node, string $language = 'de', bool $email = false): string {
		$weather = ['facts'=>self::facts($node,$language),'icons'=>self::icons($node),'notices'=>'','facts_html'=>'','rows'=>''];
		foreach ($weather['facts']['notices'] as $notice) $weather['notices'] .= $email
			? '<tr><td colspan="4">'.($notice['title'] !== '' ? '<b>'.self::escape($notice['title']).'</b><br>' : '').($notice['period'] !== '' ? self::escape($notice['period']).'<br>' : '').self::escape($notice['text']).($notice['source'] !== '' ? '<br><small>'.self::escape($notice['source']).'</small>' : '').'</td></tr>'
			: '<p class="chat-card__notice"'.($notice['title'] !== '' ? ' data-tone="warning"' : '').'>'.($notice['title'] !== '' ? '<strong>'.self::escape($notice['title']).'</strong>' : '').($notice['period'] !== '' ? '<small>'.self::escape($notice['period']).'</small>' : '').self::escape($notice['text']).($notice['source'] !== '' ? '<small>'.self::escape($notice['source']).'</small>' : '').'</p>';
		foreach ($weather['facts']['current']['facts'] as $fact) $weather['facts_html'] .= $email ? ($weather['facts_html'] === '' ? '' : ', ').self::escape($fact['label']).' <b>'.self::escape($fact['value']).'</b>' : '<div><dt>'.self::escape($fact['label']).'</dt><dd>'.self::escape($fact['value']).'</dd></div>';
		foreach ($weather['facts']['days'] as $day) {
			$weather['icon'] = self::image($weather['icons'],$day['icon'],$email ? '' : $day['description'],$email ? 28 : 24,$email);
			$weather['rows'] .= $email
				? '<tr><th align="left" scope="row">'.self::escape(str_replace(' ',"\u{00A0}",$day['label'])).'</th><td>'.$weather['icon'].' '.self::escape($day['description']).'</td><td>'.self::escape($day['rain']).'</td><td>'.self::escape($day['min'].' / '.$day['max']).'</td></tr>'
				: '<tr><th scope="row"><time datetime="'.self::escape($day['date']).'">'.self::escape($day['label']).'</time></th><td>'.$weather['icon'].'</td><td>'.self::escape($day['rain']).'</td><td>'.($day['range'] ? '<span class="chat-card__range"><data value="'.self::escape($day['min_value']).'">'.self::escape($day['min']).'</data><i style="--_system-range-from:'.$day['from'].';--_system-range-to:'.$day['to'].'">'.($day['at'] === null ? '' : '<b style="--_system-range-at:'.$day['at'].'"></b>').'</i><data value="'.self::escape($day['max_value']).'">'.self::escape($day['max']).'</data></span>' : self::escape($day['min'].' / '.$day['max'])).'</td></tr>';
		}
		$weather['current'] = $weather['facts']['current'];
		$weather['image'] = self::image($weather['icons'],$weather['current']['icon'],'',$email ? 40 : 48,$email);
		if ($email) return '<table role="presentation" width="100%" cellpadding="3" cellspacing="0" border="0">'
			.'<tr><td colspan="4"><b>'.self::escape($weather['facts']['title']).'</b>'.($weather['facts']['location'] !== '' ? '<br>'.self::escape($weather['facts']['location']) : '').'</td></tr>'
			.$weather['notices']
			.'<tr><td colspan="4">'.$weather['image'].' <b>'.self::escape($weather['current']['temperature']."\u{00A0}".$weather['current']['unit']).'</b> '.self::escape(implode(', ',array_filter([$weather['current']['description'],$weather['current']['feels_like']]))).($weather['facts_html'] !== '' ? '<br>'.$weather['facts_html'] : '').'</td></tr>'
			.($weather['rows'] !== '' ? '<tr><td></td><td></td><th align="left" scope="col">'.self::escape($weather['facts']['labels']['rain']).'</th><th align="left" scope="col">'.self::escape($weather['facts']['labels']['temperature']).'</th></tr>'.$weather['rows'] : '')
			.'<tr><td colspan="4"><small>'.self::escape($weather['facts']['updated']['label']).'</small></td></tr></table>';
		return '<section class="chat-card" data-chat-rich aria-label="'.self::escape($weather['facts']['title']).'">'
			.'<header class="chat-card__header"><strong>'.self::escape($weather['facts']['title']).'</strong>'.($weather['facts']['location'] !== '' ? '<span>'.self::escape($weather['facts']['location']).'</span>' : '').'</header>'
			.$weather['notices']
			.'<div class="chat-card__metric">'.$weather['image'].'<data'.($weather['current']['value'] === null ? '' : ' value="'.self::escape($weather['current']['value']).'"').'>'.self::escape($weather['current']['temperature']).'<small>'.self::escape($weather['current']['unit']).'</small></data><p>'.self::escape($weather['current']['description']).($weather['current']['feels_like'] !== '' ? '<small>'.self::escape($weather['current']['feels_like']).'</small>' : '').'</p></div>'
			.($weather['facts_html'] !== '' ? '<dl class="chat-card__facts">'.$weather['facts_html'].'</dl>' : '')
			.($weather['rows'] !== '' ? '<table class="chat-card__rows"><thead><tr><td></td><td></td><th scope="col">'.self::escape($weather['facts']['labels']['rain']).'</th><th scope="col">'.self::escape($weather['facts']['labels']['temperature']).'</th></tr></thead><tbody>'.$weather['rows'].'</tbody></table>' : '')
			.'<footer class="chat-card__footer"><time datetime="'.self::escape($weather['facts']['updated']['datetime']).'">'.self::escape($weather['facts']['updated']['label']).'</time></footer></section>';
	}

	public static function plain(array $node): string {
		$weather = ['facts'=>self::facts($node,(string) ($node['language'] ?? 'de')),'lines'=>[]];
		$weather['current'] = $weather['facts']['current'];
		$weather['lines'][] = $weather['facts']['title'].($weather['facts']['location'] !== '' ? ', '.$weather['facts']['location'] : '');
		foreach ($weather['facts']['notices'] as $notice) $weather['lines'][] = implode(', ',array_filter([$notice['title'],$notice['period'],$notice['text'],$notice['source']]));
		$weather['lines'][] = implode(', ',array_filter([$weather['current']['temperature']."\u{00A0}".$weather['current']['unit'],$weather['current']['description'],$weather['current']['feels_like']]));
		$weather['facts_line'] = [];
		foreach ($weather['current']['facts'] as $fact) $weather['facts_line'][] = $fact['label'].' '.$fact['value'];
		if ($weather['facts_line']) $weather['lines'][] = implode(', ',$weather['facts_line']);
		foreach ($weather['facts']['days'] as $day) $weather['lines'][] = $day['label'].': '.implode(', ',array_filter([$day['description'],$day['min'].' / '.$day['max'],$weather['facts']['labels']['rain'].' '.$day['rain']]));
		$weather['lines'][] = $weather['facts']['updated']['label'];
		return implode(PHP_EOL,$weather['lines']);
	}

	public static function attachments(array $node): array {
		return array_values(self::icons($node));
	}

	private static function facts(array $node, string $language): array {
		$forecast = $node['forecast'];
		$weather = [
			'title'=>$node['title'],
			'location'=>mb_stripos($node['title'],$forecast['location']['label']) === false ? $forecast['location']['label'] : '',
			'labels'=>['rain'=>self::label($language,'rain'),'temperature'=>self::label($language,'temperature')],
			'zone'=>new \DateTimeZone($forecast['location']['timezone'] !== '' ? $forecast['location']['timezone'] : 'UTC'),
			'notices'=>[],
			'days'=>[],
			'numbers'=>new \NumberFormatter($language,\NumberFormatter::DECIMAL)
		];
		$weather['numbers']->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS,1);
		if (!empty($forecast['stale']) || !empty($forecast['warning'])) $weather['notices'][] = ['title'=>'','period'=>'','text'=>self::label($language,!empty($forecast['stale']) ? 'stale' : 'warning'),'source'=>''];
		foreach ($forecast['alerts'] as $alert) $weather['notices'][] = [
			'title'=>$alert['event'],
			'period'=>implode(' – ',array_filter([self::date($alert['start'],$language,'EEEdMjmm',$weather['zone']),self::date($alert['end'],$language,'EEEdMjmm',$weather['zone'])])),
			'text'=>$alert['description'],
			'source'=>$alert['sender']
		];
		$current = $forecast['current'];
		$weather['current'] = [
			'icon'=>$current['icon'],
			'value'=>$current['temperature'] === null ? null : (string) $current['temperature'],
			'temperature'=>$current['temperature'] === null ? self::label($language,'unavailable') : self::number($weather['numbers'],$current['temperature']),
			'unit'=>$current['temperature'] === null ? '' : $forecast['measurements']['temperature'],
			'description'=>self::sentence($current['description']),
			'feels_like'=>$current['feels_like'] !== null && ($current['temperature'] === null || round($current['feels_like']) !== round($current['temperature'])) ? self::label($language,'feels').' '.self::number($weather['numbers'],$current['feels_like'])."\u{00A0}".$forecast['measurements']['temperature'] : '',
			'facts'=>[]
		];
		if ($current['wind_speed'] !== null) $weather['current']['facts'][] = ['label'=>self::label($language,'wind'),'value'=>self::number($weather['numbers'],$current['wind_speed'])."\u{00A0}".$forecast['measurements']['wind_speed']];
		if ($current['wind_gust'] !== null && ($current['wind_speed'] === null || $current['wind_gust'] > $current['wind_speed'])) $weather['current']['facts'][] = ['label'=>self::label($language,'gust'),'value'=>self::number($weather['numbers'],$current['wind_gust'])."\u{00A0}".$forecast['measurements']['wind_speed']];
		if ($current['humidity'] !== null) $weather['current']['facts'][] = ['label'=>self::label($language,'humidity'),'value'=>self::number($weather['numbers'],$current['humidity'])."\u{00A0}".$forecast['measurements']['probability']];
		$weather['temperatures'] = array_filter(array_merge([$current['temperature']],array_column($forecast['daily'],'temperature_min'),array_column($forecast['daily'],'temperature_max')),static fn($value): bool => $value !== null);
		$weather['low'] = $weather['temperatures'] ? min($weather['temperatures']) : 0;
		$weather['span'] = $weather['temperatures'] ? (max($weather['temperatures']) - $weather['low'] ?: 1) : 1;
		foreach ($forecast['daily'] as $day) $weather['days'][] = [
			'date'=>$day['date'],
			'label'=>self::date($day['timestamp'],$language,'EEEd',$weather['zone']),
			'icon'=>$day['icon'],
			'description'=>self::sentence($day['description']),
			'rain'=>$day['precipitation_probability'] === null ? self::label($language,'unavailable') : self::number($weather['numbers'],$day['precipitation_probability'])."\u{00A0}".$forecast['measurements']['probability'],
			'min_value'=>(string) $day['temperature_min'],
			'max_value'=>(string) $day['temperature_max'],
			'min'=>$day['temperature_min'] === null ? self::label($language,'unavailable') : self::number($weather['numbers'],$day['temperature_min']).'°',
			'max'=>$day['temperature_max'] === null ? self::label($language,'unavailable') : self::number($weather['numbers'],$day['temperature_max']).'°',
			'range'=>$day['temperature_min'] !== null && $day['temperature_max'] !== null,
			'from'=>$day['temperature_min'] === null ? null : round(($day['temperature_min'] - $weather['low']) / $weather['span'],3),
			'to'=>$day['temperature_max'] === null ? null : round(($day['temperature_max'] - $weather['low']) / $weather['span'],3),
			'at'=>$current['temperature'] !== null && $day['date'] === substr($current['date'],0,10) ? round(($current['temperature'] - $weather['low']) / $weather['span'],3) : null
		];
		$weather['updated'] = ['datetime'=>$forecast['updated_at'],'label'=>self::label($language,'updated').' '.self::date($forecast['updated_at'],$language,'EEEdMjmm',$weather['zone']).' ('.$weather['zone']->getName().')'];
		unset($weather['zone'],$weather['numbers'],$weather['temperatures'],$weather['low'],$weather['span']);
		return $weather;
	}

	private static function date(int|string $value, string $language, string $skeleton, \DateTimeZone $zone): string {
		if ($value === '' || $value === 0) return '';
		return (string) \IntlDateFormatter::create($language,\IntlDateFormatter::NONE,\IntlDateFormatter::NONE,$zone,null,(new \IntlDatePatternGenerator($language))->getBestPattern($skeleton))->format(is_int($value) ? $value : new \DateTimeImmutable($value));
	}

	private static function sentence(string $value): string {
		return mb_strtoupper(mb_substr($value,0,1)).mb_substr($value,1);
	}

	private static function number(\NumberFormatter $numbers, int|float $value): string {
		return str_replace('-','−',(string) $numbers->format($value));
	}

	private static function image(array $icons, string $icon, string $description, int $size, bool $email): string {
		if (!isset($icons[$icon])) return '';
		return '<img class="chat-card__icon" src="'.self::escape($email ? 'cid:'.\mail\Mailer::attachmentCid($icons[$icon]) : (defined('PAGEPATH') ? PAGEPATH.'/' : '').$icons[$icon]).'" alt="'.self::escape($description).'"'.($description !== '' ? ' title="'.self::escape($description).'"' : '').' width="'.$size.'" height="'.$size.'"'.($email ? '' : ' loading="lazy"').'>';
	}

	private static function icons(array $node): array {
		$weather = ['instance'=>new Weather(),'icons'=>[]];
		foreach (array_merge([$node['forecast']['current']],$node['forecast']['daily']) as $row) {
			if (isset($weather['icons'][$row['icon']])) continue;
			$weather['file'] = $weather['instance']->iconFile($row['icon'],true);
			if ($weather['file'] === '') continue;
			try {
				$weather['content'] = \ficms\Files::readSnapshot($weather['file'],dirname($weather['file']),262144)['content'];
			} catch (\RuntimeException) {
				continue;
			}
			$weather['image'] = @getimagesizefromstring($weather['content']);
			if (!$weather['image'] || !in_array($weather['image']['mime'],['image/png','image/webp','image/gif','image/jpeg'],true) || $weather['image'][0] > 1024 || $weather['image'][1] > 1024) continue;
			$weather['icons'][$row['icon']] = $weather['file'];
		}
		return $weather['icons'];
	}

	private static function label(string $language, string $name): string {
		return language__get($language,'_weather_card_'.$name);
	}

	private static function escape(string $value): string {
		return htmlspecialchars($value,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8');
	}
}
