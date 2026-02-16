<?php
/**
 * MongoDB Helper
 *
 * Uses the PHP MongoDB extension (MongoDB\Driver) when available.
 * Falls back to the Atlas Data API (REST) for hosts without the extension.
 */

class MongoAtlas
{
    private ?string $connString = null;
    private ?string $database = null;

    // Data API fallback
    private ?string $apiUrl = null;
    private ?string $apiKey = null;
    private ?string $cluster = null;

    private ?object $manager = null;

    public function __construct(array $config)
    {
        $this->database = $config['database'];

        // Prefer native PHP driver if available
        if (!empty($config['connection_string']) && extension_loaded('mongodb')) {
            $this->connString = $config['connection_string'];
            $this->manager = new \MongoDB\Driver\Manager($this->connString);
        } elseif (!empty($config['data_api_url'])) {
            // Fallback to Atlas Data API (REST)
            $this->apiUrl = rtrim($config['data_api_url'], '/');
            $this->apiKey = $config['api_key'] ?? '';
            $this->cluster = $config['cluster'] ?? '';
        } else {
            throw new \RuntimeException('No MongoDB connection configured');
        }
    }

    /**
     * Find a single document matching $filter.
     */
    public function findOne(string $collection, array $filter): ?array
    {
        if ($this->manager) {
            return $this->driverFindOne($collection, $filter);
        }
        return $this->apiFindOne($collection, $filter);
    }

    /**
     * Find all documents matching $filter.
     */
    public function find(string $collection, array $filter, array $options = []): array
    {
        if ($this->manager) {
            return $this->driverFind($collection, $filter, $options);
        }
        return $this->apiFind($collection, $filter, $options);
    }

    /**
     * Update a single document.
     */
    public function updateOne(string $collection, array $filter, array $update): bool
    {
        if ($this->manager) {
            return $this->driverUpdateOne($collection, $filter, $update);
        }
        return $this->apiUpdateOne($collection, $filter, $update);
    }

    /**
     * Insert a single document.
     */
    public function insertOne(string $collection, array $document): ?string
    {
        if ($this->manager) {
            return $this->driverInsertOne($collection, $document);
        }
        return $this->apiInsertOne($collection, $document);
    }

    // ────────────────────────────────────────────────────────────
    // Native PHP MongoDB Driver methods
    // ────────────────────────────────────────────────────────────

    private function ns(string $collection): string
    {
        return $this->database . '.' . $collection;
    }

    private function driverFindOne(string $collection, array $filter): ?array
    {
        $query = new \MongoDB\Driver\Query($filter, ['limit' => 1]);
        $cursor = $this->manager->executeQuery($this->ns($collection), $query);
        $docs = $cursor->toArray();
        if (empty($docs)) {
            return null;
        }
        return $this->bsonToArray($docs[0]);
    }

    private function driverFind(string $collection, array $filter, array $options = []): array
    {
        $queryOpts = [];
        if (isset($options['limit'])) {
            $queryOpts['limit'] = $options['limit'];
        }
        if (isset($options['sort'])) {
            $queryOpts['sort'] = $options['sort'];
        }
        $query = new \MongoDB\Driver\Query($filter, $queryOpts);
        $cursor = $this->manager->executeQuery($this->ns($collection), $query);
        $result = [];
        foreach ($cursor as $doc) {
            $result[] = $this->bsonToArray($doc);
        }
        return $result;
    }

    private function driverUpdateOne(string $collection, array $filter, array $update): bool
    {
        $bulk = new \MongoDB\Driver\BulkWrite();
        $bulk->update($filter, $update, ['multi' => false, 'upsert' => false]);
        $result = $this->manager->executeBulkWrite($this->ns($collection), $bulk);
        return $result->getModifiedCount() > 0 || $result->getMatchedCount() > 0;
    }

    private function driverInsertOne(string $collection, array $document): ?string
    {
        $bulk = new \MongoDB\Driver\BulkWrite();
        $id = $bulk->insert($document);
        $this->manager->executeBulkWrite($this->ns($collection), $bulk);
        return (string) $id;
    }

    /**
     * Create an ObjectId appropriate for the active connection mode.
     * Native driver: returns MongoDB\BSON\ObjectId instance.
     * Data API:      returns ['$oid' => $id] array.
     */
    public static function objectId(string $id): mixed
    {
        if (extension_loaded('mongodb')) {
            return new \MongoDB\BSON\ObjectId($id);
        }
        return ['$oid' => $id];
    }

    /**
     * Convert a BSON document (stdClass) to a plain array.
     */
    private function bsonToArray(object $doc): array
    {
        $arr = (array) $doc;
        // Convert ObjectId to string
        if (isset($arr['_id']) && $arr['_id'] instanceof \MongoDB\BSON\ObjectId) {
            $arr['_id'] = (string) $arr['_id'];
        }
        return $arr;
    }

    // ────────────────────────────────────────────────────────────
    // Atlas Data API fallback methods (for hosts without extension)
    // ────────────────────────────────────────────────────────────

    private function apiRequest(string $action, array $body): ?array
    {
        $url = $this->apiUrl . '/action/' . $action;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'apiKey: ' . $this->apiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log('MongoAtlas cURL error: ' . $curlError);
            return null;
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            error_log('MongoAtlas HTTP ' . $httpCode . ': ' . $response);
            return null;
        }
        return json_decode($response, true);
    }

    private function apiBody(string $collection): array
    {
        return [
            'collection' => $collection,
            'dataSource' => $this->cluster,
            'database'   => $this->database,
        ];
    }

    private function apiFindOne(string $collection, array $filter): ?array
    {
        $result = $this->apiRequest('findOne', array_merge(
            $this->apiBody($collection),
            ['filter' => (object) $filter]
        ));
        return $result['document'] ?? null;
    }

    private function apiFind(string $collection, array $filter, array $options = []): array
    {
        $body = array_merge(
            $this->apiBody($collection),
            ['filter' => (object) $filter],
            $options
        );
        $result = $this->apiRequest('find', $body);
        return $result['documents'] ?? [];
    }

    private function apiUpdateOne(string $collection, array $filter, array $update): bool
    {
        $result = $this->apiRequest('updateOne', array_merge(
            $this->apiBody($collection),
            ['filter' => (object) $filter, 'update' => (object) $update]
        ));
        return ($result['modifiedCount'] ?? 0) > 0 || ($result['matchedCount'] ?? 0) > 0;
    }

    private function apiInsertOne(string $collection, array $document): ?string
    {
        $result = $this->apiRequest('insertOne', array_merge(
            $this->apiBody($collection),
            ['document' => (object) $document]
        ));
        return $result['insertedId'] ?? null;
    }
}
