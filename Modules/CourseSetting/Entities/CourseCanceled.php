<?php

namespace Modules\CourseSetting\Entities;

use App\User;
use Illuminate\Database\Eloquent\Model;

class CourseCanceled extends Model
{
    protected $fillable = [];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id', 'id')->withDefault();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id')->withDefault();
    }
}
