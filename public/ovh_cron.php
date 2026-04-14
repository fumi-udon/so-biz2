<?php
// 【確証用ログ】OVHがこのファイルを叩いた瞬間に足跡を残す
$logMessage = '[' . date('Y-m-d H:i:s') . '] OVH Cron Triggered ovh_cron.php' . PHP_EOL;
file_put_contents(__DIR__.'/../storage/logs/ovh_cron_bridge.log', $logMessage, FILE_APPEND);

// OVHのCronから直接自動退勤コマンドを呼び出すブリッジファイル
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->handle(
    new Symfony\Component\Console\Input\StringInput('app:auto-clock-out'),
    new Symfony\Component\Console\Output\ConsoleOutput()
);
