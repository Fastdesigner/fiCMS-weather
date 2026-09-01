<?php

if (!$site['onsite']) return false;

if ($context->mode === 'describe') return [
	'purpose'=>'Deletes one weather location and its cached forecast. To hide it without losing it, update weather with active:0.',
	'args'=>['id'=>'Weather location id.'],
	'scope'=>['admin']
];

$weatherMcp = ['id'=>trim((string) ($context->args['id'] ?? ''))];
if ($weatherMcp['id'] === '') return ['error'=>'Weather location id is required.'];
return \weather\McpView::delete($weatherMcp['id']);
