<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Model;

use Exception;
use Magento\Framework\DB\Adapter\DeadlockException;
use Magento\Framework\DB\Adapter\LockWaitException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\OrderRepository;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Config\Config;
use Superpayments\SuperPayment\Gateway\Service\ApiServiceInterface;
use Superpayments\SuperPayment\Gateway\Service\ReturnFundsService;
use Throwable;

class PaymentUpdate
{
    public const STATUS_SUCCESS = 'PaymentSuccess';
    public const STATUS_CANCELED = 'PaymentCancelled';
    public const STATUS_FAILED = 'PaymentFailed';
    public const STATUS_DELAYED = 'PaymentDelayed';
    public const STATUS_ABANDONED = 'PaymentAbandoned';
    public const STATUS_REFUNDED = 'RefundSuccess';
    public const STATUS_REFUND_FAILED = 'RefundFailed';
    public const STATUS_REFUND_ABANDONED = 'RefundAbandoned';
    public const MAX_SAVE_RETRIES = 3;

    /** @var OrderInterface */
    private $orderRepository;

    /** @var ApiServiceInterface */
    private $apiService;

    /** @var ReturnFundsService */
    private $returnFundsService;

    /** @var LoggerInterface */
    private $logger;

    /** @var OrderInterface|null */
    private ?OrderInterface $order;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var Config */
    private $config;

    /** @var OrderSender */
    private $orderSender;

    /** @var InvoiceSender */
    private $invoiceSender;

    /** @var CreditmemoRepositoryInterface */
    private $creditMemoRepository;

    public function __construct(
        OrderRepository $orderRepository,
        ApiServiceInterface $apiService,
        Config $config,
        StoreManagerInterface $storeManager,
        OrderSender $orderSender,
        InvoiceSender $invoiceSender,
        CreditmemoRepositoryInterface $creditMemoRepository,
        ReturnFundsService $returnFundsService,
        LoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->apiService = $apiService;
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->orderSender = $orderSender;
        $this->invoiceSender = $invoiceSender;
        $this->creditMemoRepository = $creditMemoRepository;
        $this->returnFundsService = $returnFundsService;
        $this->logger = $logger;
        $this->order = null;
    }

    public function execute(OrderInterface $order, array $updateData): void
    {
        $this->order = $order;

        $this->order->addCommentToStatusHistory(
            'Super Payment Webhook Status: ' . $updateData['transactionStatus']
        );

        switch ($updateData['transactionStatus']) {
            case self::STATUS_SUCCESS:
                if (!$this->order->isCanceled()) {
                    $this->order->setState(Order::STATE_PROCESSING);
                    $this->order->setStatus(Order::STATE_PROCESSING);
                }

                /* Order Update for instant Discount row*/
                $this->updateOrderTotal($updateData);

                $this->order->addCommentToStatusHistory(
                    'Transaction Reference: ' . $updateData['transactionReference']
                );
                $this->order->getPayment()->setTransactionId($updateData['transactionId']);
                $this->order->getPayment()->setTransactionAdditionalInfo(
                    'transactionAmount',
                    $updateData['transactionAmount']
                );
                $this->order->getPayment()->setTransactionAdditionalInfo(
                    'transactionReference',
                    $updateData['transactionReference']
                );
                $this->order->getPayment()->setTransactionAdditionalInfo(
                    'transactionId',
                    $updateData['transactionId']
                );
                $this->order->getPayment()->setIsTransactionPending(false);
                $additionalInfo = $this->order->getPayment()->getAdditionalInformation();
                $additionalInfo['transactionId'] = $updateData['transactionId'];
                $additionalInfo['transactionReference'] = $updateData['transactionReference'];
                $additionalInfo['transactionStatus'] = $updateData['transactionStatus'];
                $additionalInfo['transactionAmount'] = $updateData['transactionAmount'];
                $additionalInfo['fundingSummary'] = $updateData['fundingSummary'] ?? '';
                if (!isset($additionalInfo['last_transaction_id'])) {
                    $additionalInfo['last_transaction_id'] = $updateData['transactionId'];
                    $additionalInfo['last_transaction_amount'] = $updateData['transactionAmount'] / 100;
                }
                $this->order->getPayment()->setAdditionalInformation($additionalInfo);
                $this->saveOrder();

                if ($this->order->isCanceled()) {
                    $this->handleReturnFundsOnCanceledOrder($updateData);
                } else {
                    $this->sendOrderConfirmationEmail();
                    if ($this->config->isAutoRegisterCaptureEnabled()) {
                        $this->registerCapture(((float) $updateData['transactionAmount']) / 100);
                        $this->sendInvoiceEmail();
                    }
                    $this->expireOffer();
                    $this->saveOrder();
                }
                break;
            case self::STATUS_CANCELED:
            case self::STATUS_ABANDONED:
            case self::STATUS_FAILED:
                if (!$this->order->isCanceled()) {
                    $this->order->cancel();
                    $this->saveOrder();
                }
                break;
            case self::STATUS_REFUNDED:
            case self::STATUS_REFUND_FAILED:
            case self::STATUS_REFUND_ABANDONED:
                $this->handleRefund($updateData);
                break;
            case self::STATUS_DELAYED:
            default:
                break;
        }
    }

