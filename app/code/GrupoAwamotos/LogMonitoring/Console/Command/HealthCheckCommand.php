<?php

declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Console\Command;

use GrupoAwamotos\LogMonitoring\Service\OperationalHealthService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class HealthCheckCommand extends Command
{
    private OperationalHealthService $operationalHealthService;

    public function __construct(
        OperationalHealthService $operationalHealthService,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->operationalHealthService = $operationalHealthService;
    }

    protected function configure(): void
    {
        $this->setName('awa:health:check')
            ->setDescription('Verificação operacional da loja (versão, cron, indexadores, Redis, OpenSearch, logs)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Saída JSON para monitoramento/automação')
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Exit code 1 também em avisos (warn)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $report = $this->operationalHealthService->collect();
        $asJson = (bool)$input->getOption('json');
        $strict = (bool)$input->getOption('strict');

        if ($asJson) {
            $encoded = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                $output->writeln('<error>Falha ao serializar relatório.</error>');
                return Command::FAILURE;
            }
            $output->writeln($encoded);
        } else {
            $this->renderHuman($output, $report);
        }

        if ($report['overall'] === 'fail') {
            return Command::FAILURE;
        }

        if ($strict && $report['overall'] === 'warn') {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $report
     */
    private function renderHuman(OutputInterface $output, array $report): void
    {
        $overall = (string)$report['overall'];
        $tag = match ($overall) {
            'ok' => 'info',
            'warn' => 'comment',
            default => 'error',
        };

        $output->writeln('');
        $output->writeln('<info>AWA Motos — Health Check</info>');
        $output->writeln(sprintf('Verificado em: %s', $report['checked_at'] ?? ''));
        $output->writeln(sprintf(
            '<%s>Status geral: %s (%d falha(s), %d aviso(s))</%s>',
            $tag,
            strtoupper($overall),
            (int)($report['failed'] ?? 0),
            (int)($report['warnings'] ?? 0),
            $tag
        ));
        $output->writeln('');

        /** @var array<int, array<string, mixed>> $checks */
        $checks = $report['checks'] ?? [];
        foreach ($checks as $check) {
            $status = (string)($check['status'] ?? 'unknown');
            $icon = match ($status) {
                'ok' => '[OK]',
                'warn' => '[!!]',
                default => '[XX]',
            };
            $output->writeln(sprintf(
                '%s %s: %s',
                $icon,
                $check['label'] ?? $check['id'] ?? 'check',
                $check['message'] ?? ''
            ));
        }

        $output->writeln('');
    }
}
