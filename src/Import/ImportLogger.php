<?php

namespace KIC\Importer\Import;

final class ImportLogger
{
    private int $id;
    /** @var array<int,array<string,mixed>> */
    private array $events = array();

    public function __construct()
    {
        $this->id = (int) get_option('kic_next_import_id', 1);
        update_option('kic_next_import_id', $this->id + 1, false);
    }

    public function id(): int { return $this->id; }

    /** @param array<string,mixed> $context */
    public function add(string $level, string $message, array $context = array()): void
    {
        $this->events[] = array('time' => gmdate('c'), 'level' => $level, 'message' => $message, 'context' => $context);
    }

    /** @param array<int,int> $pageIds @param array<int,int> $mediaIds */
    public function save(string $status, array $pageIds, array $mediaIds): int
    {
        $record = array('id' => $this->id, 'status' => $status, 'created_at' => gmdate('c'), 'pages' => $pageIds, 'media' => $mediaIds, 'events' => $this->events);
        update_option('kic_import_' . $this->id, $record, false);
        $index = array_values(array_unique(array_merge(array($this->id), (array) get_option('kic_import_index', array()))));
        update_option('kic_import_index', array_slice($index, 0, 50), false);
        return $this->id;
    }
}
