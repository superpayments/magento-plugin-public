<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Block;

use Magento\Framework\Phrase;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Block\ConfigurableInfo;
use Magento\Payment\Gateway\ConfigInterface;

class Info extends ConfigurableInfo
{
    /** @var string */
    protected $_template = 'Superpayments_SuperPayment::payment/info.phtml';

    /**
     * @inheritdoc
     */
    protected function getLabel($field): Phrase
    {
        return __($field);
    }
}
