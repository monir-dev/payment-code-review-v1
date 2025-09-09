<?php

declare(strict_types=1);

namespace App\Infrastructure\Event;

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
        
        $this->logger->info('Publishing domain event', [
            'event_class' => $eventClass,
            'event_data' => $this->extractEventData($event)
        ]);
        
        if (!isset($this->listeners[$eventClass])) {
            return;
        }
        
        foreach ($this->listeners[$eventClass] as $listener) {
            try {
                $listener($event);
            } catch (\Exception $e) {
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

    private function extractEventData(object $event): array
    {
        // Simple reflection-based data extraction for logging
        try {
            $reflection = new \ReflectionClass($event);
            $data = [];
            
            foreach ($reflection->getProperties() as $property) {
                $property->setAccessible(true);
                $value = $property->getValue($event);
                
                // Convert complex objects to strings for logging
                if (is_object($value)) {
                    if (method_exists($value, '__toString')) {
                        $data[$property->getName()] = (string) $value;
                    } elseif (method_exists($value, 'getValue')) {
                        $data[$property->getName()] = $value->getValue();
                    } else {
                        $data[$property->getName()] = get_class($value);
                    }
                } else {
                    $data[$property->getName()] = $value;
                }
            }
            
            return $data;
        } catch (\Exception $e) {
            return ['error' => 'Failed to extract event data: ' . $e->getMessage()];
        }
    }
}
