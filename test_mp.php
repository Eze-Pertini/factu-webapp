<?php
require_once 'includes/db.php';

$db   = getDB();
$stmt = $db->prepare("SELECT mp_access_token, mp_ambiente FROM configuracion WHERE usuario_id = 1");
$stmt->execute();
$config = $stmt->fetch();

echo "Token: " . substr($config['mp_access_token'], 0, 20) . "...<br>";
echo "Ambiente: " . $config['mp_ambiente'] . "<br><br>";

$token = $config['mp_access_token'];

$ch = curl_init('https://api.mercadopago.com/users/me');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => ["Authorization: Bearer $token"],
    CURLOPT_SSL_VERIFYPEER => true,
]);
$res      = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode<br>";
echo "cURL error: $error<br>";
echo "Respuesta: <pre>$res</pre>";
?>