    private function registerCapture(float $transactionAmount): void
    {
        try {
            $this->order->getPayment()->registerCaptureNotification($transactionAmount, true);
        } catch (Throwable $e) {
            $this->logger->critical('[SP Webhook] RegisterCapture ' . $e->getMessage(), ['exception' => $e]);
            if ($this->config->isDebugEnabled()) {
                $this->logger->error($e->getTraceAsString());
            }
        }
    }

    private function expireOffer(): void
    {
        try {
            if ($superpaymentsOfferId = $this->order->getPayment()->getAdditionalInformation('superpaymentsOfferId')) {
                $data = [
                    'reward_calculation_id' => $superpaymentsOfferId,
                    'store' => $this->storeManager->getStore($this->order->getStoreId()),
                ];
                $this->apiService->execute($data);
            }
        } catch (Throwable $e) {
            $this->logger->critical('[SP Webhook] ExpireOffer ' . $e->getMessage(), ['exception' => $e]);
            if ($this->config->isDebugEnabled()) {
                $this->logger->error($e->getTraceAsString());
            }
        }
    }

    private function sendOrderConfirmationEmail(): void
    {
        try {
            $forceSend = $this->config->isForceEmailSendEnabled();
            if (($this->config->isOrderSendEmailEnabled() || $forceSend) && !$this->order->getEmailSent()) {
                $this->orderSender->send($this->order, $forceSend);
            }
        } catch (Throwable $e) {
            $this->logger->critical('[SP Webhook] SendOrderEmail ' . $e->getMessage(), ['exception' => $e]);
            if ($this->config->isDebugEnabled()) {
                $this->logger->error($e->getTraceAsString());
            }
        }
    }

    private function saveOrder(): void
    {
        try {
            if ($this->config->isDeadlockMitigateEnabled()) {
                $this->deadlockMitigatedSave();
            } else {
                $this->orderRepository->save($this->order);
            }
        } catch (Throwable $e) {
            $this->logger->critical('[SP Webhook] SaveOrder ' . $e->getMessage(), ['exception' => $e]);
            if ($this->config->isDebugEnabled()) {
                $this->logger->error($e->getTraceAsString());
            }
            throw new Exception($e->getMessage());
        }
    }

    private function deadlockMitigatedSave(): void
    {
        $orderId = $this->order->getIncrementId() ?? $this->order->getId();

        for ($i = 1; $i <= self::MAX_SAVE_RETRIES; $i++) {
            try {
                $this->orderRepository->save($this->order);
                break;
            } catch (DeadlockException|LockWaitException $e) {
                if ($i < self::MAX_SAVE_RETRIES) {
                    $this->logger->error(
                        '[SP Webhook] Deadlock encountered retrying ..' . $e->getMessage(),
                        ['exception' => $e]
                    );
                    sleep(2);
                    continue;
                }
                throw new CouldNotSaveException(
                    __(
                        '[SP Webhook] Unable to save order #%1 after %2 attempts due to a database lock: %3',
                        $orderId,
                        $i,
                        $e->getMessage()
                    ),
                    $e
                );
            } catch (Exception $e) {
                throw new CouldNotSaveException(
                    __('[SP Webhook] Unable to save order #%1: %2', $orderId, $e->getMessage()),
                    $e
                );
            }
        }
    }

