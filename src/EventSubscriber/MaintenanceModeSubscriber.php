<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);


namespace App\EventSubscriber;

use App\Services\System\UpdateExecutor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

/**
 * Blocks all web requests when maintenance mode is enabled during updates.
 */
readonly class MaintenanceModeSubscriber implements EventSubscriberInterface
{
    public function __construct(private UpdateExecutor $updateExecutor)
    {

    }

    public static function getSubscribedEvents(): array
    {
        return [
            // High priority to run before other listeners
            KernelEvents::REQUEST => ['onKernelRequest', 512], //High priority to run before other listeners
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Only handle main requests
        if (!$event->isMainRequest()) {
            return;
        }

        // Skip if not in maintenance mode
        if (!$this->updateExecutor->isMaintenanceMode()) {
            return;
        }

        //Allow to view the progress page
        if (preg_match('#^/\w{2}/system/update-manager/progress#', $event->getRequest()->getPathInfo())) {
            return;
        }

        // Allow CLI requests
        if (PHP_SAPI === 'cli') {
            return;
        }

        // Get maintenance info
        $maintenanceInfo = $this->updateExecutor->getMaintenanceInfo();

        // Calculate how long the update has been running
        $duration = null;
        if ($maintenanceInfo && isset($maintenanceInfo['enabled_at'])) {
            try {
                $startedAt = new \DateTime($maintenanceInfo['enabled_at']);
                $now = new \DateTime();
                $duration = $now->getTimestamp() - $startedAt->getTimestamp();
            } catch (\Exception) {
                // Ignore date parsing errors
            }
        }

        $content = $this->getSimpleMaintenanceHtml($maintenanceInfo, $duration);

        $response = new Response($content, Response::HTTP_SERVICE_UNAVAILABLE);
        $response->headers->set('Retry-After', '30');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');

        $event->setResponse($response);
    }

    /**
     * Generate a simple maintenance page HTML without Twig.
     */
    private function getSimpleMaintenanceHtml(?array $maintenanceInfo, ?int $duration): string
    {
        $reason = htmlspecialchars($maintenanceInfo['reason'] ?? 'Update in progress');
        $durationText = $duration !== null ? sprintf('%d seconds', $duration) : 'a moment';

        $startDateStr = $maintenanceInfo['enabled_at'] ?? 'unknown time';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="15">
    <title>Part-DB - Maintenance</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #ffffff;
        }
        .container {
            text-align: center;
            padding: 40px;
            max-width: 600px;
        }
        .icon {
            font-size: 80px;
            margin-bottom: 30px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .spinner {
            display: inline-block;
            animation: spin 2s linear infinite;
        }
        h1 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            color: #00d4ff;
        }
        p {
            font-size: 1.2rem;
            margin-bottom: 15px;
            color: #b8c5d6;
        }
        .reason {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px 25px;
            border-radius: 10px;
            margin: 20px 0;
            font-size: 1rem;
        }
        .progress-bar {
            width: 100%;
            height: 6px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
            overflow: hidden;
            margin: 30px 0;
        }
        .progress-bar-inner {
            height: 100%;
            background: linear-gradient(90deg, #00d4ff, #00ff88);
            border-radius: 3px;
            animation: progress 3s ease-in-out infinite;
        }
        @keyframes progress {
            0% { width: 0%; margin-left: 0%; }
            50% { width: 50%; margin-left: 25%; }
            100% { width: 0%; margin-left: 100%; }
        }
        .info {
            font-size: 0.9rem;
            color: #8899aa;
            margin-top: 30px;
        }
        .duration {
            font-family: monospace;
            background: rgba(0, 212, 255, 0.2);
            padding: 3px 8px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <span class="spinner">⚙️</span>
        </div>
        <h1>Part-DB is under maintenance</h1>
        <p>We're making things better. This should only take a moment.</p>

        <div class="reason">
            <strong>{$reason}</strong>
        </div>

        <div class="progress-bar">
            <div class="progress-bar-inner"></div>
        </div>

        <p class="info">
            Maintenance mode active since <span class="duration">{$startDateStr}</span><br>
            <br>
            Started <span class="duration">{$durationText}</span> ago<br>
            <small>This page will automatically refresh every 15 seconds.</small>
        </p>
    </div>
</body>
</html>
HTML;
    }
}
