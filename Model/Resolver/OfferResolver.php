<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Model\Resolver;

use Exception;
use Magento\Framework\Escaper;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Service\ApiServiceInterface;
use Superpayments\SuperPayment\Model\PriceConverter;
use Throwable;

class OfferResolver implements ResolverInterface
{
    /** @var Json */
    private $jsonSerializer;

    /** @var GetCartForUser */
    private $getCartForUser;

    /** @var ApiServiceInterface */
    private $apiService;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var PriceConverter */
    private $priceConverter;

    /** @var LoggerInterface */
    private $logger;

    /** @var Escaper */
    private $escaper;

    public function __construct(
        GetCartForUser $getCartForUser,
        Json $jsonSerializer,
        ApiServiceInterface $apiService,
        StoreManagerInterface $storeManager,
        PriceConverter $priceConverter,
        LoggerInterface $logger,
        Escaper $escaper
    ) {
        $this->jsonSerializer = $jsonSerializer;
        $this->getCartForUser = $getCartForUser;
        $this->apiService = $apiService;
        $this->storeManager = $storeManager;
        $this->priceConverter = $priceConverter;
        $this->logger = $logger;
        $this->escaper = $escaper;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        if (empty($args['input']['cart_id'])) {
            throw new GraphQlInputException(__('Specify the "cart_id" value.'));
        }

        try {
            $maskedCartId = $args['input']['cart_id'];
            $customerId = $context->getUserId() ?? null;

            $store = $context->getExtensionAttributes()->getStore();
            $storeId = (int)$store->getId();

            /** @var Quote $quote */
            $quote = $this->getCartForUser->execute($maskedCartId, $customerId, $storeId);
            $quote->collectTotals();

            $cartAmountMinor = $this->priceConverter->minorUnitAmount($quote->getGrandTotal());
            $cartIdRaw = $quote->getId() ?: ('unknown-' . time());
            $cartItems = $this->getCartItems($quote);

            $result = $this->apiService->execute([
                'store' => $this->storeManager->getStore($quote->getStoreId()),
            ]);

            $superCheckoutSessionId = $result['checkoutSessionId'] ?? '';
            $superCheckoutSessionToken = $result['checkoutSessionToken'] ?? '';

            $phoneNumber = $this->getPhoneNumber($quote);
            $page = 'checkout';

            // Prepare attribute-safe values
            $cartAmountAttr = $this->escaper->escapeHtmlAttr((string)(int)$cartAmountMinor);
            $pageAttr = $this->escaper->escapeHtmlAttr($page);
            $cartIdAttr = $this->escaper->escapeHtmlAttr((string)$cartIdRaw);
            $cartItemsAttr = $this->cartItemsEncode($cartItems);
            $phoneAttr = $this->escaper->escapeHtmlAttr($phoneNumber);
            $tokenAttr = $this->escaper->escapeHtmlAttr($superCheckoutSessionToken);

            // Build HTML fragments on multiple lines to keep source lines short
            $titleHtml =
                '<div class="super-payment-method-title">' .
                '<super-payment-method-title ' .
                'cartAmount="' . $cartAmountAttr . '" ' .
                'page="' . $pageAttr . '" ' .
                'cartId="' . $cartIdAttr . '" ' .
                'cartItems="' . $cartItemsAttr . '"' .
                '></super-payment-method-title>' .
                '</div>';

            $descriptionHtml =
                '<super-checkout ' .
                'amount="' . $cartAmountAttr . '" ' .
                'checkout-session-token="' . $tokenAttr . '" ' .
                'phone-number="' . $phoneAttr . '"' .
                '></super-checkout>';

            $json = [
                'title'                     => $titleHtml,
                'description'               => $descriptionHtml,
                'super_checkout_session_id' => $superCheckoutSessionId,
            ];
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            $json = ['result' => 'error', 'exception' => $e->getMessage()];
        }

        return ['content' => $this->jsonSerializer->serialize($json)];
    }

    private function getCartItems(Quote $quote): array
    {
        $items = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            try {
                $product = $item->getProduct();
                $url = $product->getUrlModel()->getUrl($product);

                $items[] = [
                    'name'            => $item->getName(),
                    'url'             => $url,
                    'quantity'        => (int)$item->getQty(),
                    'minorUnitAmount' => $this->priceConverter->minorUnitAmount($item->getPrice()),
                ];
            } catch (Throwable $e) {
                $this->logger->error(
                    '[SuperPayment] OfferResolver::getCartItems ' . $e->getMessage(),
                    ['exception' => $e]
                );
            }
        }

        return $items;
    }

    private function getPhoneNumber(Quote $quote): string
    {
        try {
            $phoneNumber = '';
            $billingAddress = $quote->getBillingAddress();
            $phoneNumber = $billingAddress ? $billingAddress->getTelephone() : '';

            if (empty($phoneNumber)) {
                $phoneNumber = $this->getCustomerDefaultBillingTelephone($quote);
            }
            if (empty($phoneNumber)) {
                $shippingAddress = $quote->getShippingAddress();
                $phoneNumber = $shippingAddress ? $shippingAddress->getTelephone() : '';
            }
        } catch (Throwable $e) {
            $this->logger->error(
                '[SuperPayment] Offerbanner::getPhoneNumber ' . $e->getMessage(),
                ['exception' => $e]
            );
        }

        return $phoneNumber ?? '';
    }

    private function getCustomerDefaultBillingTelephone(Quote $quote): ?string
    {
        try {
            $customerId = $quote->getCustomerId();
            if (!$customerId) {
                return null;
            }

            $customerAddresses = $quote->getCustomer()->getAddresses();
            foreach ($customerAddresses as $customerAddress) {
                if (
                    $customerAddress->isDefaultBilling()
                    && ($telephone = $customerAddress->getTelephone())
                ) {
                    return $telephone;
                }
            }
        } catch (Throwable $e) {
            $this->logger->error(
                '[SuperPayment] Offerbanner::getCustomerDefaultBillingTelephone ' . $e->getMessage(),
                ['exception' => $e]
            );
        }

        return null;
    }

    /**
     * Encode cart items as JSON and escape for safe use in an HTML attribute.
     */
    private function cartItemsEncode(?array $cartItems = []): string
    {
        $json = json_encode(
            $cartItems ?? [],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        return $this->escaper->escapeHtmlAttr((string)$json);
    }
}
