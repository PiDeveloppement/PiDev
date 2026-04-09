<?php

namespace App\Service\Event;

use App\Entity\Event\Event;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Psr\Log\LoggerInterface;

class GoogleCalendarWriteService
{
    private ?string $lastError = null;

    public function __construct(
        #[Autowire('%env(default::GOOGLE_CALENDAR_ID)%')] private readonly ?string $calendarId = null,
        #[Autowire('%env(default::GOOGLE_CLIENT_ID)%')] private readonly ?string $clientId = null,
        #[Autowire('%env(default::GOOGLE_CLIENT_SECRET)%')] private readonly ?string $clientSecret = null,
        #[Autowire('%env(default::GOOGLE_REFRESH_TOKEN)%')] private readonly ?string $refreshToken = null,
        #[Autowire('%env(default::GOOGLE_CALENDAR_TIMEZONE)%')] private readonly ?string $timezone = null,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function syncCreatedEvent(Event $event): bool
    {
        $this->lastError = null;

        if (!$this->isConfigured()) {
            $this->lastError = 'Missing Google configuration.';
            $this->logger->warning('Google Calendar sync skipped: missing configuration.');
            return false;
        }

        if (!$event->getStartDate() || !$event->getEndDate()) {
            $this->lastError = 'Event has no valid start/end dates.';
            $this->logger->warning('Google Calendar sync skipped: event has no valid dates.', [
                'event_id' => $event->getId(),
                'title' => $event->getTitle(),
            ]);
            return false;
        }

        try {
            $accessToken = $this->refreshAccessToken();
            if (!$accessToken) {
                $this->lastError = 'Unable to refresh Google access token.';
                $this->logger->error('Google Calendar sync failed: unable to refresh access token.');
                return false;
            }

            $response = $this->createGoogleEvent($event, $accessToken);

            if ($this->isSuccessStatus($response['status'] ?? 500)) {
                $this->logger->info('Google Calendar sync success.', [
                    'event_id' => $event->getId(),
                    'title' => $event->getTitle(),
                    'google_status' => $response['status'] ?? null,
                ]);

                return true;
            }

            $errorPayload = $response['data'] ?? [];
            $errorMessage = is_array($errorPayload)
                ? (string) ($errorPayload['error']['message'] ?? $errorPayload['error_description'] ?? 'Unknown Google error')
                : 'Unknown Google error';

            $this->lastError = sprintf('Google API returned HTTP %s: %s', (string) ($response['status'] ?? 500), $errorMessage);

            $this->logger->error('Google Calendar sync failed on event creation.', [
                'event_id' => $event->getId(),
                'title' => $event->getTitle(),
                'google_status' => $response['status'] ?? null,
                'google_response' => $response['data'] ?? [],
            ]);

            return false;
        } catch (\Throwable) {
            $this->lastError = 'Exception while contacting Google Calendar API.';
            $this->logger->error('Google Calendar sync threw an exception.', [
                'event_id' => $event->getId(),
                'title' => $event->getTitle(),
            ]);
            return false;
        }
    }

    public function syncUpdatedEvent(Event $event): bool
    {
        $this->lastError = null;

        if (!$this->isConfigured()) {
            $this->lastError = 'Missing Google configuration.';
            return false;
        }

        if (!$event->getId() || !$event->getStartDate() || !$event->getEndDate()) {
            $this->lastError = 'Event has no valid identifier or dates.';
            return false;
        }

        $accessToken = $this->refreshAccessToken();
        if (!$accessToken) {
            $this->lastError = 'Unable to refresh Google access token.';
            return false;
        }

        $googleEventId = $this->resolveGoogleEventId($event, $accessToken);
        if (!$googleEventId) {
            // Event not found in Google yet, fallback to creation.
            return $this->syncCreatedEvent($event);
        }

        $url = sprintf(
            'https://www.googleapis.com/calendar/v3/calendars/%s/events/%s',
            rawurlencode((string) $this->calendarId),
            rawurlencode($googleEventId)
        );

        $response = $this->requestJson(
            $url,
            'PUT',
            [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            json_encode($this->buildGooglePayload($event))
        );

        if ($this->isSuccessStatus($response['status'] ?? 500)) {
            return true;
        }

        $errorPayload = $response['data'] ?? [];
        $errorMessage = is_array($errorPayload)
            ? (string) ($errorPayload['error']['message'] ?? $errorPayload['error_description'] ?? 'Unknown Google error')
            : 'Unknown Google error';

        $this->lastError = sprintf('Google API returned HTTP %s: %s', (string) ($response['status'] ?? 500), $errorMessage);

        return false;
    }

    public function syncDeletedEvent(Event $event): bool
    {
        $this->lastError = null;

        if (!$this->isConfigured() || !$event->getId()) {
            return true;
        }

        $accessToken = $this->refreshAccessToken();
        if (!$accessToken) {
            $this->lastError = 'Unable to refresh Google access token.';
            return false;
        }

        $googleEventId = $this->resolveGoogleEventId($event, $accessToken);
        if (!$googleEventId) {
            // Already absent from Google.
            return true;
        }

        $url = sprintf(
            'https://www.googleapis.com/calendar/v3/calendars/%s/events/%s',
            rawurlencode((string) $this->calendarId),
            rawurlencode($googleEventId)
        );

        $response = $this->requestJson(
            $url,
            'DELETE',
            [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json',
            ]
        );

        if (($response['status'] ?? 500) === 404 || $this->isSuccessStatus($response['status'] ?? 500)) {
            return true;
        }

        $this->lastError = 'Google delete failed with HTTP ' . (string) ($response['status'] ?? 500);

        return false;
    }

    /**
     * @return array{ok: bool, found: bool, event: ?array<string, mixed>}
     */
    public function fetchRemoteEventByLocalId(int $localEventId): array
    {
        $this->lastError = null;

        if (!$this->isConfigured()) {
            $this->lastError = 'Missing Google configuration.';
            return ['ok' => false, 'found' => false, 'event' => null];
        }

        $accessToken = $this->refreshAccessToken();
        if (!$accessToken) {
            $this->lastError = 'Unable to refresh Google access token.';
            return ['ok' => false, 'found' => false, 'event' => null];
        }

        $url = sprintf(
            'https://www.googleapis.com/calendar/v3/calendars/%s/events?privateExtendedProperty=%s&maxResults=1&singleEvents=true',
            rawurlencode((string) $this->calendarId),
            rawurlencode('eventflow_event_id=' . $localEventId)
        );

        $response = $this->requestJson(
            $url,
            'GET',
            [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json',
            ]
        );

        if (!$this->isSuccessStatus($response['status'] ?? 500)) {
            $errorPayload = $response['data'] ?? [];
            $errorMessage = is_array($errorPayload)
                ? (string) ($errorPayload['error']['message'] ?? $errorPayload['error_description'] ?? 'Unknown Google error')
                : 'Unknown Google error';

            $this->lastError = sprintf('Google API returned HTTP %s: %s', (string) ($response['status'] ?? 500), $errorMessage);

            return ['ok' => false, 'found' => false, 'event' => null];
        }

        $items = $response['data']['items'] ?? [];
        if (!is_array($items) || !isset($items[0]) || !is_array($items[0])) {
            return ['ok' => true, 'found' => false, 'event' => null];
        }

        $item = $items[0];
        if ((string) ($item['status'] ?? '') === 'cancelled') {
            return ['ok' => true, 'found' => false, 'event' => null];
        }

        $startRaw = (string) ($item['start']['dateTime'] ?? $item['start']['date'] ?? '');
        $endRaw = (string) ($item['end']['dateTime'] ?? $item['end']['date'] ?? '');

        if ($startRaw === '' || $endRaw === '') {
            $this->lastError = 'Google event payload is missing start or end date.';
            return ['ok' => false, 'found' => false, 'event' => null];
        }

        return [
            'ok' => true,
            'found' => true,
            'event' => [
                'title' => (string) ($item['summary'] ?? ''),
                'description' => (string) ($item['description'] ?? ''),
                'location' => (string) ($item['location'] ?? ''),
                'start' => $startRaw,
                'end' => $endRaw,
            ],
        ];
    }

    private function isConfigured(): bool
    {
        return (bool) ($this->calendarId && $this->clientId && $this->clientSecret && $this->refreshToken);
    }

    private function refreshAccessToken(): ?string
    {
        $payload = http_build_query([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $this->refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        $response = $this->requestJson(
            'https://oauth2.googleapis.com/token',
            'POST',
            [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
            $payload
        );

        if (($response['status'] ?? 500) < 200 || ($response['status'] ?? 500) >= 300) {
            return null;
        }

        $data = $response['data'] ?? [];
        return is_array($data) ? (string) ($data['access_token'] ?? '') : null;
    }

    private function requestJson(string $url, string $method, array $headers, ?string $body = null): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $body ?? '',
                'timeout' => 8,
                'ignore_errors' => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);

        $status = 500;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string) $http_response_header[0], $matches)) {
            $status = (int) $matches[1];
        }

        $decoded = json_decode((string) $raw, true);

        return [
            'status' => $status,
            'data' => is_array($decoded) ? $decoded : [],
        ];
    }

