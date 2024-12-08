<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Model\Config\Source;

class Environment
{
    public const SANDBOX = 'sandbox';
    public const PRODUCTION = 'production';

    public function toOptionArray(): array
    {
        return [
            ['value' => self::SANDBOX, 'label' => __('Sandbox')],
            ['value' => self::PRODUCTION, 'label' => __('Production')],
        ];
    }

    public function toArray(): array
    {
        return [
            self::SANDBOX => __('Sandbox'),
            self::PRODUCTION => __('Production'),
        ];
    }
}
