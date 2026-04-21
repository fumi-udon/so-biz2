<?php

namespace App\Support\Pos;

use App\Models\ShopPrinterSetting;

/**
 * Resolves browser-side POS printer config from DB (SSOT) with config fallbacks.
 *
 * @phpstan-type DeviceConfig array{
 *   shop_id:int,
 *   printer_ip:string,
 *   printer_port:string,
 *   device_id:string,
 *   crypto:bool,
 *   timeout_ms:int
 * }
 * @phpstan-type JsConfig array{
 *   driver:string,
 *   url:string,
 *   timeoutMs:int,
 *   shop_id:int,
 *   printer_ip:string,
 *   printer_port:string,
 *   device_id:string,
 *   crypto:bool
 * }
 */
final class PosPrinterClientConfig
{
    /**
     * Full payload for diagnostics UI and optional extra keys for debugging.
     *
     * @return DeviceConfig&JsConfig
     */
    public static function resolveForShopId(int $shopId): array
    {
        $defaults = (array) config('pos_printer.defaults', []);

        $ip = (string) ($defaults['printer_ip'] ?? '192.168.1.200');
        $port = (string) ($defaults['printer_port'] ?? '8043');
        $deviceId = (string) ($defaults['device_id'] ?? 'local_printer');
        $crypto = (bool) ($defaults['crypto'] ?? true);
        $timeoutMs = (int) ($defaults['timeout_ms'] ?? 10_000);

        if ($shopId > 0) {
            $row = ShopPrinterSetting::query()->where('shop_id', $shopId)->first();
            if ($row !== null) {
                $ip = (string) $row->printer_ip;
                $port = (string) $row->printer_port;
                $deviceId = (string) $row->device_id;
                $crypto = (bool) $row->crypto;
                $timeoutMs = (int) $row->timeout_ms;
            }
        }

        $url = self::buildServiceUrl($ip, $port, $deviceId, $timeoutMs, $crypto);

        return [
            'shop_id' => $shopId,
            'driver' => 'epson',
            'url' => $url,
            'timeoutMs' => $timeoutMs,
            'timeout_ms' => $timeoutMs,
            'printer_ip' => $ip,
            'printer_port' => $port,
            'device_id' => $deviceId,
            'crypto' => $crypto,
        ];
    }

    /**
     * Minimal object for {@see window.posPrinterConfig} (EpsonHttpPrinter / PrinterFactory).
     *
     * @return array{driver:string,url:string,timeoutMs:int}
     */
    public static function forPrinterFactory(int $shopId): array
    {
        $full = self::resolveForShopId($shopId);

        return [
            'driver' => 'epson',
            'url' => (string) $full['url'],
            'timeoutMs' => (int) $full['timeoutMs'],
        ];
    }

    public static function buildServiceUrl(
        string $ip,
        string $port,
        string $deviceId,
        int $timeoutMs,
        bool $crypto,
    ): string {
        $scheme = $crypto ? 'https' : 'http';
        $timeoutMs = max(1000, min(300_000, $timeoutMs));

        return sprintf(
            '%s://%s:%s/cgi-bin/epos/service.cgi?devid=%s&timeout=%d',
            $scheme,
            $ip,
            $port,
            rawurlencode($deviceId),
            $timeoutMs
        );
    }
}
