<?php

namespace Modules\CourseSetting\Http\Controllers;

use DB;
use App\User;
use Exception;
use App\LessonComplete;
use App\Traits\Filepond;
use App\Traits\ImageStore;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Modules\Payment\Entities\Cart;
use Illuminate\Support\Facades\App;

use Modules\Org\Entities\OrgBranch;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Intervention\Image\Facades\Image;
use Modules\Org\Entities\OrgMaterial;
use Modules\Quiz\Entities\OnlineQuiz;
use Illuminate\Support\Facades\Session;
use Modules\Quiz\Entities\QuestionBank;
use Modules\Quiz\Entities\QuestionGroup;
use Modules\Quiz\Entities\QuestionLevel;
use Modules\Setting\Model\GeneralSetting;
use Modules\CourseSetting\Entities\Course;
use Modules\CourseSetting\Entities\Lesson;
use Modules\CourseSetting\Entities\Chapter;
use Modules\Localization\Entities\Language;
use Modules\CourseSetting\Entities\Category;
use Modules\Certificate\Entities\Certificate;
use Modules\CourseSetting\Entities\CourseLevel;
use Modules\CourseSetting\Entities\CourseEnrolled;
use Modules\CourseSetting\Entities\CourseExercise;
use Modules\Quiz\Entities\OnlineExamQuestionAssign;
use Modules\BundleSubscription\Entities\BundleCourse;
use Modules\AmazonS3\Http\Controllers\AmazonS3Controller;
use Modules\BundleSubscription\Entities\BundleCoursePlan;
use Modules\Newsletter\Http\Controllers\AcelleController;
use Modules\OrgInstructorPolicy\Entities\OrgPolicyCategory;
use Modules\Newsletter\Http\Controllers\MailchimpController;
use Modules\Newsletter\Http\Controllers\GetResponseController;
use Modules\Membership\Repositories\Interfaces\MembershipCourseRepositoryInterface;


class CourseSettingController extends Controller
{
    use Filepond, ImageStore;

    public function getSubscriptionList()
    {
        $list = [];

        try {
            $user = Auth::user();
            if ($user->subscription_method == "Mailchimp" && $user->subscription_api_status == 1) {
                $mailchimp = new MailchimpController();
                $mailchimp->mailchimp($user->subscription_api_key);
                $getlists = $mailchimp->mailchimpLists();
                foreach ($getlists as $key => $l) {
                    $list[$key]['name'] = $l['name'];
                    $list[$key]['id'] = $l['id'];
                }

            } elseif ($user->subscription_method == "GetResponse" && $user->subscription_api_status == 1) {
                $getResponse = new GetResponseController();
                $getResponse->getResponseApi($user->subscription_api_key);
                $getlists = $getResponse->getResponseLists();
                foreach ($getlists as $key => $l) {
                    $list[$key]['name'] = $l->name;
                    $list[$key]['id'] = $l->campaignId;
                }
            } elseif ($user->subscription_method == "Acelle" && $user->subscription_api_status == 1) {
                $acelleController = new AcelleController();

                $acelleController->getAcelleApiResponse();
                $getlists = $acelleController->getAcelleList();
                foreach ($getlists as $key => $l) {
                    $list[$key]['name'] = $l['name'];
                    $list[$key]['id'] = $l['uid'];


                }
            }
        } catch (\Exception $exception) {

        }
        return $list;

    }


    public function ajaxGetCourseSubCategory(Request $request)
    {
        try {

            $query = Category::orderBy('position_order', 'ASC');
            if (isModuleActive('OrgInstructorPolicy') && \auth()->user()->role_id != 1) {
                $assign = OrgPolicyCategory::where('policy_id', \auth()->user()->policy_id)->pluck('category_id')->toArray();
                $query->whereIn('id', $assign);
            }
            $sub_categories = $query->where('parent_id', '=', $request->id)->get();
            return response()->json([$sub_categories]);
        } catch (Exception $e) {
            return response()->json("", 404);
        }
    }

    public function courseSortByCat($id)
    {
        try {
            if (!empty($id))
                $courses = Course::whereHas('enrolls')
                    ->where('category_id', $id)->with('user', 'category', 'subCategory', 'enrolls', 'comments', 'reviews', 'lessons')->paginate(15);
            else
                $courses = Course::whereHas('enrolls')->with('user', 'category', 'subCategory', 'enrolls', 'comments', 'reviews', 'lessons')->paginate(15);

            return response()->json([
                'courses' => $courses
            ], 200);
        } catch (Exception $e) {
            return response()->json(['error' => trans("lang.Oops, Something Went Wrong")]);
        }
    }


    public function getAllCourse()
    {
        try {
            $user = Auth::user();

            $video_list = [];
            $vdocipher_list = [];

            $courses = [];
            $query = Category::orderBy('position_order', 'ASC');
            if (isModuleActive('OrgInstructorPolicy') && \auth()->user()->role_id != 1) {
                $assign = OrgPolicyCategory::where('policy_id', \auth()->user()->policy_id)->pluck('category_id')->toArray();
                $query->whereIn('id', $assign);
            }
            $categories = $query->with('parent')->get();
            if ($user->role_id == 2) {
                $quizzes = OnlineQuiz::where('created_by', $user->id)->latest()->get();
            } else {
                $quizzes = OnlineQuiz::latest()->get();
            }

            $instructor_query = User::select('name', 'id');
            if (isModuleActive('UserType')) {
                $instructor_query->whereHas('userRoles', function ($q) {
                    $q->whereIn('role_id', [1, 2]);
                });
            } else {
                $instructor_query->whereIn('role_id', [1, 2]);
            }
            $instructors = $instructor_query->get();
            $languages = Language::select('id', 'native', 'code')
                ->where('status', '=', 1)
                ->get();
            $levels = CourseLevel::where('status', 1)->get();
            $title = trans('courses.All');

            $sub_lists = $this->getSubscriptionList();

            return view('coursesetting::courses', compact('sub_lists', 'levels', 'video_list', 'vdocipher_list', 'title', 'quizzes', 'courses', 'categories', 'languages', 'instructors'));
        } catch (Exception $e) {
            GettingError($e->getMessage(), url()->current(), request()->ip(), request()->userAgent());

        }
    }

