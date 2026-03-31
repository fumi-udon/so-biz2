<?php

namespace App\Traits;

use Filament\Resources\Pages\CreateRecord;

/**
 * Filament CreateRecord / EditRecord で保存・作成後にリソースの index へ遷移する。
 * 一覧へ戻したくないページでは本 Trait を use しない。
 */
trait RedirectsToIndex
{
    /**
     * CreateRecord では getRedirectUrlParameters() を index URL に引き継ぐ。
     */
    protected function getRedirectUrl(): string
    {
        $resource = static::getResource();

        $params = $this instanceof CreateRecord
            ? $this->getRedirectUrlParameters()
            : [];

        return $resource::getUrl('index', $params);
    }
}
