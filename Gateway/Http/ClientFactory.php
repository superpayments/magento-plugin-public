<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Http;

use Magento\Framework\HTTP\LaminasClient;
use Magento\Framework\HTTP\ZendClient;
use Magento\Framework\ObjectManagerInterface;

class ClientFactory
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var string
     */
    private $instanceName;

    public function __construct(
        ObjectManagerInterface $objectManager,
        string $instanceName = '\\Magento\\Framework\\HTTP\\LaminasClient'
    ) {
        $this->objectManager = $objectManager;
        $this->instanceName = $instanceName;
    }

    /**
     * @param array $data
     * @return LaminasClient|ZendClient
     */
    public function create(array $data = [])
    {
        if (!class_exists($this->instanceName)) {
            $this->instanceName = '\\Magento\\Framework\\HTTP\\ZendClient';
            return $this->objectManager->create($this->instanceName, $data);
        }

        return $this->objectManager->create($this->instanceName, $data);
    }
}