    public function courseSortBy(Request $request)
    {
        if (demoCheck()) {
            return redirect()->back();
        }
        try {
            $user = Auth::user();

            $video_list = [];
            $vdocipher_list = [];

            $query = Category::orderBy('position_order', 'ASC');
            if (isModuleActive('OrgInstructorPolicy') && \auth()->user()->role_id != 1) {
                $assign = OrgPolicyCategory::where('policy_id', \auth()->user()->policy_id)->pluck('category_id')->toArray();
                $query->whereIn('id', $assign);
            }
            $categories = $query->with('parent')->get();
            $instructor_query = User::select('name', 'id');
            if (isModuleActive('UserType')) {
                $instructor_query->whereHas('userRoles', function ($q) {
                    $q->whereIn('role_id', [1, 2]);
                });
            } else {
                $instructor_query->whereIn('role_id', [1, 2]);
            }
            $instructors = $instructor_query->get();

            if ($user->role_id == 2) {
                $quizzes = OnlineQuiz::where('created_by', $user->id)->latest()->get();
            } else {
                $quizzes = OnlineQuiz::latest()->get();
            }
            $languages = Language::select('id', 'native', 'code')
                ->where('status', '=', 1)
                ->get();


            $courses = Course::query();
            // $courses->where('active_status', 1);
            if ($request->category != "") {
                $courses->where('category_id', $request->category);
            }
            if ($request->type != "") {
                $courses->where('type', $request->type);
            } else {
                $courses->whereIn('type', [1, 2]);
            }
            if ($request->instructor != "") {
                $courses->where('user_id', $request->instructor);
            }
            if ($request->status != "") {
                $courses->where('status', $request->status);
            }
            if ($request->search_required_type != "") {
                $courses->where('required_type', $request->search_required_type);
            }
            if ($request->search_delivery_mode != "") {
                $courses->where('mode_of_delivery', $request->search_delivery_mode);
            }
            if (Route::current()->getName() == 'getActiveCourse') {
                $courses->where('status', 1);
            }
            if (Route::current()->getName() == 'getPendingCourse') {
                $courses->where('status', 0);
            }

            if ($request->category) {
                $category_search = $request->category;
            } else {
                $category_search = '';

            }

            if ($request->type) {
                $category_type = $request->type;
            } else {
                $category_type = '';

            }

            if ($request->instructor) {
                $category_instructor = $request->instructor;
            } else {
                $category_instructor = '';

            }

            if ($request->search_required_type) {
                $search_required_type = $request->search_required_type;
            } else {
                $search_required_type = '';
            }

            if ($request->search_delivery_mode) {
                $search_delivery_mode = $request->search_delivery_mode;
            } else {
                $search_delivery_mode = '';
            }

            if ($request->search_status) {
                $category_status = $request->search_status;
            } else {
                $category_status = '';
            }

            $courses = [];
//            $courses = $courses->with('user', 'category', 'subCategory', 'enrolls', 'lessons')->orderBy('id', 'desc')->get();

            $levels = CourseLevel::where('status', 1)->get();
            $sub_lists = $this->getSubscriptionList();
            return view('coursesetting::courses', compact('search_delivery_mode', 'search_required_type', 'sub_lists', 'levels', 'category_search', 'vdocipher_list', 'category_instructor', 'category_type', 'category_status', 'video_list', 'quizzes', 'courses', 'categories', 'languages', 'instructors'));

        } catch (Exception $e) {
            GettingError($e->getMessage(), url()->current(), request()->ip(), request()->userAgent());
        }
    }


