<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Console\Command;

use Byte8\VatValidator\Api\VatValidatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ValidateCommand extends Command
{
    private const ARG_VAT = 'vat';

    public function __construct(
        private readonly VatValidatorInterface $validator,
        private readonly LoggerInterface $logger,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('byte8:vat:validate')
            ->setDescription('Validate a single VAT number against VIES / HMRC.')
            ->addArgument(
                self::ARG_VAT,
                InputArgument::REQUIRED,
                'VAT number including country prefix (e.g. GB123456789, DE123456789).'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $raw = (string) $input->getArgument(self::ARG_VAT);
        $normalised = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '');

        if (strlen($normalised) < 3 || !ctype_alpha(substr($normalised, 0, 2))) {
            $output->writeln('<error>VAT number must start with a 2-letter country code (e.g. GB123456789).</error>');

            return Command::INVALID;
        }

        $country = substr($normalised, 0, 2);
        $number = substr($normalised, 2);

        try {
            $result = $this->validator->validate($country, $number);
        } catch (\Throwable $e) {
            $this->logger->error('CLI validation error: ' . $e->getMessage());
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Country:</info>   %s', $result->getCountryCode()));
        $output->writeln(sprintf('<info>Number:</info>    %s', $result->getVatNumber()));
        $output->writeln(sprintf('<info>Status:</info>    %s', $result->getStatus()));
        $output->writeln(sprintf('<info>Source:</info>    %s', $result->getSource()));
        if ($result->getName() !== null) {
            $output->writeln(sprintf('<info>Name:</info>      %s', $result->getName()));
        }
        if ($result->getAddress() !== null) {
            $output->writeln(sprintf('<info>Address:</info>   %s', $result->getAddress()));
        }
        if ($result->getRequestIdentifier() !== null) {
            $output->writeln(sprintf('<info>Ref:</info>       %s', $result->getRequestIdentifier()));
        }
        if ($result->getMessage() !== null) {
            $output->writeln(sprintf('<info>Message:</info>   %s', $result->getMessage()));
        }

        return $result->getStatus() === \Byte8\VatValidator\Api\Data\ValidationResultInterface::STATUS_VALID
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}
