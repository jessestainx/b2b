<?php

declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Console\Command;

use GrupoAwamotos\LogMonitoring\Service\AgentTermination\DiagnosticReportService;
use Magento\Framework\Serialize\Serializer\Json;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DiagnoseAgentTerminationCommand extends Command
{
    private const OPTION_MESSAGE = 'message';
    private const OPTION_STACK_TRACE = 'stack-trace';
    private const OPTION_TRACE_ID = 'trace-id';
    private const OPTION_TRAJECTORY_ID = 'trajectory-id';
    private const OPTION_WORKFLOW = 'workflow';
    private const OPTION_MODEL = 'model';
    private const OPTION_PAYLOAD_FILE = 'payload-file';
    private const OPTION_HEADERS_JSON = 'headers-json';
    private const OPTION_DETAILS_JSON = 'details-json';
    private const OPTION_METADATA_JSON = 'metadata-json';
    private const OPTION_PERSIST_ALERT = 'persist-alert';
    private const OPTION_NOTIFY = 'notify';

    private DiagnosticReportService $diagnosticReportService;
    private Json $serializer;

    public function __construct(
        DiagnosticReportService $diagnosticReportService,
        Json $serializer,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->diagnosticReportService = $diagnosticReportService;
        $this->serializer = $serializer;
    }

    protected function configure(): void
    {
        $this->setName('awa:agent-termination:diagnose')
            ->setDescription('Classifica falhas do agente, gera relatório de diagnóstico e registra alertas quando necessário')
            ->addOption(self::OPTION_MESSAGE, null, InputOption::VALUE_REQUIRED, 'Mensagem exata do erro')
            ->addOption(self::OPTION_STACK_TRACE, null, InputOption::VALUE_OPTIONAL, 'Stack trace bruto do erro')
            ->addOption(self::OPTION_TRACE_ID, null, InputOption::VALUE_OPTIONAL, 'Trace ID do provedor')
            ->addOption(self::OPTION_TRAJECTORY_ID, null, InputOption::VALUE_OPTIONAL, 'Trajectory ID da sessão')
            ->addOption(self::OPTION_WORKFLOW, null, InputOption::VALUE_OPTIONAL, 'Workflow afetado', 'agent_execution')
            ->addOption(self::OPTION_MODEL, null, InputOption::VALUE_OPTIONAL, 'Modelo afetado', 'unknown_model')
            ->addOption(self::OPTION_PAYLOAD_FILE, null, InputOption::VALUE_OPTIONAL, 'Arquivo JSON com o payload completo do incidente')
            ->addOption(self::OPTION_HEADERS_JSON, null, InputOption::VALUE_OPTIONAL, 'Headers em JSON', '{}')
            ->addOption(self::OPTION_DETAILS_JSON, null, InputOption::VALUE_OPTIONAL, 'Payload details.error em JSON', '{}')
            ->addOption(self::OPTION_METADATA_JSON, null, InputOption::VALUE_OPTIONAL, 'Metadados adicionais em JSON', '{}')
            ->addOption(self::OPTION_PERSIST_ALERT, null, InputOption::VALUE_NONE, 'Persistir alerta no módulo de monitoramento')
            ->addOption(self::OPTION_NOTIFY, null, InputOption::VALUE_NONE, 'Enviar notificações após persistir o alerta');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $incident = $this->buildIncidentPayload($input);
        } catch (\InvalidArgumentException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $report = $this->diagnosticReportService->generate(
            $incident,
            (bool)$input->getOption(self::OPTION_PERSIST_ALERT),
            (bool)$input->getOption(self::OPTION_NOTIFY)
        );

        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $output->writeln('<error>Falha ao serializar o relatório de diagnóstico.</error>');
            return Command::FAILURE;
        }

        $output->writeln($json);
        return Command::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonOption(string $value, string $optionName): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        try {
            $decoded = $this->serializer->unserialize($value);
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException(
                sprintf('The --%s option must contain valid JSON: %s', $optionName, $exception->getMessage()),
                0,
                $exception
            );
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildIncidentPayload(InputInterface $input): array
    {
        $filePayload = $this->loadPayloadFile((string)$input->getOption(self::OPTION_PAYLOAD_FILE));

        return [
            'message' => $this->resolveScalarOption($input, self::OPTION_MESSAGE, $filePayload, 'message', true),
            'stack_trace' => $this->resolveScalarOption($input, self::OPTION_STACK_TRACE, $filePayload, 'stack_trace'),
            'trace_id' => $this->resolveScalarOption($input, self::OPTION_TRACE_ID, $filePayload, 'trace_id'),
            'trajectory_id' => $this->resolveScalarOption($input, self::OPTION_TRAJECTORY_ID, $filePayload, 'trajectory_id'),
            'workflow' => $this->resolveScalarOption($input, self::OPTION_WORKFLOW, $filePayload, 'workflow', false, 'agent_execution'),
            'model' => $this->resolveScalarOption($input, self::OPTION_MODEL, $filePayload, 'model', false, 'unknown_model'),
            'headers' => $this->resolveJsonOption($input, self::OPTION_HEADERS_JSON, $filePayload, 'headers'),
            'details' => $this->resolveJsonOption($input, self::OPTION_DETAILS_JSON, $filePayload, 'details'),
            'metadata' => $this->resolveJsonOption($input, self::OPTION_METADATA_JSON, $filePayload, 'metadata'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadPayloadFile(string $filePath): array
    {
        $filePath = trim($filePath);
        if ($filePath === '') {
            return [];
        }

        if (!is_file($filePath)) {
            throw new \InvalidArgumentException(sprintf('The --%s file does not exist: %s', self::OPTION_PAYLOAD_FILE, $filePath));
        }

        $contents = file_get_contents($filePath);
        if ($contents === false) {
            throw new \InvalidArgumentException(sprintf('Unable to read --%s file: %s', self::OPTION_PAYLOAD_FILE, $filePath));
        }

        try {
            $decoded = $this->serializer->unserialize($contents);
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException(
                sprintf('The --%s file must contain valid JSON: %s', self::OPTION_PAYLOAD_FILE, $exception->getMessage()),
                0,
                $exception
            );
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $filePayload
     */
    private function resolveScalarOption(
        InputInterface $input,
        string $optionName,
        array $filePayload,
        string $payloadKey,
        bool $required = false,
        string $defaultValue = ''
    ): string {
        $optionValue = $input->getOption($optionName);
        if (is_string($optionValue) && trim($optionValue) !== '') {
            return $optionValue;
        }

        $payloadValue = $filePayload[$payloadKey] ?? null;
        if (is_string($payloadValue) && trim($payloadValue) !== '') {
            return $payloadValue;
        }

        if ($required && $defaultValue === '') {
            throw new \InvalidArgumentException(sprintf('The --%s option is required when not provided by --%s.', $optionName, self::OPTION_PAYLOAD_FILE));
        }

        return $defaultValue;
    }

    /**
     * @param array<string, mixed> $filePayload
     * @return array<string, mixed>
     */
    private function resolveJsonOption(
        InputInterface $input,
        string $optionName,
        array $filePayload,
        string $payloadKey
    ): array {
        $optionValue = (string)$input->getOption($optionName);
        if (trim($optionValue) !== '' && $optionValue !== '{}') {
            return $this->decodeJsonOption($optionValue, $optionName);
        }

        $payloadValue = $filePayload[$payloadKey] ?? null;
        return is_array($payloadValue) ? $payloadValue : [];
    }
}
