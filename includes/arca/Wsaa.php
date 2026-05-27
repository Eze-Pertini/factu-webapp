<?php
// ================================================
// includes/arca/Wsaa.php — Autenticación ARCA
// Web Service de Autenticación y Autorización
// ================================================

class Wsaa {

    const URL_PROD = 'https://wsaa.afip.gov.ar/ws/services/LoginCms?wsdl';
    const URL_TEST = 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms?wsdl';

    private string $certFile;
    private string $keyFile;
    private string $ticketFile;
    private bool   $produccion;

    public function __construct(
        string $certFile,
        string $keyFile,
        string $ticketDir,
        bool   $produccion = true
    ) {
        $this->certFile   = $certFile;
        $this->keyFile    = $keyFile;
        $this->ticketFile = rtrim($ticketDir, '/\\') . DIRECTORY_SEPARATOR . 'ticket_wsfe.json';
        $this->produccion = $produccion;
    }

    public function obtenerTicket(): array {
        if (file_exists($this->ticketFile)) {
            $cached = json_decode(file_get_contents($this->ticketFile), true);
            if ($cached && strtotime($cached['expiracion']) > time() + 60) {
                return $cached;
            }
        }
        $ticket = $this->loginCms();
        file_put_contents($this->ticketFile, json_encode($ticket));
        return $ticket;
    }

    private function loginCms(): array {
        $service    = 'wsfe';
        $uniqueId   = time();
        $generacion = date('c', time() - 60);
        $expiracion = date('c', time() + 36000);

        $tra = '<?xml version="1.0" encoding="UTF-8"?>
<loginTicketRequest version="1.0">
  <header>
    <uniqueId>' . $uniqueId . '</uniqueId>
    <generationTime>' . $generacion . '</generationTime>
    <expirationTime>' . $expiracion . '</expirationTime>
  </header>
  <service>' . $service . '</service>
</loginTicketRequest>';

        $cms = $this->firmarCms($tra);

        $url    = $this->produccion ? self::URL_PROD : self::URL_TEST;
        $client = new SoapClient($url, [
            'soap_version'   => SOAP_1_2,
            'trace'          => false,
            'exceptions'     => true,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer'      => false,
                    'verify_peer_name' => false,
                ]
            ])
        ]);

        $result         = $client->loginCms(['in0' => $cms]);
        $loginCmsReturn = $result->loginCmsReturn ?? null;

        if (!$loginCmsReturn) {
            throw new Exception('WSAA no devolvió respuesta válida.');
        }

        $xml   = simplexml_load_string($loginCmsReturn);
        $token = (string)$xml->credentials->token;
        $sign  = (string)$xml->credentials->sign;
        $exp   = (string)$xml->header->expirationTime;

        if (!$token || !$sign) {
            throw new Exception('WSAA no devolvió token/sign válidos.');
        }

        return [
            'token'      => $token,
            'sign'       => $sign,
            'expiracion' => $exp,
        ];
    }

    private function firmarCms(string $tra): string {
        $traFile = tempnam(sys_get_temp_dir(), 'tra_') . '.xml';
        $cmsFile = tempnam(sys_get_temp_dir(), 'cms_') . '.pem';

        file_put_contents($traFile, $tra);

        // Usar openssl cms -sign con nodetach para incluir contenido + firma juntos
        $opensslPath = 'C:\\Program Files\\Git\\usr\\bin\\openssl.exe';

        $cmd = sprintf(
            '"%s" cms -sign -in "%s" -out "%s" -signer "%s" -inkey "%s" -nodetach -outform PEM 2>&1',
            $opensslPath,
            $traFile,
            $cmsFile,
            $this->certFile,
            $this->keyFile
        );

        $output = [];
        $retval = 0;
        exec($cmd, $output, $retval);

        if ($retval !== 0 || !file_exists($cmsFile)) {
            @unlink($traFile);
            @unlink($cmsFile);
            throw new Exception('Error ejecutando openssl cms: ' . implode(' | ', $output));
        }

        $contenido = file_get_contents($cmsFile);
        @unlink($traFile);
        @unlink($cmsFile);

        // Extraer Base64 entre -----BEGIN CMS----- y -----END CMS-----
        if (!preg_match('/-----BEGIN CMS-----(.+?)-----END CMS-----/s', $contenido, $matches)) {
            throw new Exception('No se encontró bloque CMS en la salida. Contenido: ' . substr($contenido, 0, 200));
        }

        $cms = trim(str_replace(["\r", "\n", " "], '', $matches[1]));

        if (empty($cms)) {
            throw new Exception('El bloque CMS está vacío.');
        }

        return $cms;
    }
}
