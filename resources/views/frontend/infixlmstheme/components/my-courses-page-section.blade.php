

<div>
    <div class="main_content_iner main_content_padding">
        <div class="container">
            <div class="my_courses_wrapper">
                <div class="row">
                    <div class="col-12">
                        <div class="section__title3 margin-50">
                            <h3>
                                @if (routeIs('myClasses'))
                                    {{ __('courses.Live Class') }}
                                @elseif(routeIs('myQuizzes'))
                                    {{ __('courses.My Quizzes') }}
                                @else
                                    {{ __('courses.My Courses') }}
                                @endif
                            </h3>
                        </div>
                    </div>

                    @php
                        if (routeIs('myClasses')) {
                            $search_text = trans('frontend.Search My Classes');
                            $search_route = '';
                        } elseif (routeIs('myQuizzes')) {
                            $search_text = trans('frontend.Search My Quizzes');
                            $search_route = '';
                        } else {
                            $search_text = trans('frontend.Search My Courses');
                            $search_route = '';
                        }
                    @endphp
                    <div class="col-xl-6 col-md-6">
                        <div class="short_select d-flex align-items-center pt-0 pb-3">
                            <h5 class="mr_10 font_16 f_w_500 mb-0">{{ __('frontend.Filter By') }}:</h5>
                            <input type="hidden" id="siteUrl" value="{{ route(\Request::route()->getName()) }}">
                            <select class="theme_select my-course-select w-50" id="categoryFilter">
                                <option value="" data-display="{{ __('frontend.All Categories') }}">
                                    {{ __('frontend.All Categories') }}</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}"
                                        {{ @$category_id == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class=" col-xl-6 col-md-6">
                        <form action="{{ route(\Request::route()->getName()) }}">
                            <div class="input-group theme_search_field pt-0 pb-3 float-right w-50">
                                <div class="input-group-prepend">
                                    <button class="btn" type="button" id="button-addon1"><i class="ti-search"></i>
                                    </button>
                                </div>

                                <input type="text" class="form-control" name="search"
                                    placeholder="{{ $search_text }}" value="{{ $search }}"
                                    onfocus="this.placeholder = ''" onblur="this.placeholder = '{{ $search_text }}'">

                            </div>
                        </form>
                    </div>
                    @if (isset($courses))
                        @foreach ($courses as $SingleCourse)
                            @php
                                $course = $SingleCourse->course;
                            @endphp
                            <div class="col-xl-4 col-md-6">
                                @if ($course->type == 1)
                                    <div class="couse_wizged">
                                        <div class="thumb">
                                            <div class="thumb_inner lazy"
                                                data-src="{{ getCourseImage($course->thumbnail) }}">

                                                <x-price-tag :price="$course->price" :discount="$course->discount_price" />

                                            </div>

                                        </div>
                                        <div class="course_content">
                                            <a href="{{ route('continueCourse', [$course->slug]) }}">
                                                <h4 class="noBrake" title="{{ $course->title }}">
                                                    {{ $course->title }}
                                                </h4>
                                            </a>
                                            @if ($SingleCourse->pathway_id != null)
                                                <x-my-course-pathway-info :enrolld="$SingleCourse" />
                                            @endif
                                            <div class="d-flex align-items-center gap_15">
                                                <div class="rating_cart">
                                                    <div class="rateing">
                                                        <span>{{ $course->totalReview }}/5</span>
                                                        <i class="fas fa-star"></i>
                                                    </div>
                                                </div>

                                                <div class="progress_percent flex-fill text-right">
                                                    <div class="progress theme_progressBar ">
                                                        <div class="progress-bar" role="progressbar"
                                                            style="width: {{ round($course->loginUserTotalPercentage) }}%"
                                                            aria-valuenow="25" aria-valuemin="0" aria-valuemax="100">
                                                        </div>
                                                    </div>
                                                    <p class="font_14 f_w_400">
                                                        {{ round($course->loginUserTotalPercentage) }}
                                                        % {{ __('student.Complete') }}</p>
                                                </div>
                                            </div>
                                            <div class="course_less_students">
                                                <a>
                                                    <i class="ti-agenda"></i> {{ count($course->lessons) }}
                                                    {{ __('student.Lessons') }}
                                                </a>
                                                <a>
                                                    <i class="ti-user"></i> {{ $course->total_enrolled }}
                                                    {{ __('student.Students') }}
                                                </a>
                                                @if (isModuleActive('CPD'))
                                                    @if(count($cpds)>0)
                                                        <a class="cpd cpdValue" data-course_id={{ $course->id }} data-toggle="modal" data-target="#exampleModal">
                                                            <i class="ti-bolt"></i> 
                                                            {{ __('cpd.CPD') }}
                                                        </a>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @elseif($course->type == 2)
                                    <div class="quiz_wizged">
                                        <a href="{{ courseDetailsUrl($course->id, $course->type, $course->slug) }}">
                                            <div class="thumb">
                                                <div class="thumb_inner lazy"
                                                    data-src="{{ getCourseImage($course->thumbnail) }}">

                                                    <x-price-tag :price="$course->price" :discount="$course->discount_price" />


                                                </div>
                                                <span class="quiz_tag">{{ __('quiz.Quiz') }}</span>
                                            </div>
                                        </a>
                                        <div class="course_content">
                                            <a href="{{ courseDetailsUrl($course->id, $course->type, $course->slug) }}">
                                                <h4 class="noBrake" title="{{ $course->title }}"> {{ $course->title }}
                                                </h4>
                                            </a>
                                            <div class="rating_cart">
                                                <div class="rateing">
                                                    <span>{{ $course->totalReview }}/5</span>
                                                    <i class="fas fa-star"></i>
                                                </div>
                                            </div>
                                            <div class="course_less_students">

                                                <a> <i class="ti-agenda"></i>{{ count($course->quiz->assign) }}
                                                    {{ __('student.Question') }}</a>
                                                <a>
                                                    <i class="ti-user"></i> {{ $course->total_enrolled }}
                                                    {{ __('student.Students') }}
                                                </a>
                                                @if (isModuleActive('CPD'))
                                                @if(count($cpds)>0)
                                                    {{-- <a onclick="cpd({{ $course->id }})" class="cpd">
                                                        <i class="ti-bolt"></i> {{ __('cpd.CPD') }}
                                                    </a> --}}
                                                    <a class="cpd cpdvalue" data-toggle="modal" data-course_id={{ $course->id }} data-target="#exampleModal">
                                                        <i class="ti-bolt"></i> 
                                                        {{ __('cpd.CPD') }}
                                                    </a>
                                                @endif
                                            @endif
                                            </div>
                                        </div>
                                    </div>
                                @elseif($course->type == 3)
                                    <div class="quiz_wizged">
                                        <div class="thumb">
                                            <a href="{{ courseDetailsUrl($course->id, $course->type, $course->slug) }}">
                                                <div class="thumb">
                                                    <div class="thumb_inner lazy"
                                                        data-src="{{ getCourseImage($course->thumbnail) }}">
                                                        <x-class-close-tag :class="$course->class" />

                                                        <x-price-tag :price="$course->price" :discount="$course->discount_price" />


                                                    </div>
                                                    <span class="live_tag">{{ __('student.Live') }}</span>
                                                </div>
                                            </a>


                                        </div>
                                        <div class="course_content">
                                            <a href="{{ courseDetailsUrl($course->id, $course->type, $course->slug) }}">
                                                <h4 class="noBrake" title="{{ $course->title }}"> {{ $course->title }}
                                                </h4>
                                            </a>
                                            <div class="rating_cart">
                                                <div class="rateing">
                                                    <span>{{ $course->totalReview }}/5</span>
                                                    <i class="fas fa-star"></i>
                                                </div>
                                            </div>
                                            <div class="course_less_students">
                                                <a> <i class="ti-agenda"></i> {{ $course->class->total_class }}
                                                    {{ __('student.Classes') }}</a>
                                                <a>
                                                    <i class="ti-user"></i> {{ $course->total_enrolled }}
                                                    {{ __('student.Students') }}
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        <div class="pt-3">
                            {{ $courses->links() }}
                        </div>
                    @endif
                    @if (count($courses) == 0)
                        <div class="col-12">
                            <div class="section__title3 margin_50">
                                @if (routeIs('myClasses'))
                                    <p class="text-center">{{ __('student.No Class Purchased Yet') }}!</p>
                                @elseif(routeIs('myQuizzes'))
                                    <p class="text-center">{{ __('student.No Quiz Purchased Yet') }}!</p>
                                @else
                                    <p class="text-center">{{ __('student.No Course Purchased Yet') }}!</p>
                                @endif

                            </div>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</div>
@if(isModuleActive('CPD'))
{{-- <div class="modal cs_modal fade admin-query" id="makeItCPd" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('cpd.CPD') }}</h5>
                <button type="button" class="close" data-bs-dismiss="modal"><i class="ti-close "></i></button>
            </div>

            <form action="{{ route('cpd.course_to_cpd') }}" method="Post">
                <div class="modal-body">
                    @csrf
                  
                    <div class="cpdClass">
                        <div class="">
                            <label class="" for="">{{ __('cpd.Select CPD') }}
                            </label>
                            <select class="active" name="cpd_id">
                                <option data-display="{{ __('cpd.Select CPD') }}" value="">
                                    {{ __('cpd.Select CPD') }} </option>
                                @foreach ($cpds as $cpd)
                                    <option value="{{ $cpd->id }}">{{ $cpd->title }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <div class="mt-40 d-flex justify-content-between">
                        <button type="button" class="theme_line_btn mr-2"
                            data-bs-dismiss="modal">{{ __('common.Cancel') }}
                        </button>
                        <button class="theme_btn " type="submit">{{ __('common.Submit') }}</button>
                    </div>
                </div>
            </form>

        </div>
    </div>
</div> --}}
<div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="exampleModalLabel">{{ __('cpd.CPD') }}</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true"><i class="ti-close"></i></span>
          </button>
        </div>

        {!! Form::open(['route'=>'cpd.course_to_cpd', 'method'=>'POST']) !!}
        <input type="hidden" name="course_id" id="cpd_course_id" value="">
                 
        <div class="modal-body">
          <div class="input-control">
              <label for="#">{{ __('cpd.CPD') }}</label>
              <select name="" id="" class="theme_select">
                <option value="">{{ __('cpd.Select CPD') }}</option>
                @foreach ($cpds as $cpd)
                    <option value="{{ $cpd->id }}">{{ $cpd->title }}</option>
                @endforeach
              </select>
          </div>
        </div>
        <div class="modal-footer mntop">
          <button type="button" class="theme_btn small_btn bg-transparent" data-dismiss="modal">{{ __('common.Cancel') }}</button>
          <button type="button" class="theme_btn small_btn ">{{ __('common.Submit') }}</button>
        </div>
        {!! Form::close() !!}
      </div>
    </div>
  </div>
@endif