    public function saveCourse(Request $request)
    {
        Session::flash('type', 'store');

        if (demoCheck()) {
            return redirect()->back();
        }

        $code = auth()->user()->language_code;

        $rules = [
            'title.' . $code => 'required|max:255',
            'type' => 'required',
            'language' => 'required',
            'duration' => 'nullable',
            'image' => 'nullable|mimes:jpeg,bmp,png,jpg|max:1024',

        ];

        $this->validate($request, $rules, validationMessage($rules));

        if ($request->type == 1) {
            $rules = [
                'level' => 'required',
                'category' => 'required',
                // 'host' => 'required',
            ];
            $this->validate($request, $rules, validationMessage($rules));

            if (isset($request->show_overview_media)) {

                $rules = [
                    'host' => 'required',
                ];
                $this->validate($request, $rules, validationMessage($rules));

                if ($request->get('host') == "Vimeo") {
                    $rules = [
                        'vimeo' => 'required',
                    ];
                    $this->validate($request, $rules, validationMessage($rules));

                } elseif ($request->get('host') == "VdoCipher") {
                    $rules = [
                        'vdocipher' => 'required',
                    ];
                    $this->validate($request, $rules, validationMessage($rules));
                } elseif ($request->get('host') == "Youtube") {
                    $rules = [
                        'trailer_link' => 'required'
                    ];
                    $this->validate($request, $rules, validationMessage($rules));

                }
            }

        }


        try {

            $course = new Course();
            if ($request->file('image') != "") {
                $course->image = $this->saveImage($request->image);
                $course->thumbnail = $this->saveImage($request->image, 270);
            }

            if (isModuleActive('Membership')) {
                if ($request->filled('is_membership')) {
                    $course->is_membership = 1;
                }
                if ($request->filled('all_level_member')) {
                    $course->all_level_member = $request->all_level_member;
                }
            }

            $course->user_id = Auth::id();
            if ($request->type == 1) {
                $course->quiz_id = null;
                $course->category_id = $request->category;
                $course->subcategory_id = $request->sub_category;
            } elseif ($request->type == 2) {
                $course->quiz_id = $request->quiz;
                $course->category_id = null;
                $course->subcategory_id = null;
            }


            $course->lang_id = $request->language;
            $course->scope = $request->scope;

            foreach ($request->title as $key => $title) {
                $course->setTranslation('title', $key, $title);
            }

            foreach ($request->about as $key => $about) {
                $course->setTranslation('about', $key, $about);
            }

            foreach ($request->requirements as $key => $requirements) {
                $course->setTranslation('requirements', $key, $requirements);
            }

            foreach ($request->outcomes as $key => $outcomes) {
                $course->setTranslation('outcomes', $key, $outcomes);
            }

            $course->slug = null;
            $course->duration = $request->duration;

            if (showEcommerce()) {
                if ($request->is_discount == 1) {
                    $course->discount_price = $request->discount_price;
                } else {
                    $course->discount_price = null;
                }
                if ($request->is_free == 1) {
                    $course->price = 0;
                    $course->discount_price = null;
                } else {
                    $course->price = $request->price;
                }
            } else {
                $course->price = 0;
                $course->discount_price = null;
            }

            if (isModuleActive('Org')) {
                $course->required_type = $request->required_type;
            } else {
                $course->required_type = 0;
            }

            $course->publish = 1;
            $course->status = 0;
            $course->level = $request->level;
            if ($request->iap) {
                $course->iap_product_id = $request->iap_product_id;
            } else {
                $course->iap_product_id = null;
            }

            $course->mode_of_delivery = $request->mode_of_delivery;

            $course->show_overview_media = $request->show_overview_media ? 1 : 0;
            $course->host = $request->host;
            $course->subscription_list = $request->subscription_list;

            if (!empty($request->assign_instructor)) {
                $course->user_id = $request->assign_instructor;
            }

            if ($request->get('host') == "Vimeo") {
                if (config('vimeo.connections.main.upload_type') == "Direct") {
                    $vimeoController = new VimeoController();
                    $course->trailer_link = $vimeoController->uploadFileIntoVimeo(md5(time()), $request->vimeo);
                } else {
                    $course->trailer_link = $request->vimeo;
                }

            } elseif ($request->get('host') == "VdoCipher") {
                $course->trailer_link = $request->vdocipher;
            } elseif ($request->get('host') == "Youtube") {
                $course->trailer_link = $request->trailer_link;
            } elseif ($request->get('host') == "Self") {
                $course->trailer_link = $this->getPublicPathFromServerId($request->get('file'), 'local');


            } elseif ($request->get('host') == "AmazonS3") {

                $course->trailer_link = $this->getPublicPathFromServerId($request->get('file'), 's3');

            } else {
                $course->trailer_link = null;
            }


            if (!empty($request->assign_instructor)) {
                $course->user_id = $request->assign_instructor;
            }


            if (!empty($request->assistant_instructors)) {
                $assistants = $request->assistant_instructors;
                if (($key = array_search($course->user_id, $assistants)) !== false) {
                    unset($assistants[$key]);
                }
                if (!empty($assistants)) {
                    $course->assistant_instructors = json_encode(array_values($assistants));
                }
            }

            $course->meta_keywords = $request->meta_keywords;
            $course->meta_description = $request->meta_description;

            $course->type = $request->type;
            $course->drip = $request->drip;
            $course->complete_order = $request->complete_order;
            if (Settings('frontend_active_theme') == "edume") {
                $course->what_learn1 = $request->what_learn1;
                $course->what_learn2 = $request->what_learn2;
            }
            $course->save();

            if (isModuleActive('Membership')) {
                $membershipInterface = App::make(MembershipCourseRepositoryInterface::class);
                // $membershipInterface->storeCourseMember($request->merge([
                //     'course_id'=>$course->id,
                // ]));
            }

            Toastr::success(trans('common.Operation successful'), trans('common.Success'));
            return redirect()->to(route('getAllCourse'));

        } catch (Exception $e) {
            GettingError($e->getMessage(), url()->current(), request()->ip(), request()->userAgent());

        }
    }

