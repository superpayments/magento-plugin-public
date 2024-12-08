<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Service;

use Magento\Framework\DataObject;

interface ApiServiceInterface
{
    public function execute(array $subject): DataObject;
}
