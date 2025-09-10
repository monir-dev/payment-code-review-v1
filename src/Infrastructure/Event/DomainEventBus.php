<?php

declare(strict_types=1);

namespace App\Infrastructure\Event;

use Exception;
use Psr\Log\LoggerInterface;

final class DomainEventBus
{
    /**
     * @var array<string, array<callable>>
     */
    private array $listeners = [];

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function subscribe(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    public function publish(object $event): void
    {
        $eventClass = get_class($event);

        if (!isset($this->listeners[$eventClass])) {
            return;
        }

        foreach ($this->listeners[$eventClass] as $listener) {
            try {
                $listener($event);
            } catch (Exception $e) {
                $this->logger->error('Domain event listener failed', [
                    'event_class' => $eventClass,
                    'listener' => get_debug_type($listener),
                    'error' => $e->getMessage()
                ]);

                // Don't let listener failures break the main flow
                continue;
            }
        }
    }

    /**
     * @param object[] $events
     */
    public function publishAll(array $events): void
    {
        foreach ($events as $event) {
            $this->publish($event);
        }
    }
}