    public function AdminUpdateCourse(Request $request)
    {

        Session::flash('type', 'update');
        Session::flash('id', $request->id);

        if (demoCheck()) {
            return redirect()->back();
        }
        Session::flash('type', 'courseDetails');

        $code = auth()->user()->language_code;

        $rules = [
            'title.' . $code => 'required|max:255',
            'type' => 'required',
            'language' => 'required',
            'image' => 'nullable|mimes:jpeg,bmp,png,jpg|max:1024',

        ];
        $this->validate($request, $rules, validationMessage($rules));


        if ($request->type == 1) {
            $rules = [
                'duration' => 'nullable',
                'level' => 'required',
                'category' => 'required',
            ];
            $this->validate($request, $rules, validationMessage($rules));

            if (isset($request->show_overview_media)) {

                if ($request->get('host') == "Vimeo") {
                    $rules = [
                        'vimeo' => 'required',
                    ];
                    $this->validate($request, $rules, validationMessage($rules));

                } elseif ($request->get('host') == "VdoCipher") {
                    $rules = [
                        'vdocipher' => 'required',
                    ];
                    $this->validate($request, $rules, validationMessage($rules));

                } elseif ($request->get('host') == "Youtube") {
                    $rules = [
                        'trailer_link' => 'required'
                    ];
                    $this->validate($request, $rules, validationMessage($rules));

                }
            }

        }


        try {

            $course = Course::find($request->id);
            $course->scope = $request->scope;
            if ($request->file('image') != "") {
                $course->image = $this->saveImage($request->image);
                $course->thumbnail = $this->saveImage($request->image, 270);
            }


            $course->user_id = Auth::id();

            if (!empty($request->assign_instructor)) {
                $course->user_id = $request->assign_instructor;
            }
            $course->drip = $request->drip;
            $course->complete_order = $request->complete_order;
            $course->lang_id = $request->language;
            foreach ($request->title as $key => $title) {
                $course->setTranslation('title', $key, $title);
            }

            foreach ($request->about as $key => $about) {
                $course->setTranslation('about', $key, $about);
            }

            foreach ($request->requirements as $key => $requirements) {
                $course->setTranslation('requirements', $key, $requirements);
            }

            foreach ($request->outcomes as $key => $outcomes) {
                $course->setTranslation('outcomes', $key, $outcomes);
            }
            $course->duration = $request->duration;
            $course->subscription_list = $request->subscription_list;

            if (showEcommerce()) {
                if ($request->is_discount == 1) {
                    $course->discount_price = $request->discount_price;
                } else {
                    $course->discount_price = null;
                }
                if ($request->is_free == 1) {
                    $course->price = 0;
                    $course->discount_price = null;
                } else {
                    $course->price = $request->price;
                }
            } else {
                $course->price = 0;
                $course->discount_price = null;
            }


            $course->level = $request->level;
            if ($request->iap) {
                $course->iap_product_id = $request->iap_product_id;
            } else {
                $course->iap_product_id = null;
            }
            $course->mode_of_delivery = $request->mode_of_delivery;

            $course->show_overview_media = $request->show_overview_media ? 1 : 0;
            if ($request->get('host') == "Vimeo") {
                if (config('vimeo.connections.main.upload_type') == "Direct") {
                    $vimeoController = new VimeoController();
                    $course->trailer_link = $vimeoController->uploadFileIntoVimeo(md5(time()), $request->vimeo);
                } else {
                    $course->trailer_link = $request->vimeo;
                }
            } elseif ($request->get('host') == "VdoCipher") {
                $course->trailer_link = $request->vdocipher;
            } elseif ($request->get('host') == "Youtube") {
                $course->trailer_link = $request->trailer_link;
            } elseif ($request->get('host') == "Self") {
                if ($request->get('file')) {
                    $course->trailer_link = $this->getPublicPathFromServerId($request->get('file'), 'local');

                }

            } elseif ($request->get('host') == "AmazonS3") {
                if ($request->get('file')) {

                    $course->trailer_link = $this->getPublicPathFromServerId($request->get('file'), 's3');
                }


            } else {
                $course->trailer_link = null;
            }
            if (isModuleActive('Org')) {
                $course->required_type = $request->required_type;
            } else {
                $course->required_type = 0;
            }
            $course->host = $request->host;
            $course->meta_keywords = $request->meta_keywords;
            $course->meta_description = $request->meta_description;
            $course->type = $request->type;
            if ($request->type == 1) {
                $course->quiz_id = null;
                $course->category_id = $request->category;
                $course->subcategory_id = $request->sub_category;
            } elseif ($request->type == 2) {
                $course->quiz_id = $request->quiz;
                $course->category_id = null;
                $course->subcategory_id = null;
            }

            if (Settings('frontend_active_theme') == "edume") {
                $course->what_learn1 = $request->what_learn1;
                $course->what_learn2 = $request->what_learn2;
            }
            if (!empty($request->assistant_instructors)) {
                $assistants = $request->assistant_instructors;
                if (($key = array_search($course->user_id, $assistants)) !== false) {
                    unset($assistants[$key]);
                }
                if (!empty($assistants)) {
                    $course->assistant_instructors = json_encode(array_values($assistants));
                }
            }

            $course->save();

            Toastr::success(trans('common.Operation successful'), trans('common.Success'));
            return redirect()->back();

        } catch (Exception $e) {
            GettingError($e->getMessage(), url()->current(), request()->ip(), request()->userAgent());
        }
    }

    public function AdminUpdateCourseCertificate(Request $request)
    {

        Session::flash('type', 'certificate');
        Session::flash('id', $request->course_id);

        if (demoCheck()) {
            return redirect()->back();
        }


        $rules = [
            'certificate' => 'required',

        ];
        $this->validate($request, $rules, validationMessage($rules));


        try {

            $course = Course::find($request->course_id);
            $course->certificate_id = $request->certificate;
            $course->save();

            Toastr::success(trans('common.Operation successful'), trans('common.Success'));
            return redirect()->back();

        } catch (Exception $e) {
            Toastr::error(trans('common.Operation failed'), trans('common.Failed'));
            return redirect()->back();
        }
    }


