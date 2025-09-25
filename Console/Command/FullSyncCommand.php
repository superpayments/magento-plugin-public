<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Console\Command;

use Superpayments\SuperPayment\Cron\ProductSyncFullSync;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento superpayments:product:fullsync
 */
class FullSyncCommand extends Command
{
    /** @var ProductSyncFullSync */
    private $productSyncFullSync;

    public function __construct(
        ProductSyncFullSync $productSyncFullSync,
        ?string $name = null
    ) {
        $this->productSyncFullSync = $productSyncFullSync;
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('superpayments:product:fullsync');
        $this->setDescription('Manually trigger the Superpayments product full sync job.');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Starting: Superpayments product full sync.</info>');
        $this->productSyncFullSync->execute();
        $output->writeln('<info>Completed: Superpayments product full sync.</info>');
    }
}
