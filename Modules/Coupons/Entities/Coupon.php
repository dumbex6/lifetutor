<?php

namespace Modules\Coupons\Entities;

use App\User;
use App\Traits\Tenantable;
use Modules\Payment\Entities\Checkout;
use Illuminate\Database\Eloquent\Model;
use Modules\Subscription\Entities\SubscriptionCheckout;

class Coupon extends Model
{
    use Tenantable;

    protected $guarded = ['id', 'created_at'];
    protected $dates = [
        'end_date',
        'start_date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withDefault();
    }

    public function coupon_user()
    {
        return $this->belongsTo(User::class, 'coupon_user_id')->withDefault();
    }

    public function totalUsed()
    {
        return $this->hasMany(Checkout::class, 'coupon_id');
    }

    public function loginUserTotalUsed()
    {
        return $this->hasMany(Checkout::class, 'coupon_id')->where('user_id', auth()->id())->count();
    }

    public function loginUserTotalSubscriptionUsed()
    {
        return $this->hasMany(SubscriptionCheckout::class, 'coupon_id')->where('user_id', auth()->id())->count();
    }
}
