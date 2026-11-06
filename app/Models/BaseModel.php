<?php
/**
 * BaseModel — generic tenant-aware CRUD for PDO models.
 * When $tenantScoped is true and a tenant id is supplied, every read/write
 * is constrained by tenant_id so tenants can never touch each other's data.
 */
abstract class BaseModel
{
    protected static string $table;
    protected static bool $tenantScoped = true;

    public static function all(?int $tenantId = null, string $order = 'id DESC', int $limit = 200, int $offset = 0): array
    {
        $where = static::scope($tenantId);
        $sql = 'SELECT * FROM ' . static::$table . $where . " ORDER BY {$order} LIMIT {$limit} OFFSET {$offset}";
        return Database::fetchAll($sql, static::scopeParams($tenantId));
    }

    public static function find(int $id, ?int $tenantId = null): ?array
    {
        $where = static::scope($tenantId, ' AND id = ?');
        $params = static::scopeParams($tenantId);
        $params[] = $id;
        return Database::fetch('SELECT * FROM ' . static::$table . $where . ' LIMIT 1', $params);
    }

    public static function create(array $data): int
    {
        return Database::insert(static::$table, $data);
    }

    public static function updateById(int $id, array $data, ?int $tenantId = null): int
    {
        $where = 'id = :wid' . (static::$tenantScoped && $tenantId !== null ? ' AND tenant_id = :wtid' : '');
        $params = ['wid' => $id];
        if (static::$tenantScoped && $tenantId !== null) {
            $params['wtid'] = $tenantId;
        }
        return Database::update(static::$table, $data, $where, $params);
    }

    public static function deleteById(int $id, ?int $tenantId = null): int
    {
        $where = static::scope($tenantId, ' AND id = ?');
        $params = static::scopeParams($tenantId);
        $params[] = $id;
        return Database::query('DELETE FROM ' . static::$table . $where, $params)->rowCount();
    }

    public static function count(?int $tenantId = null, string $extra = '', array $params = []): int
    {
        $where = static::scope($tenantId, $extra);
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM ' . static::$table . $where,
            array_merge(static::scopeParams($tenantId), $params)
        );
    }

    protected static function scope(?int $tenantId, string $extra = ''): string
    {
        $where = '';
        if (static::$tenantScoped && $tenantId !== null) {
            $where = ' WHERE tenant_id = ?';
        }
        if ($extra !== '') {
            $where .= ($where === '' ? ' WHERE 1=1' : '') . $extra;
        }
        return $where;
    }

    protected static function scopeParams(?int $tenantId): array
    {
        return (static::$tenantScoped && $tenantId !== null) ? [$tenantId] : [];
    }
}
