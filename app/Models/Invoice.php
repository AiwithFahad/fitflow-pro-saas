<?php
/** Invoices generated for subscriptions and memberships. */
class Invoice extends BaseModel
{
    protected static string $table = 'invoices';

    public static function generate(?int $tenantId, ?int $paymentId, string $description, float $amount, string $status = 'unpaid'): array
    {
        $number = 'INV-' . date('Y') . '-' . str_pad((string) random_int(10000, 99999), 5, '0', STR_PAD_LEFT)
            . '-' . strtoupper(bin2hex(random_bytes(2)));
        $id = Database::insert('invoices', [
            'tenant_id'   => $tenantId,
            'payment_id'  => $paymentId,
            'number'      => $number,
            'description' => $description,
            'amount'      => $amount,
            'status'      => $status,
            'due_at'      => date('Y-m-d H:i:s', strtotime('+7 days')),
            'issued_at'   => date('Y-m-d H:i:s'),
        ]);
        return ['id' => $id, 'number' => $number];
    }
}
