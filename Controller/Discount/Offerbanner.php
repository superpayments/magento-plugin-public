<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Controller\Discount;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Service\ApiServiceInterface;
use Superpayments\SuperPayment\Model\CheckoutSessionRepository;
use Superpayments\SuperPayment\Model\PriceConverter;
use Throwable;

class Offerbanner implements ActionInterface, HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * @var JsonFactory
     */
    private $jsonResultFactory;

    /** @var Session $checkoutSession */
    private $checkoutSession;

    /** @var ApiServiceInterface $apiService */
    private $apiService;

    /** @var StoreManagerInterface $storeManager */
    private $storeManager;

    /** @var CartInterface $quote */
    private $quote;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var CheckoutSessionRepository
     */
    private $checkoutSessionRepository;

    /** @var PriceConverter $priceConverter */
    private $priceConverter;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        Context $context,
        Session $checkoutSession,
        ApiServiceInterface $apiService,
        StoreManagerInterface $storeManager,
        JsonFactory $jsonResultFactory,
        CheckoutSessionRepository $checkoutSessionRepository,
        PriceConverter $priceConverter,
        LoggerInterface $logger
    ) {
        $this->jsonResultFactory = $jsonResultFactory;
        $this->request = $context->getRequest();
        $this->checkoutSession = $checkoutSession;
        $this->apiService = $apiService;
        $this->storeManager = $storeManager;
        $this->checkoutSessionRepository = $checkoutSessionRepository;
        $this->priceConverter = $priceConverter;
        $this->logger = $logger;
    }

    public function execute(): ResultInterface
    {
        try {
            $this->quote = $this->checkoutSession->getQuote();
            $this->quote->collectTotals();
            $result = $this->apiService->execute([
                'store' => $this->storeManager->getStore($this->quote->getStoreId()),
            ]);

            $superCheckoutSessionId = $result['checkoutSessionId'] ?? '';
            $superCheckoutSessionToken = $result['checkoutSessionToken'] ?? '';
            $this->checkoutSessionRepository->saveOrUpdate($this->quote->getId(), $superCheckoutSessionId);

            $cartAmount = $this->priceConverter->minorUnitAmount($this->quote->getGrandTotal());
            $cartId = ($this->quote->getId() ?: 'unknown-' . time());
            $cartItems = $this->getCartItems();
            $page = 'checkout';
            $data = [
                'title' => '<div class="super-payment-method-title"><super-payment-method-title cartAmount="' . $cartAmount . '" page="' . $page . '" cartId="' . $cartId . '" cartItems="' . $this->cartItemsEncode($cartItems) . '"></super-payment-method-title></div>',
                'description' => '<super-checkout amount="' . $cartAmount . '" checkout-session-token="' . $superCheckoutSessionToken . '"></super-checkout>',
            ];
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            $data = ['result' => 'error', 'exception' => $e->getMessage()];
        }
        $result = $this->jsonResultFactory->create();
        $result->setData($data);
        return $result;
    }

    private function getCartItems(): array
    {
        $items = [];
        foreach ($this->quote->getAllVisibleItems() as $item) {
            try {
                $item = [
                    'name' => $item->getName(),
                    'url' => $item->getProduct()->getUrlModel()->getUrl($item->getProduct()),
                    'quantity' => (int) $item->getQty(),
                    'minorUnitAmount' => $this->priceConverter->minorUnitAmount($item->getPrice()),
                ];
                $items[] = $item;
            } catch (Throwable $e) {
                $this->logger->error('[SuperPayment] ' . $e->getMessage(), ['exception' => $e]);
            }
        }

        return $items;
    }

    private function cartItemsEncode(?array $cartItems = []): ?string
    {
        return htmlspecialchars(json_encode($cartItems));
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }
}
