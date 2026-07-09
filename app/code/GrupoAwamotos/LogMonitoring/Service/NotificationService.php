<?php

declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class NotificationService
{
    private const CONFIG_PATH_EMAIL_ENABLED = 'log_monitoring/notifications/email_enabled';
    private const CONFIG_PATH_EMAIL_RECIPIENTS = 'log_monitoring/notifications/email_recipients';
    private const CONFIG_PATH_SLACK_ENABLED = 'log_monitoring/notifications/slack_enabled';
    private const CONFIG_PATH_SLACK_WEBHOOK = 'log_monitoring/notifications/slack_webhook';
    private const CONFIG_PATH_WEBHOOK_ENABLED = 'log_monitoring/notifications/webhook_enabled';
    private const CONFIG_PATH_WEBHOOK_URL = 'log_monitoring/notifications/webhook_url';

    private ScopeConfigInterface $scopeConfig;
    private TransportBuilder $transportBuilder;
    private StateInterface $inlineTranslation;
    private Curl $slackClient;
    private Curl $webhookClient;
    private LoggerInterface $logger;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        Curl $slackClient,
        Curl $webhookClient,
        LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->slackClient = $slackClient;
        $this->webhookClient = $webhookClient;
        $this->logger = $logger;
    }

    public function sendAlert(array $alertData): array
    {
        $results = [
            'email' => false,
            'slack' => false,
            'webhook' => false
        ];

        try {
            // Send email notification
            if ($this->isEmailEnabled()) {
                $results['email'] = $this->sendEmailAlert($alertData);
            }

            // Send Slack notification
            if ($this->isSlackEnabled()) {
                $results['slack'] = $this->sendSlackAlert($alertData);
            }

            // Send webhook notification
            if ($this->isWebhookEnabled()) {
                $results['webhook'] = $this->sendWebhookAlert($alertData);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error sending alert notifications: ' . $e->getMessage());
        }

        return $results;
    }

    public function sendSystemHealthAlert(array $healthData): array
    {
        $alertData = [
            'type' => 'system_health',
            'severity' => $this->getSeverityFromScore($healthData['overall_score']),
            'title' => 'System Health Alert',
            'message' => $this->formatHealthMessage($healthData),
            'context' => $healthData
        ];

        return $this->sendAlert($alertData);
    }

    public function sendPerformanceAlert(array $performanceData): array
    {
        $alertData = [
            'type' => 'performance',
            'severity' => 'medium',
            'title' => 'Performance Alert',
            'message' => $this->formatPerformanceMessage($performanceData),
            'context' => $performanceData
        ];

        return $this->sendAlert($alertData);
    }

    public function testNotifications(): array
    {
        $testAlert = [
            'type' => 'test',
            'severity' => 'low',
            'title' => 'AWA Motos - Test Notification',
            'message' => 'This is a test notification from the AWA Motos log monitoring system.',
            'context' => [
                'timestamp' => date('Y-m-d H:i:s'),
                'test' => true
            ]
        ];

        return $this->sendAlert($testAlert);
    }

    private function sendEmailAlert(array $alertData): bool
    {
        try {
            $recipients = $this->getEmailRecipients();
            if (empty($recipients)) {
                return false;
            }

            $this->inlineTranslation->suspend();

            $templateVars = [
                'alert_type' => $alertData['type'],
                'severity' => $alertData['severity'],
                'title' => $alertData['title'],
                'message' => $alertData['message'],
                'timestamp' => date('Y-m-d H:i:s'),
                'context' => $alertData['context'] ?? []
            ];

            $transport = $this->transportBuilder
                ->setTemplateIdentifier('log_monitoring_alert_email')
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => \Magento\Store\Model\Store::DEFAULT_STORE_ID,
                ])
                ->setTemplateVars($templateVars)
                ->setFromByScope([
                    'email' => 'monitoring@awamotos.com.br',
                    'name' => 'AWA Motos Monitoring'
                ])
                ->addTo($recipients)
                ->getTransport();

            $transport->sendMessage();
            $this->inlineTranslation->resume();

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error sending email alert: ' . $e->getMessage());
            $this->inlineTranslation->resume();
            return false;
        }
    }

    private function sendSlackAlert(array $alertData): bool
    {
        try {
            $webhookUrl = $this->getSlackWebhookUrl();
            if (!$webhookUrl) {
                return false;
            }

            $color = $this->getSlackColorForSeverity($alertData['severity']);
            $payload = [
                'username' => 'AWA Motos Monitoring',
                'icon_emoji' => ':warning:',
                'attachments' => [
                    [
                        'color' => $color,
                        'title' => $alertData['title'],
                        'text' => $alertData['message'],
                        'fields' => [
                            [
                                'title' => 'Type',
                                'value' => $alertData['type'],
                                'short' => true
                            ],
                            [
                                'title' => 'Severity',
                                'value' => strtoupper($alertData['severity']),
                                'short' => true
                            ],
                            [
                                'title' => 'Time',
                                'value' => date('Y-m-d H:i:s'),
                                'short' => true
                            ]
                        ],
                        'footer' => 'AWA Motos Log Monitoring',
                        'ts' => time()
                    ]
                ]
            ];

            $this->slackClient->setHeaders(['Content-Type' => 'application/json']);
            $this->slackClient->post($webhookUrl, json_encode($payload));

            $httpCode = $this->slackClient->getStatus();
            return $httpCode >= 200 && $httpCode < 300;
        } catch (\Exception $e) {
            $this->logger->error('Error sending Slack alert: ' . $e->getMessage());
            return false;
        }
    }

    private function sendWebhookAlert(array $alertData): bool
    {
        try {
            $webhookUrl = $this->getWebhookUrl();
            if (!$webhookUrl) {
                return false;
            }

            $payload = [
                'source' => 'awa_motos_log_monitoring',
                'alert' => $alertData,
                'timestamp' => date('c'),
                'server' => gethostname(),
                'environment' => $this->getEnvironment()
            ];

            $this->webhookClient->setHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => 'AWA-Motos-Monitoring/1.0'
            ]);
            $this->webhookClient->post($webhookUrl, json_encode($payload));

            $httpCode = $this->webhookClient->getStatus();
            return $httpCode >= 200 && $httpCode < 300;
        } catch (\Exception $e) {
            $this->logger->error('Error sending webhook alert: ' . $e->getMessage());
            return false;
        }
    }

    private function isEmailEnabled(): bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::CONFIG_PATH_EMAIL_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    private function isSlackEnabled(): bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::CONFIG_PATH_SLACK_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    private function isWebhookEnabled(): bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::CONFIG_PATH_WEBHOOK_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    private function getEmailRecipients(): array
    {
        $recipients = $this->scopeConfig->getValue(
            self::CONFIG_PATH_EMAIL_RECIPIENTS,
            ScopeInterface::SCOPE_STORE
        );

        if (!$recipients) {
            return [];
        }

        return array_map('trim', explode(',', $recipients));
    }

    private function getSlackWebhookUrl(): ?string
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_PATH_SLACK_WEBHOOK,
            ScopeInterface::SCOPE_STORE
        );
    }

    private function getWebhookUrl(): ?string
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_PATH_WEBHOOK_URL,
            ScopeInterface::SCOPE_STORE
        );
    }

    private function getSeverityFromScore(float $score): string
    {
        if ($score >= 80) {
            return 'low';
        } elseif ($score >= 60) {
            return 'medium';
        } elseif ($score >= 40) {
            return 'high';
        } else {
            return 'critical';
        }
    }

    private function getSlackColorForSeverity(string $severity): string
    {
        switch ($severity) {
            case 'critical':
                return 'danger';
            case 'high':
                return 'warning';
            case 'medium':
                return '#ff9500';
            case 'low':
                return 'good';
            default:
                return '#cccccc';
        }
    }

    private function formatHealthMessage(array $healthData): string
    {
        $status = $healthData['overall_status'];
        $score = $healthData['overall_score'];

        $message = "System Health Status: {$status} (Score: {$score}/100)\n\n";

        if (isset($healthData['components'])) {
            foreach ($healthData['components'] as $component => $data) {
                if (isset($data['issues']) && !empty($data['issues'])) {
                    $message .= "• {$component}: " . implode(', ', $data['issues']) . "\n";
                }
            }
        }

        if (isset($healthData['recommendations'])) {
            $message .= "\nRecommendations:\n";
            foreach ($healthData['recommendations'] as $recommendation) {
                $message .= "• {$recommendation}\n";
            }
        }

        return $message;
    }

    private function formatPerformanceMessage(array $performanceData): string
    {
        $message = "Performance Alert Triggered\n\n";

        foreach ($performanceData as $metric => $value) {
            if (is_numeric($value)) {
                $message .= "• " . ucwords(str_replace('_', ' ', $metric)) . ": {$value}\n";
            }
        }

        return $message;
    }

    private function getEnvironment(): string
    {
        return getenv('MAGE_MODE') ?: 'production';
    }
}