    public function CourseQuetionShow($question_id, $id, $chapter_id, $lesson_id)
    {
        try {
            $levels = QuestionLevel::get();
            $groups = QuestionGroup::get();
            $banks = [];
            $bank = QuestionBank::with('category', 'subCategory', 'questionGroup')->find($question_id);
            $query = Category::orderBy('position_order', 'ASC');
            if (isModuleActive('OrgInstructorPolicy') && \auth()->user()->role_id != 1) {
                $assign = OrgPolicyCategory::where('policy_id', \auth()->user()->policy_id)->pluck('category_id')->toArray();
                $query->whereIn('id', $assign);
            }
            $categories = $query->with('parent')->get();
            $data = [];
            $data['lesson_id'] = $lesson_id;
            $data['chapter_id'] = $chapter_id;
            $data['edit_chapter_id'] = $chapter_id;

            $user = Auth::user();
            $course = Course::findOrFail($id);
            if ($course->type == 1) {

                if ($user->role_id == 2) {
                    $quizzes = OnlineQuiz::where('category_id', $course->category_id)->where('created_by', $user->id)->latest()->get();
                } else {
                    $quizzes = OnlineQuiz::where('category_id', $course->category_id)->latest()->get();
                }

            } else {
                if ($user->role_id == 2) {

                    $quizzes = OnlineQuiz::where('created_by', $user->id)->where('active_status', 1)->get();
                } else {
                    $quizzes = OnlineQuiz::where('active_status', 1)->get();

                }
            }

            $chapters = Chapter::where('course_id', $id)->orderBy('position', 'asc')->with('lessons')->get();


            $query = Category::orderBy('position_order', 'ASC');
            if (isModuleActive('OrgInstructorPolicy') && \auth()->user()->role_id != 1) {
                $assign = OrgPolicyCategory::where('policy_id', \auth()->user()->policy_id)->pluck('category_id')->toArray();
                $query->whereIn('id', $assign);
            }
            $categories = $query->with('parent')->get();
            $instructor_query = User::select('name', 'id');
            if (isModuleActive('UserType')) {
                $instructor_query->whereHas('userRoles', function ($q) {
                    $q->whereIn('role_id', [1, 2]);
                });
            } else {
                $instructor_query->whereIn('role_id', [1, 2]);
            }
            $instructors = $instructor_query->get();
            $languages = Language::select('id', 'native', 'code')
                ->where('status', '=', 1)
                ->get();
            $course_exercises = CourseExercise::where('course_id', $id)->get();

            $video_list = [];
            $vdocipher_list = [];
            $levels = CourseLevel::where('status', 1)->get();
            if (Auth::user()->role_id == 1) {
                $certificates = Certificate::latest()->get();
            } else {
                $certificates = Certificate::where('created_by', Auth::user()->id)->latest()->get();
            }

            return view('coursesetting::course_details', compact('data', 'bank', 'vdocipher_list', 'levels', 'video_list', 'course', 'chapters', 'categories', 'instructors', 'languages', 'course_exercises', 'quizzes', 'certificates'));

        } catch (\Exception $e) {
            Toastr::error(trans('common.Operation failed'), trans('common.Failed'));
            return redirect()->back();
        }
    }

    public function CourseLessonShow($id, $chapter_id, $lesson_id)
    {
        try {
            $data = [];
            $data['edit_lesson_id'] = $lesson_id;
            $data['chapter_id'] = $chapter_id;

            $user = Auth::user();
            $course = Course::findOrFail($id);
            if ($course->type == 1) {
                if ($user->role_id == 2) {
                    $quizzes = OnlineQuiz::where('category_id', $course->category_id)->where('created_by', $user->id)->latest()->get();
                } else {
                    $quizzes = OnlineQuiz::where('category_id', $course->category_id)->latest()->get();
                }
            } else {
                if ($user->role_id == 2) {
                    $quizzes = OnlineQuiz::where('created_by', $user->id)->where('active_status', 1)->get();
                } else {
                    $quizzes = OnlineQuiz::where('active_status', 1)->get();
                }
            }

            $chapters = Chapter::where('course_id', $id)->orderBy('position', 'asc')->with('lessons')->get();

            $query = Category::orderBy('position_order', 'ASC');
            if (isModuleActive('OrgInstructorPolicy') && \auth()->user()->role_id != 1) {
                $assign = OrgPolicyCategory::where('policy_id', \auth()->user()->policy_id)->pluck('category_id')->toArray();
                $query->whereIn('id', $assign);
            }
            $categories = $query->with('parent')->get();
            $instructor_query = User::select('name', 'id');
            if (isModuleActive('UserType')) {
                $instructor_query->whereHas('userRoles', function ($q) {
                    $q->whereIn('role_id', [1, 2]);
                });
            } else {
                $instructor_query->whereIn('role_id', [1, 2]);
            }
            $instructors = $instructor_query->get();
            $languages = Language::select('id', 'native', 'code')
                ->where('status', '=', 1)
                ->get();
            $course_exercises = CourseExercise::where('course_id', $id)->get();

            $video_list = [];
            $vdocipher_list = [];

            $levels = CourseLevel::where('status', 1)->get();
            if (Auth::user()->role_id == 1) {
                $certificates = Certificate::latest()->get();
            } else {
                $certificates = Certificate::where('created_by', Auth::user()->id)->latest()->get();
            }
            $editLesson = Lesson::where('id', $lesson_id)->first();


            $data['isDefault'] = false;
            if (isModuleActive('Org')) {
                $material = OrgMaterial::where('link', $editLesson->video_url)->first();
                if ($material) {
                    $data['isDefault'] = false;
                } else {
                    $data['isDefault'] = true;
                }
            }

            return view('coursesetting::course_details', $data, compact('data', 'editLesson', 'levels', 'video_list', 'vdocipher_list', 'course', 'chapters', 'categories', 'instructors', 'languages', 'course_exercises', 'quizzes', 'certificates'));

        } catch (\Exception $e) {
            Toastr::error(trans('common.Operation failed'), trans('common.Failed'));
            return redirect()->back();
        }
    }

