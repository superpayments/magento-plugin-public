<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Block\Adminhtml\Order\Creditmemo;

use Magento\Directory\Model\Currency;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class Totals extends Template
{
    /**
     * @var Context $context
     */
    protected $context;
    /**
     * @var  OrderRepositoryInterface $orderRepository
     */
    protected $orderRepository;

    public function __construct(
        Context $context,
        OrderRepositoryInterface $orderRepository,
        array $data = []
    ) {
        $this->orderRepository = $orderRepository;
        parent::__construct($context, $data);
    }

    /**
     * Retrieve current order model instance
     *
     */
    public function getOrder(): ?Order
    {
        return $this->getParentBlock()->getOrder();
    }

    /**
     *
     * @return $this
     *
     */
    public function initTotals()
    {
        $parent = $this->getParentBlock();
        $order = $this->getOrder();
        $order_id = $order->getId();
        $payment = $order->getPayment();

        if ($payment->getMethod() == 'super_payment_gateway') {
            $additionalInformation = $payment->getAdditionalInformation();
            if (!isset($additionalInformation['transactionAmount'])) {
                return $this;
            }
            if ($additionalInformation['grossAmount'] <= $additionalInformation['transactionAmount']) {
                return $this;
            }
            $savingAmount = ($additionalInformation['grossAmount'] - $additionalInformation['transactionAmount']) / 100;

            $total = new DataObject(
                [
                    'code' => 'instant_discount',
                    'value' => '- ' . $savingAmount,
                    'label' => 'Super Cash Reward',
                ]
            );
            $parent->addTotalBefore($total, 'grand_total');
        }
        return $this;
    }
}
