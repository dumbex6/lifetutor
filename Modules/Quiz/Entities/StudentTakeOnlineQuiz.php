<?php

namespace Modules\Quiz\Entities;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Model;
use Rennokki\QueryCache\Traits\QueryCacheable;

class StudentTakeOnlineQuiz extends Model
{
use Tenantable;

    protected $fillable = [];
}
