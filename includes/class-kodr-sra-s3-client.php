<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Minimal AWS Signature Version 4 S3 PUT client used by version 0.1.
 * It uploads only; it cannot read or delete objects.
 */
final class Kodr_SRA_S3_Client
{
    private string $region;
    private string $bucket;
    private string $accessKey;
    private string $secretKey;

    /** @param array<string,mixed> $config */
    public function __construct(array $config)
    {
        $this->region = (string) $config['region'];
        $this->bucket = (string) $config['bucket'];
        $this->accessKey = (string) $config['access_key_id'];
        $this->secretKey = (string) $config['secret_key'];
    }

    /** @return array{etag:string,status:int,key:string} */
    public function put_object(string $key, string $body, string $contentType = 'application/octet-stream'): array
    {
        $key = ltrim($key, '/');
        if ($key === '') {
            throw new InvalidArgumentException('The S3 object key cannot be empty.');
        }

        $host = $this->bucket . '.s3.' . $this->region . '.amazonaws.com';
        $encodedKey = implode('/', array_map('rawurlencode', explode('/', $key)));
        $uri = '/' . $encodedKey;
        $endpoint = 'https://' . $host . $uri;

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $amzDate = $now->format('Ymd\\THis\\Z');
        $dateStamp = $now->format('Ymd');
        $payloadHash = hash('sha256', $body);

        $canonicalHeaders =
            'content-type:' . trim($contentType) . "\n" .
            'host:' . $host . "\n" .
            'x-amz-content-sha256:' . $payloadHash . "\n" .
            'x-amz-date:' . $amzDate . "\n";
        $signedHeaders = 'content-type;host;x-amz-content-sha256;x-amz-date';
        $canonicalRequest = "PUT\n{$uri}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";

        $scope = $dateStamp . '/' . $this->region . '/s3/aws4_request';
        $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$scope}\n" . hash('sha256', $canonicalRequest);
        $signingKey = $this->signature_key($dateStamp);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);
        $authorization = 'AWS4-HMAC-SHA256 Credential=' . $this->accessKey . '/' . $scope
            . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;

        $response = wp_remote_request($endpoint, [
            'method'      => 'PUT',
            'timeout'     => 20,
            'redirection' => 0,
            'headers'     => [
                'Authorization'         => $authorization,
                'Content-Type'          => $contentType,
                'Host'                  => $host,
                'X-Amz-Content-Sha256'  => $payloadHash,
                'X-Amz-Date'            => $amzDate,
            ],
            'body'        => $body,
            'data_format' => 'body',
        ]);

        if (is_wp_error($response)) {
            throw new RuntimeException('S3 request failed: ' . $response->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            $responseBody = trim((string) wp_remote_retrieve_body($response));
            $safeBody = $responseBody === '' ? '' : ' ' . wp_strip_all_tags(substr($responseBody, 0, 500));
            throw new RuntimeException('S3 returned HTTP ' . $status . '.' . $safeBody);
        }

        $etag = trim((string) wp_remote_retrieve_header($response, 'etag'), '"');
        return ['etag' => $etag, 'status' => $status, 'key' => $key];
    }

    private function signature_key(string $dateStamp): string
    {
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }
}
