<?php
/**
 * MedicalRecord — member medical clearance records.
 * RESTRICTED data: callers MUST check the 'members.medical' permission
 * before reading or writing through this model.
 */
class MedicalRecord extends BaseModel
{
    protected static string $table = 'member_medical_records';

    /** Latest record for a member (tenant scoped), or null. */
    public static function latestForMember(int $tenantId, int $memberId): ?array
    {
        return Database::fetch(
            'SELECT * FROM member_medical_records WHERE tenant_id = ? AND member_id = ?
             ORDER BY id DESC LIMIT 1',
            [$tenantId, $memberId]
        );
    }

    /** Full history for a member, newest first. */
    public static function historyForMember(int $tenantId, int $memberId): array
    {
        return Database::fetchAll(
            'SELECT r.*, u.name AS recorded_by_name
             FROM member_medical_records r
             LEFT JOIN users u ON u.id = r.recorded_by
             WHERE r.tenant_id = ? AND r.member_id = ? ORDER BY r.id DESC',
            [$tenantId, $memberId]
        );
    }

    /** Members whose latest clearance is not a plain "cleared" (attention list). */
    public static function attentionList(int $tenantId): array
    {
        return Database::fetchAll(
            'SELECT m.id AS member_id, m.name AS member_name, r.clearance_status, r.expiry_date
             FROM members m
             JOIN member_medical_records r ON r.member_id = m.id AND r.tenant_id = m.tenant_id
             WHERE m.tenant_id = ? AND r.clearance_status <> "cleared"
               AND r.id = (SELECT MAX(r2.id) FROM member_medical_records r2 WHERE r2.member_id = m.id)
             ORDER BY m.name',
            [$tenantId]
        );
    }
}
