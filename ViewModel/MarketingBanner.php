<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\ViewModel;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Config\Config;
use Superpayments\SuperPayment\Model\PriceConverter;
use Throwable;

class MarketingBanner implements ArgumentInterface
{
    /** @var ScopeConfigInterface */
    protected $scopeConfig;

    /** @var Config */
    private $config;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var Http */
    private $request;

    /** @var Session */
    private $checkoutSession;

    /** @var PriceConverter */
    private $priceConverter;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        Http $request,
        Session $checkoutSession,
        Config $config,
        PriceConverter $priceConverter,
        LoggerInterface $logger
    ) {
        $this->scopeConfig     = $scopeConfig;
        $this->storeManager    = $storeManager;
        $this->request         = $request;
        $this->checkoutSession = $checkoutSession;
        $this->logger          = $logger;
        $this->config          = $config;
        $this->priceConverter  = $priceConverter;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getPriceConverter(): PriceConverter
    {
        return $this->priceConverter;
    }

    public function getCdnUrl(): string
    {
        return $this->config->getCdnUrl();
    }

    public function getPublisherJsUrl(): string
    {
        return $this->config->getPublisherJsUrl();
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
        $this->config->setStoreId((int)$this->storeManager->getStore()->getId());
        return $this->config->getPublishableKey();
    }

    public function getIntegrationId(): ?string
    {
        $this->config->setStoreId((int)$this->storeManager->getStore()->getId());
        return $this->config->getIntegrationId();
    }

    public function getBrandId(): ?string
    {
        $this->config->setStoreId((int)$this->storeManager->getStore()->getId());
        return $this->config->getBrandId();
    }

    public function getStoreCurrencyCode(): ?string
    {
        return $this->storeManager->getStore()->getCurrentCurrencyCode();
    }

    public function getBannerMode(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/super_payment_gateway/banner_mode',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getStoreId()
        );
    }

    public function getPlpBanner(): ?bool
    {
        return $this->scopeConfig->isSetFlag(
            'payment/super_payment_gateway/plp_banner',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getStoreId()
        );
    }

    public function getPdpBanner(): ?bool
    {
        return $this->scopeConfig->isSetFlag(
            'payment/super_payment_gateway/pdp_banner',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getStoreId()
        );
    }

    public function getCartBanner(): ?bool
    {
        return $this->scopeConfig->isSetFlag(
            'payment/super_payment_gateway/cart_banner',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getStoreId()
        );
    }

    public function getOrderConfirmationBanner(): ?bool
    {
        return $this->scopeConfig->isSetFlag(
            'payment/super_payment_gateway/confirmation_page_web_component',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getStoreId()
        );
    }

    public function getPublishersPostCheckoutBanner(): ?bool
    {
        return $this->scopeConfig->isSetFlag(
            'payment/super_payment_gateway/publisher_post_checkout_banner',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getStoreId()
        );
    }

    public function getIsHomePage(): ?bool
    {
        return $this->request->getFullActionName() === 'cms_index_index';
    }

    public function getIsCheckout(): ?bool
    {
        return $this->request->getFullActionName() === 'checkout_index_index';
    }

    public function getIsPLP(): ?bool
    {
        return $this->request->getFullActionName() === 'catalog_category_view';
    }

    public function getIsPDP(): ?bool
    {
        return $this->request->getFullActionName() === 'catalog_product_view';
    }

    public function getIsCart(): ?bool
    {
        return $this->request->getFullActionName() === 'checkout_cart_index';
    }

    public function getIsOrderConfirmationPage(): ?bool
    {
        return $this->request->getFullActionName() === 'checkout_onepage_success';
    }

    public function getPage(): string
    {
        if ($this->getIsHomePage()) {
            $page = 'home';
        } elseif ($this->getIsCheckout()) {
            $page = 'checkout';
        } elseif ($this->getIsPLP()) {
            $page = 'product-listing';
        } elseif ($this->getIsPDP()) {
            $page = 'product-detail';
        } elseif ($this->getIsCart()) {
            $page = 'cart';
        } elseif ($this->getIsOrderConfirmationPage()) {
            $page = 'order-confirmation';
        } else {
            $page = 'unknown';
        }
        return $page;
    }

    public function getWebComponentData(
        ?string $currentPage = null,
        ?bool $includeCartId = false,
        ?bool $includeOrderData = false
    ): ?array {
        /** @var CartInterface $quote */
        $quote = $this->checkoutSession->getQuote();

        $page = $currentPage ?? $this->getPage();
        if (in_array($page, ['cart', 'checkout'])) {
            $quote->collectTotals();
        }

        $minorUnitAmount = $this->priceConverter->minorUnitAmount($quote->getGrandTotal());
        $minorForReturn = $includeCartId ? ($minorUnitAmount ?: 0) : 0;

        $pageLower = strtolower($page ?: 'Checkout');

        $quoteId = $includeCartId
            ? ($quote->getId() ?: ('unknown-' . time()))
            : 'unknown';

        $items = $includeCartId ? $this->getWebComponentDataCartItems() : [];

        return [
            'minorUnitAmount'     => $minorForReturn,
            'page'                => $pageLower,
            'cart'                => [
                'id'    => $quoteId,
                'items' => $items,
            ],
            'transactionReference' => $includeOrderData ? $this->getTransactionReference() : null,
            'email'                => $includeOrderData ? $this->getCustomerEmail() : null,
        ];
    }

    private function getWebComponentDataCartItems(): array
    {
        /** @var CartInterface $quote */
        $quote = $this->checkoutSession->getQuote();

        $items = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            try {
                $product = $item->getProduct();
                $url     = $product->getUrlModel()->getUrl($product);

                $items[] = [
                    'name'            => $item->getName(),
                    'url'             => $url,
                    'quantity'        => (int)$item->getQty(),
                    'minorUnitAmount' => $this->priceConverter->minorUnitAmount($item->getPrice()),
                ];
            } catch (Throwable $e) {
                $this->logger->error(
                    '[SuperPayment] MarketingBanner::getWebComponentDataCartItems ' . $e->getMessage(),
                    ['exception' => $e]
                );
            }
        }

        return $items;
    }

    private function getTransactionReference(): ?string
    {
        $order = $this->checkoutSession->getLastRealOrder();
        if ($order && $order->getId() && $order->getIncrementId()) {
            return (string)$order->getIncrementId();
        }
        return null;
    }

    private function getCustomerEmail(): ?string
    {
        $order = $this->checkoutSession->getLastRealOrder();
        if ($order && $order->getId() && $order->getCustomerEmail()) {
            return (string)$order->getCustomerEmail();
        }
        return null;
    }

    public function cartItemsEncode(?array $cartItems = []): string
    {
        return (string) json_encode(
            $cartItems ?? [],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }

    public function isSuperPaymentOrder(): bool
    {
        $result = false;

        try {
            $order = $this->checkoutSession->getLastRealOrder();
            if (
                $order
                && $order->getId()
                && $order->getPayment()->getMethod() == Config::PAYMENT_CODE
            ) {
                $result = true;
            }
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
        }

        return $result;
    }
}
