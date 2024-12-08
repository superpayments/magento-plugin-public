<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Block\Checkout;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Superpayments\SuperPayment\ViewModel\ReferralBanner;

class EmailMarketing extends Template
{
    /** @var ReferralBanner $marketingBanner */
    private $referralBanner;

    public function __construct(
        ReferralBanner $referralBanner,
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->referralBanner = $referralBanner;
    }

    public function getViewModel(): ReferralBanner
    {
        return $this->referralBanner;
    }
}
