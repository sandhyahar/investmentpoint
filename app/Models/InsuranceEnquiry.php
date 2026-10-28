<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Sent from an Insurance page (Services -> Insurance): kind "product" = interested in the ticked plan categories
 * (`products`, their names), kind "adviser" = wants to join as an advisor. Both give name, mobile, age and the address
 * with city / state / PIN code. Listed in Admin -> Services -> Insurance Enquiries.
 */
class InsuranceEnquiry extends Model
{
    public const KINDS = ['product' => 'Products', 'adviser' => 'Join As Advisor'];

    protected $fillable = ['insurance', 'kind', 'name', 'contact', 'age', 'address', 'city', 'state', 'pincode', 'products', 'read_at'];

    protected $casts = ['products' => 'array', 'read_at' => 'datetime', 'age' => 'integer'];

    /** Each enquiry is also emailed here (Admin -> Services -> Insurance Products); left empty, none are emailed */
    public const NOTIFY_EMAIL = 'insurance@example.com';

    public static function notifyEmail(): ?string
    {
        return trim((string) Setting::get('insurance_notify_email', self::NOTIFY_EMAIL)) ?: null;
    }
}
