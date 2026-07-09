<?php

declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Service\AgentTermination;

use GrupoAwamotos\LogMonitoring\Api\Data\AlertInterface;

class Classifier
{
    private const DEFAULT_WORKFLOWS = [
        'provider_capacity_exhausted' => [
            'agent_execution',
            'conversation_resume',
            'retry_operation',
            'new_conversation_bootstrap'
        ],
        'mcp_configuration_error' => [
            'context_enrichment',
            'tool_bootstrap',
            'documentation_lookup'
        ],
        'remote_session_transport_error' => [
            'remote_ssh_session',
            'agent_manager_resume',
            'workspace_sync'
        ],
        'renderer_resource_failure' => [
            'chat_panel_rendering',
            'agent_manager_rendering',
            'visual_debugging'
        ],
        'conversation_state_corruption' => [
            'conversation_resume',
            'history_loading',
            'agent_manager_resume'
        ],
        'environment_command_unavailable' => [
            'local_recovery_commands',
            'manual_relaunch'
        ],
        'unknown_agent_failure' => [
            'agent_execution'
        ]
    ];

    /**
     * @param array<string, mixed> $incident
     * @param AlertInterface[] $historicalAlerts
     * @return array<string, mixed>
     */
    public function classify(array $incident, array $historicalAlerts = []): array
    {
        $searchable = mb_strtolower($this->buildSearchablePayload($incident));
        $matches = $this->matchPatterns($searchable);
        $primaryMatch = $matches[0] ?? $this->buildUnknownMatch();
        $retryDelaySeconds = $this->extractRetryDelaySeconds($incident);

        return [
            'classification' => [
                'code' => $primaryMatch['code'],
                'label' => $primaryMatch['label'],
                'severity' => $this->resolveSeverity($primaryMatch['severity'], $historicalAlerts, $primaryMatch['code']),
                'confidence' => $primaryMatch['confidence'],
                'retry_delay_seconds' => $retryDelaySeconds,
                'contributing_factors' => array_values(array_map(
                    static fn(array $match): string => $match['code'],
                    array_slice($matches, 1)
                )),
            ],
            'root_cause' => $primaryMatch['root_cause'],
            'evidence' => [
                'exact_error_message' => $this->extractExactErrorMessage($incident),
                'stack_trace' => $this->extractStackTrace($incident),
                'trace_id' => (string)($incident['trace_id'] ?? ''),
                'trajectory_id' => (string)($incident['trajectory_id'] ?? ''),
                'headers' => is_array($incident['headers'] ?? null) ? $incident['headers'] : [],
            ],
            'frequency_analysis' => $this->buildFrequencyAnalysis($historicalAlerts, $primaryMatch['code']),
            'affected_workflows' => $this->buildAffectedWorkflows($incident, $primaryMatch['code']),
        ];
    }

