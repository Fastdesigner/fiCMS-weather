<?php

return [
	'purpose'=>'Shows the exact current conditions, forecast and alerts returned by get(type:"weather") as a compact weather card.',
	'syntax'=>'[Short title](weather:<card.reference returned by get(type:"weather")>)',
	'layout'=>'block',
	'requires'=>['get:weather'],
	'available'=>[\weather\Card::class,'available'],
	'parse'=>[\weather\Card::class,'parse'],
	'html'=>[\weather\Card::class,'html'],
	'plain'=>[\weather\Card::class,'plain'],
	'attachments'=>[\weather\Card::class,'attachments']
];
