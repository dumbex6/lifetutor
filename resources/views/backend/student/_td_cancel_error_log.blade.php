@if ($query->refund != 1 && permissionCheck('course.delete'))
    @php
        $refundUrl = route('admin.enrollDelete', $query->id) . '?refund&cancel';
    @endphp

    <div class="dropdown CRM_dropdown">
        <button class="btn btn-secondary dropdown-toggle" type="button"
                id="dropdownMenu2" data-toggle="dropdown"
                aria-haspopup="true"
                aria-expanded="false">
            {{trans('common.Action') }}
        </button>
        <div class="dropdown-menu dropdown-menu-right"
             aria-labelledby="dropdownMenu2">

            <a onclick="confirm_refund_modal('{{$refundUrl}}')"
               class="dropdown-item edit_brand">
                {{trans('common.Refund') .' '. trans('common.Course')}}
            </a>

        </div>
    </div>

@endif