    public function CourseChapterShow($id, $chapter_id)
    {
        try {
            $data = [];
            $data['chapter_id'] = $chapter_id;

            $user = Auth::user();
            $course = Course::findOrFail($id);
            if ($course->type == 1) {

                if ($user->role_id == 2) {
                    $quizzes = OnlineQuiz::where('category_id', $course->category_id)->where('created_by', $user->id)->latest()->get();
                } else {
                    $quizzes = OnlineQuiz::where('category_id', $course->category_id)->latest()->get();
                }

            } else {
                if ($user->role_id == 2) {
                    $quizzes = OnlineQuiz::where('created_by', $user->id)->where('active_status', 1)->get();
                } else {
                    $quizzes = OnlineQuiz::where('active_status', 1)->get();

                }
            }

            $chapters = Chapter::where('course_id', $id)->orderBy('position', 'asc')->with('lessons')->get();

            $query = Category::orderBy('position_order', 'ASC');
            if (isModuleActive('OrgInstructorPolicy') && \auth()->user()->role_id != 1) {
                $assign = OrgPolicyCategory::where('policy_id', \auth()->user()->policy_id)->pluck('category_id')->toArray();
                $query->whereIn('id', $assign);
            }
            $categories = $query->with('parent')->get();
            $instructor_query = User::select('name', 'id');
            if (isModuleActive('UserType')) {
                $instructor_query->whereHas('userRoles', function ($q) {
                    $q->whereIn('role_id', [1, 2]);
                });
            } else {
                $instructor_query->whereIn('role_id', [1, 2]);
            }
            $instructors = $instructor_query->get();
            $languages = Language::select('id', 'native', 'code')
                ->where('status', '=', 1)
                ->get();
            $course_exercises = CourseExercise::where('course_id', $id)->get();

            $video_list = [];
            $vdocipher_list = [];

            $levels = CourseLevel::where('status', 1)->get();
            if (Auth::user()->role_id == 1) {
                $certificates = Certificate::latest()->get();
            } else {
                $certificates = Certificate::where('created_by', Auth::user()->id)->latest()->get();
            }
            $editChapter = Chapter::where('id', $chapter_id)->first();

            return view('coursesetting::course_details', compact('data', 'editChapter', 'levels', 'video_list', 'vdocipher_list', 'course', 'chapters', 'categories', 'instructors', 'languages', 'course_exercises', 'quizzes', 'certificates'));

        } catch (\Exception $e) {
            Toastr::error(trans('common.Operation failed'), trans('common.Failed'));
            return redirect()->back();
        }
    }


    public function courseDetails($id, $data = null)
    {
        $user = Auth::user();
        $course = Course::findOrFail($id);
        if ($course->type == 1) {

            if ($user->role_id == 2) {
                $quizzes = OnlineQuiz::where('status', 1)->where('category_id', $course->category_id)->where('created_by', $user->id)->latest()->get();
            } else {
                $quizzes = OnlineQuiz::where('status', 1)->where('category_id', $course->category_id)->latest()->get();
            }

        } else {
            if ($user->role_id == 2) {

                $quizzes = OnlineQuiz::where('status', 1)->where('created_by', $user->id)->get();
            } else {
                $quizzes = OnlineQuiz::where('status', 1)->get();

            }
        }

        $chapters = Chapter::where('course_id', $id)->orderBy('position', 'asc')->with('lessons')->get();

        $query = Category::orderBy('position_order', 'ASC');
        if (isModuleActive('OrgInstructorPolicy') && \auth()->user()->role_id != 1) {
            $assign = OrgPolicyCategory::where('policy_id', \auth()->user()->policy_id)->pluck('category_id')->toArray();
            $query->whereIn('id', $assign);
        }
        $categories = $query->with('parent')->get();
        $instructor_query = User::select('name', 'id');
        if (isModuleActive('UserType')) {
            $instructor_query->whereHas('userRoles', function ($q) {
                $q->whereIn('role_id', [1, 2]);
            });
        } else {
            $instructor_query->whereIn('role_id', [1, 2]);
        }
        $instructors = $instructor_query->get();
        $languages = Language::select('id', 'native', 'code')
            ->where('status', '=', 1)
            ->get();
        $course_exercises = CourseExercise::where('course_id', $id)->get();

        $video_list = [];
        $vdocipher_list = [];


        $levels = CourseLevel::where('status', 1)->get();
        if (Auth::user()->role_id == 1) {
            $certificates = Certificate::latest()->get();
        } else {
            $certificates = Certificate::where('created_by', Auth::user()->id)->latest()->get();
        }

        return view('coursesetting::course_details', compact('data', 'vdocipher_list', 'levels', 'video_list', 'course', 'chapters', 'categories', 'instructors', 'languages', 'course_exercises', 'quizzes', 'certificates'));

    }

    public function setCourseDripContent(Request $request)
    {

        Session::flash('type', 'drip');


        $lesson_id = $request->get('lesson_id');
        $lesson_date = $request->get('lesson_date');
        $lesson_day = $request->get('lesson_day');
        $drip_type = $request->get('drip_type');


        if (!empty($lesson_id) && is_array($lesson_id)) {
            foreach ($lesson_id as $l_key => $l_id) {
                $lesson = Lesson::find($l_id);

                if ($lesson) {

                    $checkType = $drip_type[$l_key];

                    if ($checkType == 1) {
                        $lesson->unlock_days = null;

                        if (!empty($lesson_date[$l_key])) {
                            $lesson->unlock_date = date('Y-m-d', strtotime($lesson_date[$l_key]));
                        } else {
                            $lesson->unlock_date = null;
                        }
                    } else {
                        $lesson->unlock_date = null;
                        if (!empty($lesson_day[$l_key])) {
                            $lesson->unlock_days = $lesson_day[$l_key];
                        } else {
                            $lesson->unlock_days = null;
                        }
                    }


                    $lesson->save();
                }
            }

        }
        Toastr::success(trans('common.Operation successful'), trans('common.Success'));
        return redirect()->back();
    }


