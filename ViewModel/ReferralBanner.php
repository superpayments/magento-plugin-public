<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\ViewModel;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Config\Config;
use Superpayments\SuperPayment\Gateway\Service\ApiServiceInterface;
use Throwable;

class ReferralBanner implements ArgumentInterface
{
    /** @var ScopeConfigInterface $scopeConfig */
    protected $scopeConfig;

    /** @var StoreManagerInterface $storeManager */
    private $storeManager;

    /** @var Http $request */
    private $request;

    /** @var ApiServiceInterface $apiService */
    private $apiService;

    /** @var Session $checkoutSession */
    private $checkoutSession;

    /** @var LoggerInterface */
    private $logger;

    /** @var OrderFactory */
    private $orderFactory;

    /** @var Order */
    private $order;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        Http $request,
        ApiServiceInterface $apiService,
        Session $checkoutSession,
        LoggerInterface $logger,
        OrderFactory $orderFactory,
        Order $order
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->request = $request;
        $this->apiService = $apiService;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        $this->orderFactory = $orderFactory;
        $this->order = $order;
    }

    public function getConfirmationPageBanner(): ?bool
    {
        return $this->scopeConfig->isSetFlag(
            'payment/super_payment_gateway/confirmation_page_banner',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getStoreId()
        );
    }

    public function getConfirmationEmailBanner(): ?bool
    {
        return $this->scopeConfig->isSetFlag(
            'payment/super_payment_gateway/confirmation_email_banner',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getStoreId()
        );
    }

    public function getIsOrderConfirmationPage(): ?bool
    {
        if ($this->request->getFullActionName() == 'checkout_onepage_success') {
            return true;
        } else {
            return false;
        }
    }

    public function getBanner(): array
    {
        $result = ['result' => 'success'];

        try {
            $order = $this->checkoutSession->getLastRealOrder();
            $data = [
                'order' => $order,
            ];
            $response = $this->apiService->execute($data);

            $data = $response->getData();
            if (is_array($data)) {
                $result = array_merge($result, $data);
            }
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            $result = ['result' => 'error', 'exception' => $e->getMessage()];
        }
        return $result;
    }

    public function getEBanner(): array
    {
        $result = ['result' => 'success'];

        try {
            $orderData = $this->order->getCollection()->getLastItem();
            $orderId = $orderData->getId();
            $order = $this->order->load($orderId);
            $data = [
                'order' => $order,
            ];
            $response = $this->apiService->execute($data);

            $data = $response->getData();

            if (is_array($data)) {
                $result = array_merge($result, $data);
            }
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            $result = ['result' => 'error', 'exception' => $e->getMessage()];
        }
        return $result;
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