    private function updateOrderTotal(array $updateData): void
    {
        $collectAmountAfterSaving = $updateData['transactionAmount'] / 100;
        $grandTotal = $collectAmountAfterSaving ?? $this->order->getGrandTotal();

        $this->order->setGrandTotal($grandTotal);
        $this->order->setBaseGrandTotal($grandTotal);
        $this->order->setTotalPaid($grandTotal);
    }

    private function sendInvoiceEmail(): void
    {
        try {
            $forceSend = $this->config->isForceEmailSendEnabled();
            if ($this->config->isInvoiceSendEmailEnabled() || $forceSend) {
                /** @var Invoice $invoice */
                $invoice = current($this->order->getInvoiceCollection()->getItems());
                if ($invoice && !$invoice->getEmailSent()) {
                    $this->invoiceSender->send($invoice, $forceSend);
                }
            }
        } catch (Throwable $e) {
            $this->logger->critical($e->getMessage(), ['exception' => $e]);
            if ($this->config->isDebugEnabled()) {
                $this->logger->error($e->getTraceAsString());
            }
        }
    }

    private function handleRefund(?array $updateData): void
    {
        try {
            $message = null;
            switch ($updateData['transactionStatus']) {
                case self::STATUS_REFUNDED:
                    $message = 'Refund Status: Refund Successful';
                    break;
                case self::STATUS_REFUND_FAILED:
                    $message = 'Refund Status: Refund Failed!';
                    break;
                case self::STATUS_REFUND_ABANDONED:
                    $message = 'Refund Status: Refund Abandoned!';
                    break;
            }
            $additionalInfo = $this->order->getPayment()->getAdditionalInformation();
            $additionalInfo['refundTransactionId'] = $updateData['transactionId'];
            $additionalInfo['refundTransactionStatus'] = $updateData['transactionStatus'];
            $this->order->getPayment()->setAdditionalInformation($additionalInfo);
            $this->order->addCommentToStatusHistory($message);
            $this->saveOrder();

            /** @var Creditmemo $creditMemo */
            $creditMemo = current($this->order->getCreditmemosCollection()->getItems());
            if ($creditMemo) {
                $creditMemo->addComment($message);
                if ($updateData['transactionStatus'] != self::STATUS_REFUNDED) {
                    $this->order->setTotalRefunded(
                        $this->order->getTotalRefunded() - $creditMemo->getBaseGrandTotal()
                    );
                    $creditMemo->setGrandTotal(0);
                    $creditMemo->setBaseGrandTotal(0);
                }
                $this->creditMemoRepository->save($creditMemo);
                $this->saveOrder();
            }
        } catch (Throwable $e) {
            if ($this->config->isDebugEnabled()) {
                $this->logger->error($e->getTraceAsString());
            }
            throw new Exception($e->getMessage());
        }
    }

    private function handleReturnFundsOnCanceledOrder(?array $updateData): void
    {
        try {
            if (!$this->order->isCanceled()) {
                return;
            }

            $this->returnFundsService->execute([
                'order' => $this->order,
            ]);

            $refundTransactionRef = $this->order->getPayment()->getAdditionalInformation('refundTransactionReference') ?? '';

            $this->order->addCommentToStatusHistory(
                'Superpayments automatic refund initiated as order found in a canceled state after payment capture. '
                . 'Refund reference: ' . $refundTransactionRef
            );
            $this->saveOrder();
        } catch (Throwable $e) {
            if ($this->config->isDebugEnabled()) {
                $this->logger->error($e->getTraceAsString());
            }
        }
    }
}
