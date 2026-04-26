<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Console\Command;

use Byte8\VatValidator\Api\Data\ValidationResultInterface;
use Byte8\VatValidator\Api\VatValidatorInterface;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Bulk re-validate every customer address that has a VAT number set.
 *
 * Use cases:
 *   - First install on a store with an existing B2B customer base
 *   - Periodic re-validation to catch numbers that became invalid
 *   - Targeted clean-up after enabling a new upstream (e.g. UID-CHE)
 *
 * Every successful or failed result is persisted to byte8_vat_validator_log
 * via the standard event hook (no special-casing in this command).
 */
class RevalidateAllCommand extends Command
{
    private const OPT_COUNTRY = 'country';
    private const OPT_STATUS = 'status';
    private const OPT_SINCE = 'since';
    private const OPT_LIMIT = 'limit';
    private const OPT_DRY_RUN = 'dry-run';

    public function __construct(
        private readonly AddressRepositoryInterface $addressRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly VatValidatorInterface $validator,
        private readonly LoggerInterface $logger,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('byte8:vat:revalidate-all')
            ->setDescription('Re-validate every customer address that has a VAT number set.')
            ->addOption(
                self::OPT_COUNTRY,
                'c',
                InputOption::VALUE_REQUIRED,
                'Limit to a single ISO country code (e.g. DE, GB, CH). Comma-separated for multiple.'
            )
            ->addOption(
                self::OPT_STATUS,
                's',
                InputOption::VALUE_REQUIRED,
                'Filter by current default-billing status. Currently informational only — addresses are validated regardless.'
            )
            ->addOption(
                self::OPT_SINCE,
                null,
                InputOption::VALUE_REQUIRED,
                'Only addresses updated on/after this date (Y-m-d).'
            )
            ->addOption(
                self::OPT_LIMIT,
                'l',
                InputOption::VALUE_REQUIRED,
                'Maximum number of addresses to process. Useful for spot-checks.',
                '0'
            )
            ->addOption(
                self::OPT_DRY_RUN,
                null,
                InputOption::VALUE_NONE,
                'List which addresses would be validated without actually calling VIES / HMRC / UID-CHE.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $countriesOpt = (string) ($input->getOption(self::OPT_COUNTRY) ?? '');
        $sinceOpt = (string) ($input->getOption(self::OPT_SINCE) ?? '');
        $limit = (int) $input->getOption(self::OPT_LIMIT);
        $dryRun = (bool) $input->getOption(self::OPT_DRY_RUN);

        $countries = $countriesOpt === ''
            ? []
            : array_map('strtoupper', array_filter(array_map('trim', explode(',', $countriesOpt))));

        $this->searchCriteriaBuilder->addFilter('vat_id', '', 'neq');
        $this->searchCriteriaBuilder->addFilter('vat_id', null, 'notnull');

        if ($countries !== []) {
            $this->searchCriteriaBuilder->addFilter('country_id', $countries, 'in');
        }

        if ($sinceOpt !== '') {
            $this->searchCriteriaBuilder->addFilter('updated_at', $sinceOpt, 'gteq');
        }

        if ($limit > 0) {
            $this->searchCriteriaBuilder->setPageSize($limit);
        }

        $criteria = $this->searchCriteriaBuilder->create();
        $list = $this->addressRepository->getList($criteria);
        $total = $list->getTotalCount();

        if ($total === 0) {
            $output->writeln('<info>No customer addresses match the given filters.</info>');

            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<info>Found %d address(es) to process%s.</info>', $total, $dryRun ? ' (dry-run)' : ''));

        $counts = [
            ValidationResultInterface::STATUS_VALID => 0,
            ValidationResultInterface::STATUS_INVALID => 0,
            ValidationResultInterface::STATUS_UNAVAILABLE => 0,
            ValidationResultInterface::STATUS_SKIPPED => 0,
        ];

        $processed = 0;
        foreach ($list->getItems() as $address) {
            $countryId = (string) $address->getCountryId();
            $vatNumber = (string) $address->getVatId();

            if ($countryId === '' || $vatNumber === '') {
                continue;
            }

            if ($dryRun) {
                $output->writeln(sprintf(
                    '  - %s%s (customer #%s, address #%s)',
                    $countryId,
                    $vatNumber,
                    (string) $address->getCustomerId(),
                    (string) $address->getId()
                ));
                $processed++;
                continue;
            }

            try {
                $result = $this->validator->validate($countryId, $vatNumber);
                $counts[$result->getStatus()] = ($counts[$result->getStatus()] ?? 0) + 1;

                $output->writeln(sprintf(
                    '  %s %s%s → %s (%s)',
                    $this->statusIcon($result->getStatus()),
                    $result->getCountryCode(),
                    $result->getVatNumber(),
                    $result->getStatus(),
                    $result->getSource()
                ));
            } catch (\Throwable $e) {
                $this->logger->error('Bulk revalidate error: ' . $e->getMessage());
                $output->writeln(sprintf('  <error>! %s%s — %s</error>', $countryId, $vatNumber, $e->getMessage()));
            }

            $processed++;
        }

        $output->writeln('');
        $output->writeln('<info>--- Summary ---</info>');
        $output->writeln(sprintf('  Processed:    %d', $processed));
        if (!$dryRun) {
            $output->writeln(sprintf('  Valid:        %d', $counts[ValidationResultInterface::STATUS_VALID]));
            $output->writeln(sprintf('  Invalid:      %d', $counts[ValidationResultInterface::STATUS_INVALID]));
            $output->writeln(sprintf('  Unavailable:  %d', $counts[ValidationResultInterface::STATUS_UNAVAILABLE]));
            $output->writeln(sprintf('  Skipped:      %d', $counts[ValidationResultInterface::STATUS_SKIPPED]));
        }

        return Command::SUCCESS;
    }

    private function statusIcon(string $status): string
    {
        return match ($status) {
            ValidationResultInterface::STATUS_VALID => '<info>✓</info>',
            ValidationResultInterface::STATUS_INVALID => '<comment>✗</comment>',
            ValidationResultInterface::STATUS_UNAVAILABLE => '<comment>?</comment>',
            default => '<comment>·</comment>',
        };
    }
}
