<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\FlagManager;
use Magento\Store\Model\Store;
use Superpayments\SuperPayment\Gateway\Config\Config;

class FullSyncStatus extends Field
{
    /**
     * @var FlagManager
     */
    private FlagManager $flagManager;

    public function __construct(
        Context $context,
        FlagManager $flagManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->flagManager = $flagManager;
    }

    /**
     * @inheritdoc
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        // Get store ID from request context
        $storeId = $this->getStoreId();

        // Build flag code
        $flagCode = 'super_payment_gateway/store_' . $storeId . '/' . Config::KEY_PRODUCT_FULL_SYNC_COMPLETED;

        // Read flag value
        $isCompleted = (bool) $this->flagManager->getFlagData($flagCode);

        // Render as read-only status with visual indicator
        $statusText = $isCompleted ? __('Completed') : __('Not Completed');
        $statusClass = $isCompleted ? 'success' : 'warning';

        $html = sprintf(
            '<span class="flag-status flag-status-%s"><strong>%s</strong></span>',
            $statusClass,
            $statusText
        );

        // Add CSS for styling
        $html .= $this->getStatusStyles();

        return $html;
    }

    /**
     * Get store ID from request context
     *
     * Handles both store parameter (store view scope) and website parameter (website scope)
     * Falls back to current store in single-store mode
     *
     * @return int
     */
    private function getStoreId(): int
    {
        $storeId = 0;

        // Check for store parameter (store view scope)
        if ($this->getRequest()->getParam('store')) {
            $storeId = (int) $this->getRequest()->getParam('store');
        } elseif ($this->getRequest()->getParam('website')) {
            // Check for website parameter (fall back to default store)
            $website = $this->_storeManager->getWebsite($this->getRequest()->getParam('website'));
            if ($website->getId()) {
                /** @var Store $store */
                $store = $website->getDefaultStore();
                $storeId = (int) $store->getStoreId();
            }
        } else {
            // Fall back to current store (handles single-store mode)
            try {
                $store = $this->_storeManager->getStore();
                if ($store && $store->getId()) {
                    $storeId = (int) $store->getId();
                }
            } catch (\Exception $e) {
                // If unable to get current store, default to 0
                $storeId = 0;
            }
        }

        return $storeId;
    }

    /**
     * Get inline CSS styles for status display
     *
     * @return string
     */
    private function getStatusStyles(): string
    {
        return '<style>
            .flag-status {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 3px;
                font-size: 13px;
            }
            .flag-status-success {
                background-color: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            .flag-status-warning {
                background-color: #fff3cd;
                color: #856404;
                border: 1px solid #ffeeba;
            }
        </style>';
    }
}
