<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Model;

use Magento\Framework\Model\AbstractModel;
use Superpayments\SuperPayment\Model\ResourceModel\CheckoutSession as ResourceModel;

class CheckoutSession extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(ResourceModel::class);
    }
}
