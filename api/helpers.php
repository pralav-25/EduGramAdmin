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

function base64url_decode($value){
    $padding = strlen($value) % 4;
    if ($padding) $value .= str_repeat('=', 4 - $padding);
    return base64_decode(strtr($value, '-_', '+/'), true);
}

function jwt_decode($token, $secret){
    $parts = explode('.', $token);
    if(count($parts)!==3) return null;
    list($h64,$p64,$s64) = $parts;
    $headerJson = base64url_decode($h64);
    $payloadJson = base64url_decode($p64);
    $sig = base64url_decode($s64);
    if ($headerJson === false || $payloadJson === false || $sig === false) return null;
    $header = json_decode($headerJson, true);
    if (!is_array($header) || ($header['alg'] ?? null) !== 'HS256' || ($header['typ'] ?? null) !== 'JWT') return null;
    $expected = hash_hmac('sha256', "$h64.$p64", $secret, true);
    if(!hash_equals($expected, $sig)) return null;
    $payload = json_decode($payloadJson, true);
    if (!is_array($payload) || !isset($payload['exp']) || !is_numeric($payload['exp'])) return null;
    if ((int)$payload['exp'] <= time()) return null;
    if (isset($payload['nbf']) && is_numeric($payload['nbf']) && (int)$payload['nbf'] > time()) return null;
    return $payload;
}

function get_bearer_token(){
    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if(preg_match('/Bearer\s+(.*)$/i', $hdr, $m)) return $m[1];
    return null;
}
