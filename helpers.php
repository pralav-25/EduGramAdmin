<?php
// helpers.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// Simple JWT implementation (HS256) for demo only. Use a library in production.
function jwt_encode($payload, $secret){
    $header = ['alg'=>'HS256','typ'=>'JWT'];
    $b64 = function($data){ return rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '='); };
    $header_b64 = $b64($header);
    $payload_b64 = $b64($payload);
    $sig = hash_hmac('sha256', "$header_b64.$payload_b64", $secret, true);
    $sig_b64 = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
    return "$header_b64.$payload_b64.$sig_b64";
}

function jwt_decode($token, $secret){
    $parts = explode('.', $token);
    if(count($parts)!==3) return null;
    list($h64,$p64,$s64) = $parts;
    $sig = base64_decode(strtr($s64, '-_', '+/'));
    $expected = hash_hmac('sha256', "$h64.$p64", $secret, true);
    if(!hash_equals($expected, $sig)) return null;
    $payload = json_decode(base64_decode(strtr($p64,'-_','+/')), true);
    return $payload;
}

function get_bearer_token(){
    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if(preg_match('/Bearer\s+(.*)$/i', $hdr, $m)) return $m[1];
    return null;
}

