<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Model\CheckoutSession as CheckoutSessionModel;
use Superpayments\SuperPayment\Model\ResourceModel\CheckoutSession as CheckoutSessionResource;
use Throwable;

class CheckoutSessionRepository
{
    /** @var CheckoutSessionFactory */
    private $checkoutSessionFactory;

    /** @var CheckoutSessionResource */
    private $checkoutSessionResource;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        CheckoutSessionFactory $checkoutSessionFactory,
        CheckoutSessionResource $checkoutSessionResource,
        LoggerInterface $logger
    ) {
        $this->checkoutSessionFactory = $checkoutSessionFactory;
        $this->checkoutSessionResource = $checkoutSessionResource;
        $this->logger = $logger;
    }

    /**
     * Get the record by quote
     *
     * @param int $quoteId
     * @throws NoSuchEntityException
     */
    public function getByQuoteId($quoteId): CheckoutSessionModel
    {
        $checkoutSession = $this->checkoutSessionFactory->create();
        $this->checkoutSessionResource->load($checkoutSession, $quoteId, 'quote_id');

        if (!$checkoutSession->getId()) {
            throw new NoSuchEntityException(__('No Super Checkout Session ID found for Quote ID %1', $quoteId));
        }

        return $checkoutSession;
    }

    /**
     * Save or update the record by quote_id
     *
     * @param int $quoteId
     * @param string $superCheckoutSessionId
     */
    public function saveOrUpdate($quoteId, $superCheckoutSessionId): bool
    {
        try {
            try {
                $checkoutSession = $this->getByQuoteId($quoteId);
            } catch (NoSuchEntityException $e) {
                $checkoutSession = $this->checkoutSessionFactory->create();
                $checkoutSession->setData('quote_id', $quoteId);
            }
            $checkoutSession->setData('checkout_session_id', $superCheckoutSessionId);
            $this->checkoutSessionResource->save($checkoutSession);
        } catch (Throwable $e) {
            $this->logger->critical(
                'Superpayments: Unable to save superCheckoutSessionId to db: ' . $e->getMessage()
            );
            $this->logger->critical($e->getTraceAsString());
            return false;
        }

        return true;
    }
}
