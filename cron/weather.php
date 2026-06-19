<?php

if (!$site['onsite']) return;

require_once dirname(__DIR__).'/src/Weather.php';

$weather = [
	'entry'=>new \weather\Weather(dirname(__DIR__)),
	'result'=>[]
];

$weather['result'] = $weather['entry']->cron();

unset($weather);
