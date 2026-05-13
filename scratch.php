<?php
require __DIR__ . '/classes/SmsService.php';

// Test phone formatting using Reflection since it's private
$method = new ReflectionMethod('SmsService', 'normalizePhone');
$method->setAccessible(true);

$tests = [
    '0700123456',
    '254700123456',
    '+254700123456',
    '700123456', // 9 digits
];

foreach ($tests as $test) {
    echo $test . " => " . $method->invoke(null, $test) . "\n";
}
