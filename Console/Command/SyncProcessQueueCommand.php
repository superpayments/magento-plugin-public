<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Console\Command;

use Superpayments\SuperPayment\Cron\ProductSyncSendQueue;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento superpayments:product:sendqueue
 */
class SyncProcessQueueCommand extends Command
{
    /** @var ProductSyncSendQueue */
    protected $productSyncSendQueue;

    /**
     * @param ProductSyncSendQueue $productSyncSendQueue
     * @param string|null $name
     */
    public function __construct(
        ProductSyncSendQueue $productSyncSendQueue,
        string $name = null
    ) {
        $this->productSyncSendQueue = $productSyncSendQueue;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('superpayments:product:sendqueue');
        $this->setDescription('Manually trigger the Superpayments product sync send queue job.');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Starting: Superpayments product sync send queue.</info>');
        $this->productSyncSendQueue->execute();
        $output->writeln('<info>Completed: Superpayments product sync send queue.</info>');
    }
}
