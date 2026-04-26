<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Cron;

use Byte8\VatValidator\Api\ValidationLogRepositoryInterface;
use Byte8\VatValidator\Model\Config;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

class PruneValidationLog
{
    public function __construct(
        private readonly Config $config,
        private readonly ValidationLogRepositoryInterface $logRepository,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled() || !$this->config->isLogEnabled()) {
            return;
        }

        $years = $this->config->getRetentionYears();
        $cutoff = $this->dateTime->gmtDate('Y-m-d H:i:s', strtotime('-' . $years . ' years'));

        try {
            $deleted = $this->logRepository->deleteOlderThan($cutoff);

            if ($deleted > 0) {
                $this->logger->info(sprintf(
                    'VAT validator log pruned: %d entries older than %s removed (retention: %d years)',
                    $deleted,
                    $cutoff,
                    $years
                ));
            }
        } catch (\Throwable $e) {
            $this->logger->error('VAT validator log prune failed: ' . $e->getMessage());
        }
    }
}
