<?php
/**
 * ============================================================
 *  GoDaddy API Wrapper — Doctores.Digital
 * ============================================================
 *  Métodos:
 *    checkAvailability(string $domain)  → [available, price, currency]
 *    checkMultiple(array $domains)      → array de resultados
 *    suggest(string $query)             → dominios sugeridos
 *    getAgreementKeys(string $tld)      → claves de acuerdo legal
 *    purchase(array $params)            → compra el dominio
 * ============================================================
 */

require_once __DIR__ . '/../config/godaddy.php';

class GoDaddy
{
    private string $key;
    private string $secret;
    private string $baseUrl;

    public function __construct()
    {
        $this->key    = GODADDY_KEY;
        $this->secret = GODADDY_SECRET;
        $this->baseUrl = GODADDY_PRODUCTION
            ? 'https://api.godaddy.com/v1'
            : 'https://api.ote-godaddy.com/v1';
    }

    // ── Petición genérica ────────────────────────────────────
    private function request(string $method, string $path, array $body = []): array
    {
        $url = $this->baseUrl . $path;
        $ch  = curl_init($url);

        $headers = [
            'Authorization: sso-key ' . $this->key . ':' . $this->secret,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        } elseif ($method === 'GET' && !empty($body)) {
            $url .= '?' . http_build_query($body);
            curl_setopt($ch, CURLOPT_URL, $url);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['error' => 'cURL error: ' . $error, 'code' => 0];
        }

        $data = json_decode($response, true) ?? [];
        $data['_http_code'] = $httpCode;
        return $data;
    }

    // ── Verificar disponibilidad de UN dominio ───────────────
    public function checkAvailability(string $domain): array
    {
        // Nota: NO pasar forTransfer como bool — http_build_query lo convierte en ""
        $result = $this->request('GET', '/domains/available', [
            'domain'    => $domain,
            'checkType' => 'FAST',
        ]);

        return [
            'domain'    => $domain,
            'available' => $result['available'] ?? false,
            'price'     => isset($result['price']) ? $result['price'] / 1000000 : null,
            'currency'  => $result['currency'] ?? 'USD',
            'period'    => $result['period'] ?? 1,
            'error'     => $result['message'] ?? ($result['error'] ?? null),
        ];
    }

    // ── Verificar disponibilidad de VARIOS dominios ──────────
    public function checkMultiple(array $domains): array
    {
        // POST con array JSON de strings: ["domain1.com","domain2.mx"]
        $result = $this->request('POST', '/domains/available?checkType=FAST', $domains);

        // GoDaddy puede responder como array directo o con clave 'domains'
        if (isset($result[0]['domain'])) {
            $items = $result;
        } elseif (isset($result['domains'])) {
            $items = $result['domains'];
        } else {
            return []; // fallback a checkAvailability individual
        }

        $output = [];
        foreach ($items as $item) {
            $output[] = [
                'domain'    => $item['domain']    ?? '',
                'available' => $item['available'] ?? false,
                'price'     => isset($item['price']) ? $item['price'] / 1000000 : null,
                'currency'  => $item['currency']  ?? 'USD',
                'period'    => $item['period']    ?? 1,
            ];
        }

        return $output;
    }

    // ── Sugerencias de dominio basadas en keyword ────────────
    public function suggest(string $query, int $limit = 8): array
    {
        $tlds   = implode(',', GODADDY_TLDS);
        $result = $this->request('GET', '/domains/suggest', [
            'query'    => $query,
            'tlds'     => $tlds,
            'limit'    => $limit,
            'country'  => 'MX',
            'city'     => 'Mexico City',
        ]);

        if (!is_array($result) || isset($result['error'])) {
            return [];
        }

        // Si la respuesta es directamente el array de sugerencias
        $items = isset($result[0]) ? $result : ($result['domains'] ?? []);

        return array_map(fn($d) => $d['domain'] ?? $d, $items);
    }

    // ── Obtener claves de acuerdo legal para un TLD ──────────
    public function getAgreementKeys(string $tld): array
    {
        $result = $this->request('GET', '/domains/agreements', [
            'tlds'        => $tld,
            'privacy'     => false,
            'forTransfer' => false,
        ]);

        if (!is_array($result) || isset($result['error'])) {
            return ['DNRA'];
        }

        return array_column(
            isset($result[0]) ? $result : ($result['agreements'] ?? []),
            'agreementKey'
        ) ?: ['DNRA'];
    }

    // ── Comprar/Registrar un dominio ─────────────────────────
    public function purchase(
        string $domain,
        array  $contact,
        int    $years    = 1,
        bool   $privacy  = false,
        string $agreedBy = '127.0.0.1'
    ): array {
        // Obtener claves de acuerdo según el TLD
        $tld            = ltrim(strstr($domain, '.'), '.');
        $agreementKeys  = $this->getAgreementKeys($tld);

        $payload = [
            'domain'  => $domain,
            'period'  => $years,
            'renewAuto' => true,
            'privacy' => $privacy,
            'consent' => [
                'agreedAt'      => gmdate('Y-m-d\TH:i:s\Z'),
                'agreedBy'      => $agreedBy,
                'agreementKeys' => $agreementKeys,
            ],
            'contactAdmin'      => $contact,
            'contactBilling'    => $contact,
            'contactRegistrant' => $contact,
            'contactTech'       => $contact,
        ];

        $result = $this->request('POST', '/domains/purchase', $payload);

        $success = isset($result['orderId']) || ($result['_http_code'] ?? 0) === 200;

        return [
            'success'  => $success,
            'orderId'  => $result['orderId']  ?? null,
            'domain'   => $domain,
            'message'  => $result['message']  ?? ($success ? 'Dominio registrado' : 'Error al registrar'),
            'raw'      => $result,
        ];
    }
}