    public function changeChapterPosition(Request $request)
    {
        $ids = $request->get('ids');
        if (count($ids) != 0) {
            foreach ($ids as $key => $id) {

                $chapter = Chapter::find($id);
                if ($chapter) {
                    $chapter->position = $key + 1;
                    $chapter->save();
                }
            }
        }
        return true;
    }

    public function changeLessonPosition(Request $request)
    {
        $ids = $request->get('ids');
        if (count($ids) != 0) {
            foreach ($ids as $key => $id) {
                $lesson = Lesson::find($id);
                if ($lesson) {
                    $lesson->position = $key + 1;
                    $lesson->save();
                }
            }
        }
        return true;
    }


    public function courseDelete($id)
    {
        if (demoCheck()) {
            return redirect()->back();
        }

        $hasCourse = CourseEnrolled::where('course_id', $id)->count();
        if ($hasCourse != 0) {
            Toastr::error('Course Already Enrolled By ' . $hasCourse . ' Student', trans('common.Failed'));
            return redirect()->back();
        }

        $carts = Cart::where('course_id', $id)->get();
        foreach ($carts as $cart) {
            $cart->delete();
        }

        $course = Course::findOrFail($id);
        if ($course->host == "Self") {
            if (file_exists($course->trailer_link)) {
                unlink($course->trailer_link);
            }
        }
        if (file_exists($course->image)) {
            unlink($course->image);
        }
        if (file_exists($course->thumbnail)) {
            unlink($course->thumbnail);
        }

        $chapters = Chapter::where('course_id', $course->id)->get();
        foreach ($chapters as $chapter) {
            $lessons = Lesson::where('chapter_id', $chapter->id)->where('course_id', $course->id)->get();
            foreach ($lessons as $key => $lesson) {
                $complete_lessons = LessonComplete::where('lesson_id', $lesson->id)->get();
                foreach ($complete_lessons as $complete) {
                    $complete->delete();
                }
                $lessonController = new LessonController();
                $lessonController->lessonFileDelete($lesson);
                $lesson->delete();
            }

            $chapter->delete();
        }

        if (isModuleActive('BundleSubscription')) {
            $bundle = BundleCourse::where('course_id', $course->id)->get();
            foreach ($bundle as $b) {
                $b->delete();
            }
        }

        $course->delete();


        Toastr::success(trans('common.Operation successful'), trans('common.Success'));
        return redirect()->back();
    }


    public function getAllCourseData(Request $request)
    {

        $query = Course::whereIn('type', [1, 2])->with('category', 'quiz', 'user');
        if ($request->course_status != "") {
            if ($request->course_status == 1) {
                $query->where('courses.status', 1);
            } elseif ($request->course_status == 0) {
                $query->where('courses.status', 0);
            }
        }
        if ($request->category != "") {
            $query->where('category_id', $request->category);
        }
        if ($request->type != "") {
            $query->where('type', $request->type);
        }
        if ($request->instructor != "") {
            $query->where('user_id', $request->instructor);
        }
        if ($request->search_status != "") {
            $query->where('courses.status', $request->search_status);
        }
        if ($request->required_type != "") {
            $query->where('required_type', $request->required_type);
        }
        if ($request->mode_of_delivery != "") {
            $query->where('mode_of_delivery', $request->mode_of_delivery);
        }

        if (isInstructor()) {
            $query->where('user_id', '=', Auth::id());
//            $query->orWhereJsonContains('assistant_instructors',[(string)Auth::user()->id]);
            $query->orWhere('assistant_instructors', 'like', '%"{' . Auth::id() . '}"%');
        }
        if (isModuleActive('OrgInstructorPolicy') && \auth()->user()->role_id != 1) {
            $assigns = Auth::user()->policy->course_assigns;
            $ids = [];
            foreach ($assigns as $assign) {
                $ids[] = $assign->course_id;
            }
            $query->orWhereIn('id', $ids);
        }

        $query->select('courses.*');

        return Datatables::of($query)
            ->addIndexColumn()
            ->editColumn('title', function ($query) {
                return $query->title;
            })
            ->editColumn('required_type', function ($query) {
                return $query->required_type == 1 ? trans('courses.Compulsory') : trans('courses.Open');
            })->editColumn('mode_of_delivery', function ($query) {
                if ($query->mode_of_delivery == 1) {
                    $title = trans('courses.Online');

                } elseif ($query->mode_of_delivery == 2) {
                    $title = trans('courses.Distance Learning');
                } else {
                    if (isModuleActive('Org')) {
                        $title = trans('courses.Offline');
                    } else {
                        $title = trans('courses.Face-to-Face');
                    }
                }
                return $title;
            })
            ->addColumn('type', function ($query) {
                return $query->type == 1 ? trans('courses.Course') : trans('quiz.Quiz');

            })->addColumn('status', function ($query) {
                return view('coursesetting::components._course_status_td', ['query' => $query]);
            })->addColumn('lessons', function ($query) {
                return $query->lessons->count();
            })
            ->editColumn('category', function ($query) {
                if ($query->category) {
                    return $query->category->name;
                } else {
                    return '';
                }

            })
            ->editColumn('quiz', function ($query) {
                if ($query->quiz) {
                    return $query->quiz->title;
                } else {
                    return '';
                }

            })->editColumn('user', function ($query) {
                if ($query->user) {
                    return $query->user->name;
                } else {
                    return '';
                }

            })->addColumn('enrolled_users', function ($query) {
                return $query->enrollUsers->where('teach_via', 1)->count() . "/" . $query->enrollUsers->where('teach_via', 2)->count();
            })
            ->editColumn('scope', function ($query) {
                if ($query->scope == 1) {
                    $scope = trans('courses.Public');
                } else {
                    $scope = trans('courses.Private');
                }
                return $scope;

            })->addColumn('price', function ($query) {
                return view('coursesetting::components._course_price_td', ['query' => $query]);
            })->addColumn('action', function ($query) {
                return view('coursesetting::components._course_action_td', ['query' => $query]);
            })->rawColumns(['status', 'price', 'action', 'enrolled_users'])
            ->make(true);
    }