    /**
     * @param array<string, mixed> $incident
     */
    private function buildSearchablePayload(array $incident): string
    {
        $parts = [
            (string)($incident['message'] ?? ''),
            (string)($incident['stack_trace'] ?? ''),
            (string)($incident['workflow'] ?? ''),
            (string)($incident['model'] ?? ''),
            json_encode($incident['headers'] ?? [], JSON_UNESCAPED_SLASHES) ?: '',
            json_encode($incident['details'] ?? [], JSON_UNESCAPED_SLASHES) ?: '',
            json_encode($incident['metadata'] ?? [], JSON_UNESCAPED_SLASHES) ?: '',
        ];

        return implode("\n", array_filter($parts));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function matchPatterns(string $searchable): array
    {
        $patterns = [
            [
                'code' => 'provider_capacity_exhausted',
                'label' => 'Provider Capacity Exhausted',
                'severity' => AlertInterface::SEVERITY_HIGH,
                'confidence' => 0.99,
                'root_cause' => 'The upstream model backend rejected the request with HTTP 503 because the selected model had no serving capacity.',
                'needles' => [
                    'model_capacity_exhausted',
                    'no capacity available for model',
                    'high traffic right now',
                    'status":"unavailable"',
                    '503 service unavailable'
                ],
            ],
            [
                'code' => 'mcp_configuration_error',
                'label' => 'MCP Configuration Error',
                'severity' => AlertInterface::SEVERITY_MEDIUM,
                'confidence' => 0.96,
                'root_cause' => 'An MCP server was registered without a valid command or server URL, causing agent bootstrapping and tool availability failures.',
                'needles' => [
                    'serverurl or command must be specified',
                    'mcp error',
                    'context7'
                ],
            ],
            [
                'code' => 'remote_session_transport_error',
                'label' => 'Remote Session Transport Error',
                'severity' => AlertInterface::SEVERITY_HIGH,
                'confidence' => 0.92,
                'root_cause' => 'The remote agent transport became unavailable, typically due to Remote-SSH session instability or a crashed remote host process.',
                'needles' => [
                    'econnrefused',
                    'remoteagent',
                    'connection refused',
                    'remote-ssh'
                ],
            ],
            [
                'code' => 'renderer_resource_failure',
                'label' => 'Renderer or GPU Resource Failure',
                'severity' => AlertInterface::SEVERITY_HIGH,
                'confidence' => 0.9,
                'root_cause' => 'The Electron renderer or GPU process stalled, causing the chat webview or full window to freeze.',
                'needles' => [
                    'gpu process lost',
                    'window not responding',
                    'renderer',
                    'disable-gpu'
                ],
            ],
            [
                'code' => 'conversation_state_corruption',
                'label' => 'Conversation State Corruption',
                'severity' => AlertInterface::SEVERITY_MEDIUM,
                'confidence' => 0.88,
                'root_cause' => 'A persisted conversation entered an invalid state and causes the agent UI to fail when resumed.',
                'needles' => [
                    'one moment, the agent is currently loading',
                    'conversation',
                    'resume',
                    'agent manager'
                ],
            ],
            [
                'code' => 'environment_command_unavailable',
                'label' => 'Environment Command Unavailable',
                'severity' => AlertInterface::SEVERITY_MEDIUM,
                'confidence' => 0.97,
                'root_cause' => 'The recovery command was executed in the wrong environment, so the required CLI executable was not present.',
                'needles' => [
                    "command 'code' not found",
                    'sudo snap install code'
                ],
            ],
        ];

        $matches = [];
        foreach ($patterns as $pattern) {
            foreach ($pattern['needles'] as $needle) {
                if (str_contains($searchable, $needle)) {
                    $matches[] = $pattern;
                    break;
                }
            }
        }

        usort($matches, static function (array $left, array $right): int {
            return $right['confidence'] <=> $left['confidence'];
        });

        return $matches;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUnknownMatch(): array
    {
        return [
            'code' => 'unknown_agent_failure',
            'label' => 'Unknown Agent Failure',
            'severity' => AlertInterface::SEVERITY_MEDIUM,
            'confidence' => 0.35,
            'root_cause' => 'The supplied evidence is insufficient to classify the termination beyond a generic agent failure.',
        ];
    }

    /**
     * @param AlertInterface[] $historicalAlerts
     * @return array<string, mixed>
     */
    private function buildFrequencyAnalysis(array $historicalAlerts, string $classificationCode): array
    {
        $now = time();
        $matchingTotal = 0;
        $matchingLast24h = 0;
        $matchingLast7d = 0;

        foreach ($historicalAlerts as $alert) {
            $context = $alert->getContextData() ?? [];
            $contextClassification = $context['analysis']['classification']['code'] ?? null;
            if ($contextClassification !== $classificationCode) {
                continue;
            }

            $matchingTotal += max(1, $alert->getOccurrences());
            $createdAt = strtotime((string)$alert->getCreatedAt());

            if ($createdAt !== false && $createdAt >= ($now - 86400)) {
                $matchingLast24h += max(1, $alert->getOccurrences());
            }

            if ($createdAt !== false && $createdAt >= ($now - 604800)) {
                $matchingLast7d += max(1, $alert->getOccurrences());
            }
        }

        return [
            'historical_matches' => $matchingTotal,
            'matches_last_24h' => $matchingLast24h,
            'matches_last_7d' => $matchingLast7d,
            'trend' => $this->resolveTrend($matchingLast24h, $matchingLast7d),
        ];
    }

    /**
     * @param array<string, mixed> $incident
     * @return list<string>
     */
    private function buildAffectedWorkflows(array $incident, string $classificationCode): array
    {
        $workflows = self::DEFAULT_WORKFLOWS[$classificationCode] ?? self::DEFAULT_WORKFLOWS['unknown_agent_failure'];
        $incidentWorkflow = trim((string)($incident['workflow'] ?? ''));
        if ($incidentWorkflow !== '') {
            $workflows[] = $incidentWorkflow;
        }

        return array_values(array_unique($workflows));
    }

    /**
     * @param AlertInterface[] $historicalAlerts
     */
    private function resolveSeverity(string $baseSeverity, array $historicalAlerts, string $classificationCode): string
    {
        $frequency = $this->buildFrequencyAnalysis($historicalAlerts, $classificationCode);
        if ($baseSeverity !== AlertInterface::SEVERITY_CRITICAL && $frequency['matches_last_24h'] >= 3) {
            return AlertInterface::SEVERITY_CRITICAL;
        }

        return $baseSeverity;
    }

    private function resolveTrend(int $last24h, int $last7d): string
    {
        if ($last24h >= 3) {
            return 'spiking';
        }

        if ($last7d >= 5) {
            return 'recurring';
        }

        if ($last7d >= 1) {
            return 'intermittent';
        }

        return 'new';
    }

    /**
     * @param array<string, mixed> $incident
     */
    private function extractExactErrorMessage(array $incident): string
    {
        if (isset($incident['details']['error']['message']) && is_string($incident['details']['error']['message'])) {
            return $incident['details']['error']['message'];
        }

        return (string)($incident['message'] ?? 'Agent terminated due to error');
    }

    /**
     * @param array<string, mixed> $incident
     */
    private function extractStackTrace(array $incident): string
    {
        $stackTrace = trim((string)($incident['stack_trace'] ?? ''));
        if ($stackTrace !== '') {
            return $stackTrace;
        }

        return 'Not available in supplied incident payload.';
    }

    /**
     * @param array<string, mixed> $incident
     */
    private function extractRetryDelaySeconds(array $incident): ?int
    {
        $details = $incident['details']['error']['details'] ?? [];
        if (!is_array($details)) {
            return null;
        }

        foreach ($details as $detail) {
            if (!is_array($detail)) {
                continue;
            }

            $retryDelay = $detail['retryDelay'] ?? null;
            if (!is_string($retryDelay)) {
                continue;
            }

            if (preg_match('/^(\d+)s$/', $retryDelay, $matches) === 1) {
                return (int)$matches[1];
            }
        }

        return null;
    }
}
