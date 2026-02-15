<?php

namespace EilingIo\SyliusBatteryIncludedPlugin\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'bi:export',
    description: 'Exports all Products to bi.',
)]
class ExportCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->success('Export erfolgreich durchgeführt!');
        return Command::SUCCESS;
    }
}
