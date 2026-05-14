<?php

namespace App\Services;

use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;

class GoogleCalendarService
{
    private Calendar $calendar;
    private string   $calendarId;

    public function __construct()
    {
        $keyPath = config('services.google.service_account_path');

        $client = new Client();
        $client->setAuthConfig($keyPath);
        $client->addScope(Calendar::CALENDAR_EVENTS);

        $this->calendar   = new Calendar($client);
        $this->calendarId = config('services.google.calendar_id', 'primary');
    }

    /**
     * Crea un evento en Google Calendar y devuelve el link del evento o null si falla.
     */
    public function createEvent(
        string  $title,
        string  $description,
        string  $startDatetime,  // ISO8601: '2026-08-15T20:00:00-03:00'
        string  $endDatetime,
        ?string $attendeeEmail = null,
    ): ?string {
        $event = new Event([
            'summary'     => $title,
            'description' => $description,
        ]);

        $start = new EventDateTime();
        $start->setDateTime($startDatetime);
        $start->setTimeZone(config('app.timezone', 'America/Argentina/Buenos_Aires'));
        $event->setStart($start);

        $end = new EventDateTime();
        $end->setDateTime($endDatetime);
        $end->setTimeZone(config('app.timezone', 'America/Argentina/Buenos_Aires'));
        $event->setEnd($end);

        // Service accounts with regular Gmail can't add attendees (requires Google Workspace + Domain-Wide Delegation)
        // Client email is included in the event description instead

        $created = $this->calendar->events->insert($this->calendarId, $event);

        return $created->getHtmlLink() ?? null;
    }
}
