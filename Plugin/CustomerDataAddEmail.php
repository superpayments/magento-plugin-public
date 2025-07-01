<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Plugin;

use Magento\Customer\CustomerData\Customer as Subject;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Psr\Log\LoggerInterface;
use Throwable;

class CustomerDataAddEmail
{
    /** @var CurrentCustomer */
    private $customer;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(CurrentCustomer $customer, LoggerInterface $logger)
    {
        $this->customer = $customer;
        $this->logger = $logger;
    }

    public function afterGetSectionData(Subject $subject, array $result): array
    {
        try {
            if ($this->customer->getCustomerId()) {
                $customer = $this->customer->getCustomer();
                if ($customer && $customer->getEmail()) {
                    $plain = $customer->getEmail();
                    $result['spContact'] = base64_encode($plain);
                }
            }
        } catch (Throwable $e) {
            $this->logger->error('[SuperPayments] CustomerDataAddEmail ' . $e->getMessage(), ['exception' => $e]);
        }

        return $result;
    }
}
