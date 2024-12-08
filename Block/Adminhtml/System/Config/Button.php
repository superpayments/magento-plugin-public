<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Widget\Button as WidgetButton;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;

class Button extends Field
{
    /**
     * @inheritdoc
     * @throws LocalizedException
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $this->setElement($element);

        $storeId = 0;
        if ($this->getRequest()->getParam('website')) {
            $website = $this->_storeManager->getWebsite($this->getRequest()->getParam('website'));
            if ($website->getId()) {
                /** @var Store $store */
                $store = $website->getDefaultStore();
                $storeId = $store->getStoreId();
            }
        }

        $url = $this->getUrl('superpayment/system_config/validate', ['storeId' => $storeId]);

        return $this->getLayout()->createBlock(WidgetButton::class)
            ->setType('button')
            ->setClass('scalable')
            ->setId('super_payments_validate_button')
            ->setLabel('Validate API Key')
            ->setDataAttribute(['url' => $url])
            ->toHtml();
    }
}
