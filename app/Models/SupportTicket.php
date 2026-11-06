<?php
/** Platform support tickets raised by tenants. */
class SupportTicket extends BaseModel
{
    protected static string $table = 'support_tickets';

    public static function openCount(): int
    {
        return (int) Database::scalar('SELECT COUNT(*) FROM support_tickets WHERE status IN ("open","in_progress")');
    }
}
