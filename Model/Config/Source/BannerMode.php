<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Model\Config\Source;

class BannerMode
{
    public const NONE = 'none';
    public const HOMEPAGE = 'homepage';
    public const ALLPAGES = 'allpages';

    public function toOptionArray(): array
    {
        return [
            ['value' => self::NONE, 'label' => __('None')],
            ['value' => self::HOMEPAGE, 'label' => __('Home Page')],
            ['value' => self::ALLPAGES, 'label' => __('All Pages')],
        ];
    }

    public function toArray(): array
    {
        return [
            self::NONE => __('None'),
            self::HOMEPAGE => __('Home Page'),
            self::ALLPAGES => __('All Pages'),
        ];
    }
}
