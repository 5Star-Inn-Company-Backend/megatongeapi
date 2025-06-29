<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class stripepayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'email',
        'payment_id',
        'reference_code',
        'payment_method',
        'subscriptionplan',
        'currency',
        'dateofpayment',
        'payment_type'
    ] ;
}
