<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Model\Config\Source;

use Superpayments\SuperPayment\Gateway\Config\Config;

class ModuleVersion
{
    /** @var Config */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function execute(): string
    {
        return $this->config->getModuleVersion();
    }
}
