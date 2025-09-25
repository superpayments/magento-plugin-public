<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Model\ProductSync;

use Magento\Catalog\Helper\Data as TaxHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Media\Config as MediaConfig;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Catalog\Model\Product\Visibility as ProductVisibility;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableType;
use Magento\Review\Model\ResourceModel\Review\Summary as SummaryResource;
use Magento\Review\Model\Review\SummaryFactory as ReviewSummaryFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Model\Config as TaxConfig;

class ProductDataMapper
{
    /** @var CategoryCache */
    private $categoryCache;
    /** @var ProductCache */
    private $productCache;
    /** @var StoreManagerInterface */
    private $storeManager;
    /** @var TaxHelper */
    private $taxHelper;
    /** @var TaxConfig */
    private $taxConfig;
    /** @var MediaConfig */
    private $mediaConfig;
    /** @var ReviewSummaryFactory */
    private $reviewSummaryFactory;
    /** @var SummaryResource */
    private $summaryResource;
    /** @var ConfigurableType */
    private $configurableType;

    public function __construct(
        CategoryCache $categoryCache,
        ProductCache $productCache,
        StoreManagerInterface $storeManager,
        TaxHelper $taxHelper,
        TaxConfig $taxConfig,
        MediaConfig $mediaConfig,
        ReviewSummaryFactory $reviewSummaryFactory,
        SummaryResource $summaryResource,
        ConfigurableType $configurableType
    ) {
        $this->categoryCache = $categoryCache;
        $this->productCache = $productCache;
        $this->storeManager = $storeManager;
        $this->taxHelper = $taxHelper;
        $this->taxConfig = $taxConfig;
        $this->mediaConfig = $mediaConfig;
        $this->reviewSummaryFactory = $reviewSummaryFactory;
        $this->summaryResource = $summaryResource;
        $this->configurableType = $configurableType;
    }

    public function mapUpsert(Product $product, int $storeId): array
    {
        $store = $this->storeManager->getStore($storeId);

        // - Parent product data
        $parentId = $parentSku = $parentUrl = null;
        if ($product->getTypeId() === ProductType::TYPE_SIMPLE) {
            $parentIds = $this->configurableType->getParentIdsByChild($product->getId());
            if (!empty($parentIds)) {
                $parentId = (int) array_shift($parentIds);
                $parentData = $this->productCache->getProductData($parentId, $storeId);
                $parentSku = $parentData['sku'];
                $parentUrl = $parentData['url'];
            }
        }

        // - Categories
        $categories = [];
        foreach ($product->getCategoryIds() as $catId) {
            if ($cat = $this->categoryCache->getCategoryData((int) $catId)) {
                $categories[] = $cat['name'];
            }
        }

        // - Stock
        $stockItem = $product->getExtensionAttributes()->getStockItem();
        $stockQty = $stockItem ? (int) $stockItem->getQty() : null;
        $stockStatus = $stockItem ? ($stockItem->getIsInStock() ? 'instock' : 'outofstock') : null;

        // - Prices & tax
        $finalPrice = (float) $product->getFinalPrice();
        $priceTaxType = $this->taxConfig->priceIncludesTax($store) ? 'incl' : 'excl';
        $priceIncl = $this->taxHelper->getTaxPrice($product, $finalPrice, true);
        $priceExcl = $this->taxHelper->getTaxPrice($product, $finalPrice, false);

        // - Reviews
        $summary = $this->reviewSummaryFactory->create();
        $summary->setData('store_id', $storeId);
        $this->summaryResource->load($summary, $product->getId());
        $reviewsCount = (int) $summary->getReviewsCount();
        $percentRating = (float) $summary->getRatingSummary();
        $reviewsAvgRating = $percentRating ? round($percentRating / 20, 2) : 0;

        // — Images
        $imageFile = $product->getData('image');
        $primaryImage = $imageFile ? $this->mediaConfig->getMediaUrl($imageFile) : null;
        $galleryImages = [];
        foreach ($product->getMediaGalleryImages() as $img) {
            $galleryImages[] = $img->getUrl();
        }

        $catalogVisibility = $product->getVisibility() == ProductVisibility::VISIBILITY_NOT_VISIBLE ? 0 : 1;

        return [
            'id' => (int) $product->getId(),
            'parentId' => $parentId,
            'sku' => $product->getSku(),
            'parentSku' => $parentSku,
            'url' => $catalogVisibility
                ? $product->getUrlModel()->getUrl($product, ['_store' => $storeId]) : $parentUrl,
            'parentUrl' => $parentUrl,
            'name' => $product->getName(),
            'shortDescription' => $this->cleanText($product->getShortDescription()) ?? $this->cleanText($product->getDescription()),
            'status' => $product->getStatus() == Status::STATUS_ENABLED ? 'publish' : 'disabled',
            'priceTaxType' => $priceTaxType,
            'priceIncl' => $priceIncl,
            'priceExcl' => $priceExcl,
            'stockStatus' => $stockStatus,
            'stockQuantity' => $stockQty,
            'type' => $product->getTypeId(),
            'dateCreated' => $product->getCreatedAt(),
            'dateModified' => $product->getUpdatedAt(),
            'catalogVisibility' => $catalogVisibility,
            'reviewsCount' => $reviewsCount,
            'reviewsAvgRating' => $reviewsAvgRating,
            'categories' => $categories,
            'imagePrimary' => $primaryImage,
            'images' => $galleryImages,
        ];
    }

    public function mapDelete(int $productId, int $storeId): array
    {
        return [
            "id" => $productId,
            "storeId" => $storeId,
            "status" => 'deleted',
        ];
    }

    private function cleanText(?string $text): ?string
    {
        return $text ? strip_tags($text) : null;
    }
}
