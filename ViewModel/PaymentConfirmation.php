<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\ViewModel;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Config\Config;
use Superpayments\SuperPayment\Gateway\Service\ApiServiceInterface;
use Throwable;

class PaymentConfirmation implements ArgumentInterface
{
    /** @var ScopeConfigInterface $scopeConfig */
    protected $scopeConfig;

    /** @var Config $config */
    private $config;

    /** @var StoreManagerInterface $storeManager */
    private $storeManager;

    /** @var Http $request */
    private $request;

    /** @var Session $checkoutSession */
    private $checkoutSession;

    /** @var LoggerInterface */
    private $logger;

    /** @var ApiServiceInterface $apiService */
    private $apiService;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        Http $request,
        Session $checkoutSession,
        Config $config,
        ApiServiceInterface $apiService,
        LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->request = $request;
        $this->checkoutSession = $checkoutSession;
        $this->apiService = $apiService;
        $this->logger = $logger;
        $this->config = $config;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getPaymentJsUrl(): string
    {
        return $this->config->getPaymentJsUrl();
    }

    public function shouldUseSecureRenderer(): bool
    {
        $magentoVersion = $this->config->getMagentoVersion();
        if (empty($magentoVersion)) {
            return true;
        }
        return version_compare($magentoVersion, '2.4.4', '>=');
    }

    public function getPublishableKey(): ?string
    {
        $this->config->setStoreId((int) $this->storeManager->getStore()->getId());
        return $this->config->getPublishableKey();
    }

    public function getStoreCurrencyCode(): ?string
    {
        return $this->storeManager->getStore()->getCurrentCurrencyCode();
    }

    public function getPaymentConfirmationData(): ?array
    {
        return [
            'paymentIntentId' => $this->getPaymentIntentId(),
            'customerEmail' => $this->getCustomerEmail(),
            'emailGuestToken' => $this->getGuestEmailToken(),
            'publishableApiKey' => $this->getPublishableKey(),
        ];
    }

    private function getPaymentIntentId(): ?string
    {
        $order = $this->checkoutSession->getLastRealOrder();
        if ($order && $order->getId() && $order->getIncrementId()) {
            if ($paymentIntentId = $order->getPayment()->getAdditionalInformation('paymentIntentId')) {
                return (string) $paymentIntentId;
            }
        }
        return null;
    }

    private function getCustomerEmail(): ?string
    {
        $order = $this->checkoutSession->getLastRealOrder();
        if ($order && $order->getId() && $order->getCustomerEmail()) {
            return (string) $order->getCustomerEmail();
        }
        return null;
    }

    private function getGuestEmailToken(): ?string
    {
        $order = $this->checkoutSession->getLastRealOrder();
        $result = $this->apiService->execute([
            'paymentIntentId' => $this->getPaymentIntentId(),
            'store' => $this->storeManager->getStore($order->getStoreId()),
        ]);
        return $result->getData('guestToken') ?? null;
    }

    public function isSuperPaymentOrder(): bool
    {
        $result = false;

        try {
            $order = $this->checkoutSession->getLastRealOrder();
            if ($order && $order->getId() && $order->getPayment()->getMethod() == Config::PAYMENT_CODE) {
                $result = true;
            }
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
        }

        return $result;
    }
}
