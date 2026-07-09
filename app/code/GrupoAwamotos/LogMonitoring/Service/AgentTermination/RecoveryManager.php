<?php

declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Service\AgentTermination;

class RecoveryManager
{
    public const SUPPORT_URL = 'https://antigravity.google/support';

    /**
     * @param array<string, mixed> $analysis
     * @return array<string, mixed>
     */
    public function buildRecoveryPlan(array $analysis): array
    {
        $classification = $analysis['classification']['code'] ?? 'unknown_agent_failure';
        $retryDelay = (int)($analysis['classification']['retry_delay_seconds'] ?? 0);

        return match ($classification) {
            'provider_capacity_exhausted' => $this->buildProviderCapacityPlan($retryDelay),
            'mcp_configuration_error' => $this->buildMcpPlan(),
            'remote_session_transport_error' => $this->buildRemoteSessionPlan(),
            'renderer_resource_failure' => $this->buildRendererPlan(),
            'conversation_state_corruption' => $this->buildConversationPlan(),
            'environment_command_unavailable' => $this->buildEnvironmentPlan(),
            default => $this->buildGenericPlan(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProviderCapacityPlan(int $retryDelay): array
    {
        $effectiveDelay = $retryDelay > 0 ? $retryDelay : 45;

        return [
            'mode' => 'graceful_degradation',
            'automatic_retry_policy' => [
                'enabled' => true,
                'max_attempts' => 2,
                'base_delay_seconds' => $effectiveDelay,
                'backoff_strategy' => 'exponential_with_jitter',
                'stop_conditions' => [
                    'same_model_returns_http_503_twice',
                    'classification_changes_to_local_failure'
                ],
            ],
            'graceful_degradation' => [
                'preserve_partial_artifacts' => true,
                'allow_retry_action' => true,
                'allow_new_conversation_action' => true,
                'user_message' => 'Surface the exact provider saturation error, keep generated files untouched, and present Retry plus Start New Conversation actions.',
            ],
            'recommended_fixes' => [
                'Wait the provider retry delay before the first retry attempt.',
                'Switch from the overloaded high-capacity model to a balanced or standard model before reopening the workflow.',
                'Avoid infinite retries on the same model and terminate cleanly after the second 503.',
                'Use the Antigravity support page for provider-side outage guidance if the condition persists.',
            ],
            'monitoring_alerts' => [
                'severity' => 'high',
                'alert_when' => [
                    'two provider-capacity terminations happen within 24 hours',
                    'the retry path fails twice in the same session'
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMcpPlan(): array
    {
        return [
            'mode' => 'graceful_degradation',
            'automatic_retry_policy' => [
                'enabled' => false,
                'max_attempts' => 0,
                'base_delay_seconds' => 0,
                'backoff_strategy' => 'none',
                'stop_conditions' => [
                    'configuration_not_fixed'
                ],
            ],
            'graceful_degradation' => [
                'preserve_partial_artifacts' => true,
                'allow_retry_action' => true,
                'allow_new_conversation_action' => true,
                'user_message' => 'Disable or repair the failing MCP server, continue with a reduced toolset, and keep the rest of the workspace available.',
            ],
            'recommended_fixes' => [
                'Repair the MCP server definition so it includes a valid command or server URL.',
                'Disable the failing MCP integration until validation passes.',
                'Retry the workflow only after the MCP bootstrap error disappears from the IDE.',
            ],
            'monitoring_alerts' => [
                'severity' => 'medium',
                'alert_when' => [
                    'the same MCP bootstrap error occurs in consecutive sessions'
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRemoteSessionPlan(): array
    {
        return [
            'mode' => 'graceful_degradation',
            'automatic_retry_policy' => [
                'enabled' => true,
                'max_attempts' => 1,
                'base_delay_seconds' => 20,
                'backoff_strategy' => 'fixed',
                'stop_conditions' => [
                    'remote_transport_still_refuses_connections'
                ],
            ],
            'graceful_degradation' => [
                'preserve_partial_artifacts' => true,
                'allow_retry_action' => true,
                'allow_new_conversation_action' => true,
                'user_message' => 'Preserve the current files, reconnect Remote-SSH once, and fall back to a fresh conversation if the transport cannot be restored.',
            ],
            'recommended_fixes' => [
                'Reconnect Remote-SSH and confirm the remote extension host is healthy.',
                'Keep browser relay and heavy MCP relay extensions disabled on remote sessions.',
                'Escalate recurring ECONNREFUSED incidents as infrastructure instability.',
            ],
            'monitoring_alerts' => [
                'severity' => 'high',
                'alert_when' => [
                    'remote transport failures recur after one reconnect attempt'
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRendererPlan(): array
    {
        return [
            'mode' => 'graceful_degradation',
            'automatic_retry_policy' => [
                'enabled' => false,
                'max_attempts' => 0,
                'base_delay_seconds' => 0,
                'backoff_strategy' => 'none',
                'stop_conditions' => [
                    'renderer_or_gpu_process_is_still_unstable'
                ],
            ],
            'graceful_degradation' => [
                'preserve_partial_artifacts' => true,
                'allow_retry_action' => true,
                'allow_new_conversation_action' => true,
                'user_message' => 'Restart the IDE with GPU acceleration disabled and continue in a lighter workspace profile until the renderer is stable.',
            ],
            'recommended_fixes' => [
                'Relaunch the local VS Code or Antigravity client with GPU acceleration disabled.',
                'Use a clean profile with only Antigravity and Remote-SSH enabled.',
                'Re-enable nonessential extensions only after the chat panel remains stable.',
            ],
            'monitoring_alerts' => [
                'severity' => 'high',
                'alert_when' => [
                    'two renderer freezes happen in the same day'
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildConversationPlan(): array
    {
        return [
            'mode' => 'graceful_degradation',
            'automatic_retry_policy' => [
                'enabled' => false,
                'max_attempts' => 0,
                'base_delay_seconds' => 0,
                'backoff_strategy' => 'none',
                'stop_conditions' => [
                    'corrupted_conversation_is_not_isolated'
                ],
            ],
            'graceful_degradation' => [
                'preserve_partial_artifacts' => true,
                'allow_retry_action' => true,
                'allow_new_conversation_action' => true,
                'user_message' => 'Interrupt the failing conversation, quarantine it if needed, and let the user start a clean conversation without losing file changes.',
            ],
            'recommended_fixes' => [
                'Open the recent conversation in Agent Manager and interrupt it immediately.',
                'If the IDE still loops, quarantine the most recent conversation state before reopening the client.',
                'Resume work from a new conversation after the corrupted thread is isolated.',
            ],
            'monitoring_alerts' => [
                'severity' => 'medium',
                'alert_when' => [
                    'the same workspace triggers repeated conversation recovery events'
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEnvironmentPlan(): array
    {
        return [
            'mode' => 'graceful_degradation',
            'automatic_retry_policy' => [
                'enabled' => false,
                'max_attempts' => 0,
                'base_delay_seconds' => 0,
                'backoff_strategy' => 'none',
                'stop_conditions' => [
                    'recovery_command_is_executed_in_the_wrong_environment'
                ],
            ],
            'graceful_degradation' => [
                'preserve_partial_artifacts' => true,
                'allow_retry_action' => true,
                'allow_new_conversation_action' => true,
                'user_message' => 'Run local desktop recovery commands only on the local machine, not inside the remote Linux workspace terminal.',
            ],
            'recommended_fixes' => [
                'Execute local VS Code launch commands from the Windows machine that hosts the UI.',
                'Do not attempt to run the `code` desktop launcher inside the remote Linux shell unless it is intentionally installed there.',
                'Separate remote Magento shell remediation from local Antigravity desktop remediation.',
            ],
            'monitoring_alerts' => [
                'severity' => 'medium',
                'alert_when' => [
                    'operators repeatedly run local desktop commands on the remote server'
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGenericPlan(): array
    {
        return [
            'mode' => 'graceful_degradation',
            'automatic_retry_policy' => [
                'enabled' => true,
                'max_attempts' => 1,
                'base_delay_seconds' => 30,
                'backoff_strategy' => 'fixed',
                'stop_conditions' => [
                    'failure_repeats_without_new_evidence'
                ],
            ],
            'graceful_degradation' => [
                'preserve_partial_artifacts' => true,
                'allow_retry_action' => true,
                'allow_new_conversation_action' => true,
                'user_message' => 'Retry once with preserved artifacts, then guide the user to a fresh conversation and targeted diagnostics if the error repeats.',
            ],
            'recommended_fixes' => [
                'Capture the exact error payload and logs before retrying.',
                'Retry once, then start a new conversation if the same termination repeats.',
                'Use the Antigravity support resource for escalation when local remediation does not change the outcome.',
            ],
            'monitoring_alerts' => [
                'severity' => 'medium',
                'alert_when' => [
                    'the same unknown failure recurs across multiple sessions'
                ],
            ],
        ];
    }
}