    private function createGoogleEvent(Event $event, string $accessToken): array
    {
        $url = sprintf(
            'https://www.googleapis.com/calendar/v3/calendars/%s/events',
            rawurlencode((string) $this->calendarId)
        );

        return $this->requestJson(
            $url,
            'POST',
            [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            json_encode($this->buildGooglePayload($event))
        );
    }

    private function buildGooglePayload(Event $event): array
    {
        return [
            'summary' => (string) ($event->getTitle() ?? 'EventFlow Event'),
            'description' => (string) ($event->getDescription() ?? ''),
            'location' => (string) ($event->getLocation() ?? ''),
            'start' => [
                'dateTime' => $event->getStartDate()->format(DATE_ATOM),
                'timeZone' => $this->timezone ?: 'Africa/Tunis',
            ],
            'end' => [
                'dateTime' => $event->getEndDate()->format(DATE_ATOM),
                'timeZone' => $this->timezone ?: 'Africa/Tunis',
            ],
            'source' => [
                'title' => 'EventFlow',
                'url' => 'https://eventflow.local',
            ],
            'extendedProperties' => [
                'private' => [
                    'eventflow_event_id' => (string) ($event->getId() ?? ''),
                ],
            ],
        ];
    }

    private function resolveGoogleEventId(Event $event, string $accessToken): ?string
    {
        if (!$event->getId()) {
            return null;
        }

        $url = sprintf(
            'https://www.googleapis.com/calendar/v3/calendars/%s/events?privateExtendedProperty=%s&maxResults=1&singleEvents=true',
            rawurlencode((string) $this->calendarId),
            rawurlencode('eventflow_event_id=' . $event->getId())
        );

        $response = $this->requestJson(
            $url,
            'GET',
            [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json',
            ]
        );

        if (!$this->isSuccessStatus($response['status'] ?? 500)) {
            return null;
        }

        $items = $response['data']['items'] ?? [];
        if (!is_array($items) || !isset($items[0]['id'])) {
            return $this->resolveGoogleEventIdByTitleAndStart($event, $accessToken);
        }

        return (string) $items[0]['id'];
    }

    private function resolveGoogleEventIdByTitleAndStart(Event $event, string $accessToken): ?string
    {
        if (!$event->getStartDate()) {
            return null;
        }

        $title = trim((string) $event->getTitle());
        if ($title === '') {
            return null;
        }

        $startDate = \DateTimeImmutable::createFromInterface($event->getStartDate());
        $timeMin = $startDate->modify('-1 day')->format(DATE_ATOM);
        $timeMax = $startDate->modify('+1 day')->format(DATE_ATOM);

        $url = sprintf(
            'https://www.googleapis.com/calendar/v3/calendars/%s/events?singleEvents=true&orderBy=startTime&timeMin=%s&timeMax=%s&q=%s&maxResults=20',
            rawurlencode((string) $this->calendarId),
            rawurlencode($timeMin),
            rawurlencode($timeMax),
            rawurlencode($title)
        );

        $response = $this->requestJson(
            $url,
            'GET',
            [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json',
            ]
        );

        if (!$this->isSuccessStatus($response['status'] ?? 500)) {
            return null;
        }

        $items = $response['data']['items'] ?? [];
        if (!is_array($items)) {
            return null;
        }

        $targetStart = $event->getStartDate()->getTimestamp();

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $summary = (string) ($item['summary'] ?? '');
            $itemStart = (string) ($item['start']['dateTime'] ?? $item['start']['date'] ?? '');

            if ($summary === '' || $itemStart === '' || !isset($item['id'])) {
                continue;
            }

            $itemStartTs = strtotime($itemStart);
            if ($summary === $title && $itemStartTs !== false && abs($itemStartTs - $targetStart) <= 60) {
                return (string) $item['id'];
            }
        }

        return null;
    }

    private function isSuccessStatus(int $status): bool
    {
        return $status >= 200 && $status < 300;
    }
}
