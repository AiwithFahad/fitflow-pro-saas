<?php
/** Member bookings for group classes (capacity-checked). */
class ClassBooking extends BaseModel
{
    protected static string $table = 'class_bookings';

    public static function book(int $tenantId, int $classId, int $memberId): array
    {
        $class = GymClass::find($classId, $tenantId);
        if ($class === null) {
            return ['ok' => false, 'message' => 'Class not found.'];
        }
        $booked = (int) Database::scalar('SELECT COUNT(*) FROM class_bookings WHERE class_id = ? AND status = "booked"', [$classId]);
        if ($booked >= (int) $class['capacity']) {
            return ['ok' => false, 'message' => 'This class is fully booked.'];
        }
        $exists = Database::fetch('SELECT id FROM class_bookings WHERE class_id = ? AND member_id = ? AND status = "booked"', [$classId, $memberId]);
        if ($exists !== null) {
            return ['ok' => false, 'message' => 'Member is already booked into this class.'];
        }
        $id = Database::insert('class_bookings', [
            'tenant_id' => $tenantId,
            'class_id'  => $classId,
            'member_id' => $memberId,
            'status'    => 'booked',
            'booked_at' => date('Y-m-d H:i:s'),
        ]);
        return ['ok' => true, 'id' => $id, 'message' => 'Booked successfully.'];
    }

    public static function forMember(int $tenantId, int $memberId): array
    {
        return Database::fetchAll(
            'SELECT cb.*, c.name AS class_name, c.day_of_week, c.start_time, c.end_time
             FROM class_bookings cb JOIN classes c ON c.id = cb.class_id
             WHERE cb.tenant_id = ? AND cb.member_id = ? ORDER BY cb.id DESC',
            [$tenantId, $memberId]
        );
    }
}