    public function addNewCourse()
    {
        if (saasPlanCheck('course')) {
            Toastr::error('You have reached valid course limit', trans('common.Failed'));
            return redirect()->back();
        }
        $user = Auth::user();

        $video_list = [];
        $vdocipher_list = [];


        $query = Category::orderBy('position_order', 'ASC');
        if (isModuleActive('OrgInstructorPolicy') && \auth()->user()->role_id != 1) {
            $assign = OrgPolicyCategory::where('policy_id', \auth()->user()->policy_id)->pluck('category_id')->toArray();
            $query->whereIn('id', $assign);
        }
        $categories = $query->with('parent')->get();
        if ($user->role_id == 2) {
            $quizzes = OnlineQuiz::where('status', 1)->where('created_by', $user->id)->latest()->get();
        } else {
            $quizzes = OnlineQuiz::where('status', 1)->latest()->get();
        }

        $instructor_query = User::select('name', 'id');
        if (isModuleActive('UserType')) {
            $instructor_query->whereHas('userRoles', function ($q) {
                $q->whereIn('role_id', [1, 2]);
            });
        } else {
            $instructor_query->whereIn('role_id', [1, 2]);
        }
        $instructors = $instructor_query->get();

        $languages = Language::select('id', 'native', 'code')
            ->where('status', '=', 1)
            ->get();
        $levels = CourseLevel::where('status', 1)->get();
        $title = trans('courses.All');

        $sub_lists = $this->getSubscriptionList();

        return view('coursesetting::add_course', compact('sub_lists', 'levels', 'video_list', 'vdocipher_list', 'title', 'quizzes', 'categories', 'languages', 'instructors', 'vdocipher_list'));


    }

    public function changeLessonChapter(Request $request)
    {
        $chapter_id = $request->chapter_id;
        $lesson_id = $request->lesson_id;

        $lesson = Lesson::findOrFail($lesson_id);
        $lesson->chapter_id = $chapter_id;
        $lesson->save();
        return true;
    }

    public function courseMakeAsFeature($id, $type)
    {
        try {
            if ($type == "make") {
                $items = Course::all();
                foreach ($items as $item) {
                    if ($id == $item->id) {
                        $featureStatus = 1;
                    } else {
                        $featureStatus = 0;
                    }
                    $item->feature = $featureStatus;
                    $item->save();
                }
            } else {
                $course = Course::find($id);
                $course->feature = 0;
                $course->save();
            }

            Toastr::success(trans('common.Operation successful'), trans('common.Success'));
            return redirect()->to(route('getAllCourse'));
        } catch (\Exception $e) {
            GettingError($e->getMessage(), url()->current(), request()->ip(), request()->userAgent());

        }
    }

    public function CourseQuestionDelete($quiz_id, $question_id)
    {
        $assign = OnlineExamQuestionAssign::where('online_exam_id', $quiz_id)->where('question_bank_id', $question_id)->first();
        if ($assign) {
            $assign->delete();
        }

        Toastr::success(trans('common.Operation successful'), trans('common.Success'));
        return redirect()->back();

    }


    public function lessonFlies($id)
    {
        $lesson = Lesson::findOrFail($id);
        $files = $lesson->files;
        return view('coursesetting::version', compact('lesson', 'files'));
    }

    public function setting()
    {
        return view('coursesetting::setting');
    }

    public function settingSubmit(Request $request)
    {
        foreach ($request->except(['_token']) as $key => $value) {
            UpdateGeneralSetting($key, $value);
        }

        Toastr::success(trans('common.Operation successful'), trans('common.Success'));
        return redirect()->back();
    }

    public function addNewCourseData()
    {
        if (saasPlanCheck('course')) {
            Toastr::error('You have reached valid course limit', trans('common.Failed'));
            return redirect()->back();
        }
        $user = Auth::user();

        $video_list = [];
        $vdocipher_list = [];


        $query = Category::orderBy('position_order', 'ASC');
        if (isModuleActive('OrgInstructorPolicy')) {
            $assign = OrgPolicyCategory::where('policy_id', \auth()->user()->policy_id)->pluck('category_id')->toArray();
            $query->whereIn('id', $assign);
        }
        $data['categories'] = $query->with('parent')->get();
        if ($user->role_id == 2) {
            $data['quizzes'] = OnlineQuiz::where('status', 1)->where('created_by', $user->id)->latest()->get();
        } else {
            $data['quizzes'] = OnlineQuiz::where('status', 1)->latest()->get();
        }

        $data['instructors'] = User::whereIn('role_id', [1, 2])->select('name', 'id')->get();
        $data['languages'] = Language::select('id', 'native', 'code')
            ->where('status', '=', 1)
            ->get();
        $data['levels'] = CourseLevel::where('status', 1)->get();
        $data['title'] = trans('courses.All');

        $data['sub_lists'] = $this->getSubscriptionList();
        return $data;
    }
}
