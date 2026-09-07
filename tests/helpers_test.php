<?php
require_once __DIR__ . '/../api/helpers.php';
function check($condition, $message) {
    if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); }
}
$secret = str_repeat('test-secret-', 4);
$payload = ['sub'=>1, 'role'=>'student', 'exp'=>time()+3600];
$token = jwt_encode($payload, $secret);
check(jwt_decode($token, $secret)['sub'] === 1, 'Valid token should decode');
check(jwt_decode($token, 'wrong-secret') === null, 'Wrong signature must fail');
check(jwt_decode(jwt_encode(['sub'=>1,'exp'=>time()-1], $secret), $secret) === null, 'Expired token must fail');
check(jwt_decode(jwt_encode(['sub'=>1,'exp'=>time()+60,'nbf'=>'invalid'], $secret), $secret) === null, 'Malformed not-before must fail');
check(jwt_decode(jwt_encode(['sub'=>1,'exp'=>time()+60,'nbf'=>time()+30], $secret), $secret) === null, 'Future not-before must fail');
check(jwt_decode(jwt_encode(['exp'=>time()+60], $secret), $secret) === null, 'Missing subject must fail');
foreach (['', 'broken', 'a.b.c', 'a.b.c.d'] as $bad) check(jwt_decode($bad, $secret) === null, 'Malformed token must fail');
echo "Token validation checks passed.\n";
