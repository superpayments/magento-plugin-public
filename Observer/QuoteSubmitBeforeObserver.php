<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Config\Config;
use Superpayments\SuperPayment\Gateway\Service\ApiServiceInterface;
use Superpayments\SuperPayment\Model\PriceConverter;
use Throwable;

class QuoteSubmitBeforeObserver implements ObserverInterface
{
    /** @var ApiServiceInterface $apiService */
    private $apiService;
    /** @var Config */
    private $config;
    /** @var PriceConverter $priceConverter */
    private $priceConverter;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        ApiServiceInterface $apiService,
        Config $config,
        PriceConverter $priceConverter,
        LoggerInterface $logger
    ) {
        $this->apiService = $apiService;
        $this->config = $config;
        $this->priceConverter = $priceConverter;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        try {
            if (!$this->config->isActive()) {
                return $this;
            }

            $event = $observer->getEvent();
            /** @var OrderInterface $order */
            $order = $event->getOrder();
            /** @var CartInterface $quote */
            $quote = $event->getQuote();

            $data = [
                'quote' => $quote,
                'page' => 'Checkout',
                'output' => 'calculation',
                'response' => [],
            ];

            $response = $this->apiService->execute($data);
            $order->getPayment()->setAdditionalInformation(
                'superpaymentsOfferId',
                $response->getData('id')
            );

            if ($order->getPayment()->getMethod() == Config::PAYMENT_CODE) {
                $order->addCommentToStatusHistory('Reward Calculation Id: ' . $response->getData('id'));
                $quote->collectTotals();
                $order->getPayment()->setAdditionalInformation(
                    'grossAmount',
                    $this->priceConverter->minorUnitAmount($quote->getGrandTotal())
                );
            }
        } catch (Throwable $e) {
            $this->logger->error(
                '[SuperPayments] QuoteSubmitBeforeObserver ' . $e->getMessage() ."\n". $e->getTraceAsString()
            );
        }
        return $this;
    }
}
