<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class CheckoutSession extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('superpayments_session', 'entity_id');
    }
}
