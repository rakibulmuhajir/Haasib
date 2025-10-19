<?php

namespace Modules\Reporting\Actions\Reports;

use App\Mail\ReportDownloadLinkMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class DeliverReportAction
{
    /**
     * Deliver a report through specified channels
     */
    public function execute(string $reportId, array $deliveryData = []): array
    {
        $report = $this->getReport($reportId);

        $deliveryRecords = $this->getPendingDeliveries($reportId);

        $results = [];

        foreach ($deliveryRecords as $delivery) {
            $target = json_decode($delivery['target'], true);

            try {
                switch ($delivery['channel']) {
                    case 'email':
                        $results[$delivery['delivery_id']] = $this->deliverByEmail($report, $target, $deliveryData);
                        break;

                    case 'sftp':
                        $results[$delivery['delivery_id']] = $this->deliverBySftp($report, $target);
                        break;

                    case 'webhook':
                        $results[$delivery['delivery_id']] = $this->deliverByWebhook($report, $target);
                        break;

                    case 'in_app':
                        $results[$delivery['delivery_id']] = $this->deliverInApp($report, $target);
                        break;

                    default:
                        $results[$delivery['delivery_id']] = [
                            'status' => 'failed',
                            'error' => "Unknown delivery channel: {$delivery['channel']}",
                        ];
                        break;
                }

                // Update delivery status
                $this->updateDeliveryStatus($delivery['delivery_id'], $results[$delivery['delivery_id']]);

            } catch (\Exception $e) {
                $results[$delivery['delivery_id']] = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];

                $this->updateDeliveryStatus($delivery['delivery_id'], $results[$delivery['delivery_id']]);

                Log::error('Report delivery failed', [
                    'delivery_id' => $delivery['delivery_id'],
                    'report_id' => $reportId,
                    'channel' => $delivery['channel'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'report_id' => $reportId,
            'deliveries' => $results,
            'summary' => $this->summarizeDeliveryResults($results),
        ];
    }

    /**
     * Generate a secure download token for a report
     */
    public function generateDownloadToken(string $reportId, array $options = []): string
    {
        $report = $this->getReport($reportId);

        $tokenData = [
            'report_id' => $reportId,
            'company_id' => $report['company_id'],
            'expires_at' => now()->addMinutes($options['expires_minutes'] ?? 10),
            'single_use' => $options['single_use'] ?? true,
            'ip_restricted' => $options['ip_restricted'] ?? false,
            'user_id' => $options['user_id'] ?? null,
        ];

        $token = \Illuminate\Support\Str::random(40);

        // Store token with metadata
        Cache::put("report_download_token:{$token}", $tokenData, now()->addMinutes($options['expires_minutes'] ?? 10));

        Log::info('Download token generated', [
            'report_id' => $reportId,
            'token' => $token,
            'expires_at' => $tokenData['expires_at'],
        ]);

        return $token;
    }

    /**
     * Validate and consume a download token
     */
    public function validateDownloadToken(string $token, array $context = []): array
    {
        $tokenData = Cache::get("report_download_token:{$token}");

        if (! $tokenData) {
            throw new \InvalidArgumentException('Invalid or expired download token');
        }

        // Check expiration
        if (Carbon::parse($tokenData['expires_at'])->isPast()) {
            Cache::forget("report_download_token:{$token}");
            throw new \InvalidArgumentException('Download token has expired');
        }

        // Check company context
        if (isset($context['company_id']) && $tokenData['company_id'] !== $context['company_id']) {
            throw new \InvalidArgumentException('Download token is not valid for this company');
        }

        // Check user context if required
        if (isset($tokenData['user_id']) && isset($context['user_id'])) {
            if ($tokenData['user_id'] !== $context['user_id']) {
                throw new \InvalidArgumentException('Download token is not valid for this user');
            }
        }

        // Check IP restriction if enabled
        if ($tokenData['ip_restricted'] && isset($context['ip'])) {
            // You could add IP validation logic here
            // For now, we'll just log the access
            Log::info('Token access from IP', [
                'token' => $token,
                'ip' => $context['ip'],
            ]);
        }

        // Consume token if single use
        if ($tokenData['single_use']) {
            Cache::forget("report_download_token:{$token}");
        }

        return $tokenData;
    }

    /**
     * Deliver report by email
     */
    private function deliverByEmail(array $report, array $target, array $deliveryData): array
    {
        $recipients = $target['recipients'] ?? [];
        $subject = $target['subject'] ?? "Report: {$report['name']}";
        $message = $target['message'] ?? null;
        $includeDownloadLink = $target['include_download_link'] ?? true;

        if (empty($recipients)) {
            throw new \InvalidArgumentException('No recipients specified for email delivery');
        }

        // Generate download link if requested
        $downloadUrl = null;
        $token = null;

        if ($includeDownloadLink && $report['file_path']) {
            try {
                $token = $this->generateDownloadToken($report['report_id'], [
                    'single_use' => false,
                    'expires_minutes' => 60,
                    'user_id' => $deliveryData['user_id'] ?? null,
                ]);

                $downloadUrl = url("/api/reporting/reports/{$report['report_id']}/download?token={$token}");
            } catch (\Exception $e) {
                Log::warning('Failed to generate download link for email', [
                    'report_id' => $report['report_id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $mailable = new ReportDownloadLinkMail($report, $recipients, $subject, $message, $downloadUrl);

            Mail::to($recipients)->send($mailable);

            return [
                'status' => 'sent',
                'message' => 'Email sent successfully',
                'recipients' => $recipients,
                'download_url' => $downloadUrl,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send email', [
                'report_id' => $report['report_id'],
                'recipients' => $recipients,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to send email: '.$e->getMessage());
        }
    }

    /**
     * Deliver report by SFTP
     */
    private function deliverBySftp(array $report, array $target): array
    {
        $host = $target['host'] ?? null;
        $port = $target['port'] ?? 22;
        $username = $target['username'] ?? null;
        $password = $target['password'] ?? null;
        $path = $target['path'] ?? '/reports/';
        $filename = $target['filename'] ?? $this->generateSftpFilename($report);

        if (! $host || ! $username || ! $password) {
            throw new \InvalidArgumentException('SFTP configuration incomplete (host, username, password required)');
        }

        if (! $report['file_path'] || ! Storage::exists($report['file_path'])) {
            throw new \InvalidArgumentException('Report file not found for SFTP delivery');
        }

        try {
            $connection = new \phpseclib\Net_SFTP($host, $port);

            if (! $connection->login($username, $password)) {
                throw new \RuntimeException('SFTP login failed');
            }

            // Change to the target directory
            if (! $connection->mkdir($path, true)) {
                throw new \RuntimeException("Failed to create directory on SFTP server: {$path}");
            }

            $connection->chdir($path);

            // Upload the file
            $remotePath = $path.'/'.$filename;
            $localPath = Storage::path($report['file_path']);

            if (! $connection->put($localPath, $remotePath)) {
                throw new \RuntimeException('Failed to upload file to SFTP server');
            }

            $connection->disconnect();

            return [
                'status' => 'sent',
                'message' => 'File uploaded to SFTP server successfully',
                'remote_path' => $remotePath,
                'filename' => $filename,
                'host' => $host,
                'path' => $path,
            ];

        } catch (\Exception $e) {
            Log::error('SFTP delivery failed', [
                'report_id' => $report['report_id'],
                'host' => $host,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('SFTP delivery failed: '.$e->getMessage());
        }
    }

    /**
     * Deliver report by webhook
     */
    private function deliverByWebhook(array $report, array $target): array
    {
        $url = $target['url'] ?? null;
        $method = $target['method'] ?? 'POST';
        $headers = $target['headers'] ?? [];
        $payload = $target['payload'] ?? [];
        $timeout = $target['timeout'] ?? 30;
        $retryCount = $target['retry_count'] ?? 3;

        if (! $url) {
            throw new \InvalidArgumentException('Webhook URL is required');
        }

        // Add common headers
        $headers['Content-Type'] = 'application/json';
        $headers['User-Agent'] = 'Haasib-Reporting-System/1.0';
        $headers['X-Haasib-Report-ID'] = $report['report_id'];
        $headers['X-Haasib-Report-Type'] = $report['report_type'];

        // Prepare webhook payload
        $webhookData = array_merge([
            'report_id' => $report['report_id'],
            'report_type' => $report['report_type'],
            'name' => $report['name'],
            'status' => $report['status'],
            'generated_at' => $report['generated_at'],
            'file_size' => $report['file_size'],
            'mime_type' => $report['mime_type'],
        ], $payload);

        // Add download token if file exists
        if ($report['file_path'] && Storage::exists($report['file_path'])) {
            try {
                $token = $this->generateDownloadToken($report['report_id'], [
                    'expires_minutes' => 60,
                    'single_use' => false,
                ]);

                $webhookData['download_url'] = url("/api/reporting/reports/{$report['report_id']}/download?token={$token}");
            } catch (\Exception $e) {
                Log::warning('Failed to generate download token for webhook', [
                    'report_id' => $report['report_id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Retry logic
        $lastError = null;

        for ($attempt = 1; $attempt <= $retryCount; $attempt++) {
            try {
                $response = $this->makeHttpRequest($method, $url, $webhookData, $headers, $timeout);

                if ($response['status_code'] >= 200 && $response['status_code'] < 300) {
                    return [
                        'status' => 'sent',
                        'message' => 'Webhook call successful',
                        'url' => $url,
                        'status_code' => $response['status_code'],
                        'attempt' => $attempt,
                    ];
                }

                $lastError = "HTTP {$response['status_code']} - {$response['body']}";

            } catch (\Exception $e) {
                $lastError = $e->getMessage();

                Log::warning('Webhook delivery attempt failed', [
                    'report_id' => $report['report_id'],
                    'attempt' => $attempt,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < $retryCount) {
                    sleep(min(2 ** $attempt, 10)); // Exponential backoff, max 10 seconds
                }
            }
        }

        throw new \RuntimeException("Webhook delivery failed after {$retryCount} attempts. Last error: {$lastError}");
    }

    /**
     * Deliver report in-app (just mark as delivered)
     */
    private function deliverInApp(array $report, array $target): array
    {
        $notificationType = $target['notification_type'] ?? 'info';
        $message = $target['message'] ?? 'Report generated successfully';

        // For in-app delivery, we just mark it as sent
        // The actual notification would be handled by the frontend
        return [
            'status' => 'sent',
            'message' => $message,
            'notification_type' => $notificationType,
        ];
    }

    /**
     * Get report details
     */
    private function getReport(string $reportId): array
    {
        $report = DB::table('rpt.reports')
            ->where('report_id', $reportId)
            ->first();

        if (! $report) {
            throw new \InvalidArgumentException('Report not found');
        }

        return (array) $report;
    }

    /**
     * Get pending deliveries for a report
     */
    private function getPendingDeliveries(string $reportId): array
    {
        return DB::table('rpt.report_deliveries')
            ->where('report_id', $reportId)
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * Update delivery status
     */
    private function updateDeliveryStatus(string $deliveryId, array $result): void
    {
        $status = $result['status'] ?? 'failed';
        $error = $result['error'] ?? null;

        $updateData = [
            'status' => $status,
            'updated_at' => now(),
        ];

        if ($status === 'sent') {
            $updateData['sent_at'] = now();
        }

        if ($error) {
            $updateData['failure_reason'] = $error;
        }

        DB::table('rpt.report_deliveries')
            ->where('delivery_id', $deliveryId)
            ->update($updateData);
    }

    /**
     * Summarize delivery results
     */
    private function summarizeDeliveryResults(array $results): array
    {
        $summary = [
            'total' => count($results),
            'sent' => 0,
            'failed' => 0,
            'pending' => 0,
            'channels' => [],
        ];

        foreach ($results as $deliveryId => $result) {
            $status = $result['status'] ?? 'failed';
            $summary[$status]++;

            // Extract channel info if available
            $delivery = DB::table('rpt.report_deliveries')
                ->where('delivery_id', $deliveryId)
                ->first();

            if ($delivery) {
                $channel = $delivery->channel;
                if (! isset($summary['channels'][$channel])) {
                    $summary['channels'][$channel] = ['sent' => 0, 'failed' => 0];
                }
                $summary['channels'][$channel][$status]++;
            }
        }

        return $summary;
    }

    /**
     * Generate SFTP filename
     */
    private function generateSftpFilename(array $report): string
    {
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $report['name']);
        $date = now()->format('Y-m-d_His');
        $reportType = $report['report_type'];
        $extension = pathinfo($report['file_name'] ?? '', PATHINFO_EXTENSION) ?: 'pdf';

        return "{$name}_{$reportType}_{$date}.{$extension}";
    }

    /**
     * Make HTTP request
     */
    private function makeHttpRequest(string $method, string $url, array $data, array $headers = [], int $timeout = 30): array
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPE, false);

        // Set method
        $method = strtoupper($method);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        // Set headers
        if (! empty($headers)) {
            $headerArray = [];
            foreach ($headers as $key => $value) {
                $headerArray[] = "{$key}: {$value}";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
        }

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status_code' => $status,
            'body' => $response,
        ];
    }
}
